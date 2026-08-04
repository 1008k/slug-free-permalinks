<?php
/**
 * Plugin Name: Slug-Free Permalinks
 * Plugin URI: https://happas.jp/en/slug-free-permalinks/
 * Description: Use ID-based permalinks for selected post types and taxonomies without managing slugs.
 * Version: 1.5.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Kodo
 * Author URI: https://happas.jp/en/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: slug-free-permalinks
 * Domain Path: /languages
 *
 * @package Slug_Free_Permalinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides ID-based permalink routing and plugin settings.
 */
final class PTID_Permalink_Plugin {

	private const OPTION_NAME                   = 'ptid_permalink_settings';
	private const MENU_SLUG                     = 'ptid-permalink-settings';
	private const REWRITE_MARKER                = 'ptid_route=1';
	private const REWRITE_MARKER_VERSION        = '1';
	private const REWRITE_MARKER_VERSION_OPTION = 'ptid_rewrite_marker_version';
	/**
	 * Normalized plugin settings.
	 *
	 * @var array|null
	 */
	private ?array $settings_cache = null;

	/**
	 * Enabled post type names.
	 *
	 * @var array|null
	 */
	private ?array $enabled_post_types_cache = null;

	/**
	 * Enabled taxonomy names.
	 *
	 * @var array|null
	 */
	private ?array $enabled_taxonomies_cache = null;

	/**
	 * Selected permalink structure.
	 *
	 * @var string|null
	 */
	private ?string $permalink_structure_cache = null;

	/**
	 * Whether rewrite rules need to be flushed after registration completes.
	 *
	 * @var bool
	 */
	private bool $rewrite_rules_flush_pending = false;

	/**
	 * Registers plugin hooks.
	 */
	public static function bootstrap(): void {
		$instance = new self();
		add_action( 'init', array( $instance, 'register_rewrite_rules' ), 99 );
		add_action( 'registered_post_type', array( $instance, 'refresh_rewrite_rules_after_registration' ), 10, 0 );
		add_action( 'registered_taxonomy', array( $instance, 'refresh_rewrite_rules_after_registration' ), 10, 0 );
		add_action( 'init', array( $instance, 'flush_pending_rewrite_rules' ), 999 );
		add_action( 'wp_loaded', array( $instance, 'flush_pending_rewrite_rules' ), 999 );
		add_filter( 'post_link', array( $instance, 'filter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( $instance, 'filter_permalink' ), 10, 2 );
		add_filter( 'term_link', array( $instance, 'filter_term_link' ), 10, 3 );
		add_filter( 'query_vars', array( $instance, 'register_query_vars' ) );
		add_action( 'parse_request', array( $instance, 'resolve_term_request' ) );
		add_action( 'template_redirect', array( $instance, 'redirect_legacy_permalink' ) );
		add_action( 'admin_init', array( $instance, 'register_settings' ) );
		add_action( 'admin_init', array( $instance, 'normalize_stored_settings' ) );
		add_action( 'admin_menu', array( $instance, 'register_settings_page' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( __FILE__ ),
			array( $instance, 'add_settings_link' )
		);
	}

	/**
	 * Registers rewrite rules and flushes them on activation.
	 */
	public static function activate(): void {
		if ( ! get_option( self::OPTION_NAME ) ) {
			add_option( self::OPTION_NAME, self::default_settings() );
		}

		$plugin = new self();
		$plugin->register_rewrite_rules();
		flush_rewrite_rules();
		update_option( self::REWRITE_MARKER_VERSION_OPTION, self::REWRITE_MARKER_VERSION, true );
	}

	/**
	 * Flushes rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Replaces a post permalink with its configured ID-based form.
	 *
	 * @param string  $post_link Existing post permalink.
	 * @param WP_Post $post      Post object.
	 * @return string Filtered post permalink.
	 */
	public function filter_permalink( string $post_link, $post ): string {

		if ( ! $this->is_feature_enabled() ) {
			return $post_link;
		}

		$post_context = $this->get_post_permalink_context( $post );
		if ( null === $post_context ) {
			return $post_link;
		}

		if ( ! in_array( $post_context['post_status'], array( 'publish', 'private' ), true ) ) {
			return $post_link;
		}

		if ( ! in_array( $post_context['post_type'], $this->get_enabled_post_types(), true ) ) {
			return $post_link;
		}

		$post_type_slug = $this->get_post_type_route_slug( $post_context['post_type'] );

		return $this->build_content_permalink( $post_link, $post_type_slug, $post_context['ID'], $this->get_polylang_home_url_for_post( $post_context['ID'] ) );
	}
	/**
	 * Replaces a term permalink with its configured ID-based form.
	 *
	 * @param string  $term_link Existing term permalink.
	 * @param WP_Term $term      Term object.
	 * @param string  $taxonomy  Taxonomy name.
	 * @return string Filtered term permalink.
	 */
	public function filter_term_link( string $term_link, WP_Term $term, string $taxonomy ): string {

		if ( ! $this->is_feature_enabled() ) {
			return $term_link;
		}
		if ( ! in_array( $taxonomy, $this->get_enabled_taxonomies(), true ) ) {
			return $term_link;
		}

		$taxonomy_slug = $this->get_taxonomy_route_slug( $taxonomy );

		return $this->build_content_permalink(
			$term_link,
			$taxonomy_slug,
			$term->term_id,
			$this->get_polylang_home_url_for_term( $term->term_id )
		);
	}

	/**
	 * Registers ID-based rewrite rules for enabled content types.
	 */
	public function register_rewrite_rules(): void {
		$this->register_rewrite_rules_for(
			$this->get_permalink_structure(),
			$this->get_enabled_post_types(),
			$this->get_enabled_taxonomies(),
			$this->is_feature_enabled()
		);

		if (
			$this->has_stale_persisted_rewrite_rules()
			|| self::REWRITE_MARKER_VERSION !== (string) get_option( self::REWRITE_MARKER_VERSION_OPTION, '' )
		) {
			$this->rewrite_rules_flush_pending = true;
		}
	}

	/**
	 * Refreshes rewrite rules after a post type or taxonomy is registered.
	 */
	public function refresh_rewrite_rules_after_registration(): void {
		$this->settings_cache            = null;
		$this->enabled_post_types_cache  = null;
		$this->enabled_taxonomies_cache  = null;
		$this->permalink_structure_cache = null;

		$this->register_rewrite_rules();
	}

	/**
	 * Flushes rewrite rules once when registration changed the persisted set.
	 */
	public function flush_pending_rewrite_rules(): void {
		if ( ! $this->rewrite_rules_flush_pending ) {
			return;
		}

		$this->rewrite_rules_flush_pending = false;
		flush_rewrite_rules( false );
		update_option( self::REWRITE_MARKER_VERSION_OPTION, self::REWRITE_MARKER_VERSION, true );
	}

	/**
	 * Registers the taxonomy query variable used by ID-based rules.
	 *
	 * @param array $query_vars Existing public query variables.
	 * @return array Filtered public query variables.
	 */
	public function register_query_vars( array $query_vars ): array {
		$query_vars[] = 'ptid_taxonomy';
		$query_vars[] = 'ptid_term_id';
		$query_vars[] = 'ptid_route';

		return $query_vars;
	}

	/**
	 * Resolves an ID-based taxonomy request to its term query variable.
	 *
	 * @param WP $wp Current WordPress request object.
	 */
	public function resolve_term_request( WP $wp ): void {
		if ( ! $this->is_feature_enabled() ) {
			return;
		}

		$taxonomy = sanitize_key( (string) ( $wp->query_vars['ptid_taxonomy'] ?? '' ) );
		$term_id  = absint( $wp->query_vars['ptid_term_id'] ?? 0 );

		if ( '' === $taxonomy || 1 > $term_id ) {
			return;
		}

		if ( ! in_array( $taxonomy, $this->get_enabled_taxonomies(), true ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
			return;
		}

		$wp->query_vars['taxonomy'] = $taxonomy;
		$wp->query_vars['term']     = $term->slug;

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( $taxonomy_object && is_string( $taxonomy_object->query_var ) && '' !== $taxonomy_object->query_var ) {
			$wp->query_vars[ $taxonomy_object->query_var ] = $term->slug;
		}
	}

	/**
	 * Registers the plugin setting and its sanitizer.
	 */
	public function register_settings(): void {

		register_setting(
			'ptid_permalink_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Normalizes stored settings during an administrative request.
	 */
	public function normalize_stored_settings(): void {

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			return;
		}

		$normalized = $this->normalize_settings( $settings );
		$conflicts  = $this->get_route_slug_conflicts( $normalized['post_types'], $normalized['taxonomies'] );
		if ( array() !== $conflicts ) {
			$this->add_route_slug_conflict_errors( $conflicts, true );
			$normalized = $this->remove_route_slug_conflicts( $normalized, $conflicts );
		}

		if ( $settings !== $normalized ) {
			update_option( self::OPTION_NAME, $normalized );
			$this->register_rewrite_rules_for(
				$normalized['structure'],
				$normalized['post_types'],
				$normalized['taxonomies'],
				$this->has_enabled_targets( $normalized )
			);
			flush_rewrite_rules();
			$this->prime_settings_cache( $normalized );
		}
	}

	/**
	 * Registers the settings page.
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Slug-Free Permalinks', 'slug-free-permalinks' ),
			__( 'Slug-Free Permalinks', 'slug-free-permalinks' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Adds a settings link to the Plugins screen.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Filtered plugin action links.
	 */
	public function add_settings_link( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::MENU_SLUG );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'slug-free-permalinks' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Sanitizes submitted plugin settings.
	 *
	 * @param mixed $input Submitted setting value.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		$previous = $this->get_settings();

		$settings  = array(
			'structure'       => $this->sanitize_structure( $input['structure'] ?? '' ),
			'post_types'      => $this->sanitize_enabled_items(
				$input['post_types'] ?? array(),
				array_keys( $this->get_available_post_types() )
			),
			'taxonomies'      => $this->sanitize_enabled_items(
				$input['taxonomies'] ?? array(),
				array_keys( $this->get_available_taxonomies() )
			),
			'redirect_legacy' => ! empty( $input['redirect_legacy'] ),
		);
		$conflicts = $this->get_route_slug_conflicts( $settings['post_types'], $settings['taxonomies'] );

		if ( array() !== $conflicts ) {
			$this->add_route_slug_conflict_errors( $conflicts );
			return $previous;
		}

		if ( $previous !== $settings ) {
			$this->register_rewrite_rules_for(
				$settings['structure'],
				$settings['post_types'],
				$settings['taxonomies'],
				$this->has_enabled_targets( $settings )
			);
			flush_rewrite_rules();
		}

		$this->prime_settings_cache( $settings );

		return $settings;
	}

	/**
	 * Renders the plugin settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = $this->get_settings();
		$post_types = $this->get_available_post_types();
		$taxonomies = $this->get_available_taxonomies();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Slug-Free Permalinks', 'slug-free-permalinks' ); ?></h1>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<p><?php echo esc_html__( 'Checked post types and taxonomies will use the selected ID based permalink format. Clear all checks to disable. Rewrite rules are flushed automatically when settings change.', 'slug-free-permalinks' ); ?></p>

			<form action="options.php" method="post">
			<?php settings_fields( 'ptid_permalink_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Permalink format', 'slug-free-permalinks' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[structure]">
									<option value="slash" <?php selected( $settings['structure'], 'slash' ); ?>>
									<?php echo esc_html( '/post/123/' ); ?>
									</option>
									<option value="hyphen" <?php selected( $settings['structure'], 'hyphen' ); ?>>
									<?php echo esc_html( '/post-123/' ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Target post types', 'slug-free-permalinks' ); ?></th>
							<td>
								<fieldset>
								<?php foreach ( $post_types as $post_type => $object ) : ?>
										<label style="display:block; margin-bottom:0.5rem;">
											<input
												name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]"
												type="checkbox"
												value="<?php echo esc_attr( $post_type ); ?>"
												<?php checked( in_array( $post_type, $settings['post_types'], true ) ); ?>
											/>
											<?php echo esc_html( $object->labels->singular_name . ' (' . $post_type . ')' ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Target taxonomies', 'slug-free-permalinks' ); ?></th>
							<td>
								<fieldset>
								<?php foreach ( $taxonomies as $taxonomy => $object ) : ?>
										<label style="display:block; margin-bottom:0.5rem;">
											<input
												name="<?php echo esc_attr( self::OPTION_NAME ); ?>[taxonomies][]"
												type="checkbox"
												value="<?php echo esc_attr( $taxonomy ); ?>"
												<?php checked( in_array( $taxonomy, $settings['taxonomies'], true ) ); ?>
											/>
											<?php echo esc_html( $object->labels->singular_name . ' (' . $taxonomy . ')' ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Redirect legacy permalinks', 'slug-free-permalinks' ); ?></th>
							<td>
								<label>
									<input
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[redirect_legacy]"
										type="checkbox"
										value="1"
									<?php checked( ! empty( $settings['redirect_legacy'] ) ); ?>
									/>
								<?php echo esc_html__( 'Redirect old slug based URLs to the current ID based permalink when WordPress can resolve the request.', 'slug-free-permalinks' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
			<?php submit_button(); ?>
			</form>
		</div>
			<?php
	}

	/**
	 * Redirects a resolved legacy permalink to its ID-based permalink.
	 */
	public function redirect_legacy_permalink(): void {
		if (
		is_admin()
		|| ! $this->is_feature_enabled()
		|| ! $this->should_redirect_legacy_requests()
		|| is_feed()
		|| is_preview()
		|| headers_sent()
		) {
			return;
		}

		$target_url = '';

		if ( is_singular() ) {
			$post = get_queried_object();

			if ( ! ( $post instanceof WP_Post ) ) {
				return;
			}

			if ( ! in_array( $post->post_type, $this->get_enabled_post_types(), true ) ) {
				return;
			}

			if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
				return;
			}

			$target_url = get_permalink( $post );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( ! ( $term instanceof WP_Term ) ) {
				return;
			}

			if ( ! in_array( $term->taxonomy, $this->get_enabled_taxonomies(), true ) ) {
				return;
			}

			$term_link = get_term_link( $term );
			if ( is_wp_error( $term_link ) ) {
				return;
			}

			$target_url = $term_link;
		} else {
			return;
		}

		if ( ! is_string( $target_url ) || '' === $target_url ) {
			return;
		}

		$current_url  = $this->get_current_request_url();
		$redirect_url = $this->append_current_query_args( $target_url, $current_url );

		if ( '' === $current_url || ! $this->should_redirect_to_target( $current_url, $redirect_url ) ) {
			return;
		}

		wp_safe_redirect( $redirect_url, 301, 'Post Type ID Permalink' );
		exit;
	}

	/**
	 * Returns the default plugin settings.
	 *
	 * @return array Default settings.
	 */
	private static function default_settings(): array {
		return array(
			'structure'       => 'slash',
			'post_types'      => array(),
			'taxonomies'      => array(),
			'redirect_legacy' => false,
		);
	}

	/**
	 * Returns normalized plugin settings.
	 *
	 * @return array Normalized settings.
	 */
	private function get_settings(): array {
		if ( is_array( $this->settings_cache ) ) {
			return $this->settings_cache;
		}

		$settings   = get_option( self::OPTION_NAME, array() );
		$normalized = $this->normalize_settings( is_array( $settings ) ? $settings : array() );
		return $this->prime_settings_cache( $normalized );
	}

	/**
	 * Determines whether any permalink targets are enabled.
	 *
	 * @return bool Whether the feature is enabled.
	 */
	private function is_feature_enabled(): bool {
		return $this->has_enabled_targets( $this->get_settings() );
	}

	/**
	 * Returns enabled public post type names.
	 *
	 * @return array Enabled post type names.
	 */
	private function get_enabled_post_types(): array {
		if ( is_array( $this->enabled_post_types_cache ) ) {
			return $this->enabled_post_types_cache;
		}

		$settings                       = $this->get_settings();
		$this->enabled_post_types_cache = $this->sanitize_enabled_items(
			$settings['post_types'] ?? array(),
			array_keys( $this->get_available_post_types() )
		);

		return $this->enabled_post_types_cache;
	}

	/**
	 * Returns enabled public taxonomy names.
	 *
	 * @return array Enabled taxonomy names.
	 */
	private function get_enabled_taxonomies(): array {

		if ( is_array( $this->enabled_taxonomies_cache ) ) {
			return $this->enabled_taxonomies_cache;
		}
		$settings                       = $this->get_settings();
		$this->enabled_taxonomies_cache = $this->sanitize_enabled_items(
			$settings['taxonomies'] ?? array(),
			array_keys( $this->get_available_taxonomies() )
		);

		return $this->enabled_taxonomies_cache;
	}

	/**
	 * Returns post types available for configuration.
	 *
	 * @return array Available post type objects keyed by name.
	 */
	private function get_available_post_types(): array {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'], $post_types['page'] );

		return $post_types;
	}

	/**
	 * Returns taxonomies available for configuration.
	 *
	 * @return array Available taxonomy objects keyed by name.
	 */
	private function get_available_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);

		unset( $taxonomies['post_format'] );

		return $taxonomies;
	}

	/**
	 * Returns the rewrite slug used for a post type's public URL.
	 *
	 * @param string $post_type Post type name.
	 * @return string Route slug.
	 */
	private function get_post_type_route_slug( string $post_type ): string {
		$post_type_object = get_post_type_object( $post_type );

		if ( $post_type_object && is_array( $post_type_object->rewrite ) && ! empty( $post_type_object->rewrite['slug'] ) ) {
			$route_slug = trim( (string) $post_type_object->rewrite['slug'], '/' );
			if ( '' !== $route_slug ) {
				return $route_slug;
			}
		}

		return $post_type;
	}

	/**
	 * Returns the rewrite slug used for a taxonomy's public URL.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string Route slug.
	 */
	private function get_taxonomy_route_slug( string $taxonomy ): string {
		$taxonomy_object = get_taxonomy( $taxonomy );

		if ( $taxonomy_object && is_array( $taxonomy_object->rewrite ) && ! empty( $taxonomy_object->rewrite['slug'] ) ) {
			$route_slug = trim( (string) $taxonomy_object->rewrite['slug'], '/' );
			if ( '' !== $route_slug ) {
				return $route_slug;
			}
		}

		return $taxonomy;
	}

	/**
	 * Finds duplicate rewrite slugs among selected content types.
	 *
	 * @param array $post_types Enabled post type names.
	 * @param array $taxonomies Enabled taxonomy names.
	 * @return array Conflicts keyed by rewrite slug.
	 */
	private function get_route_slug_conflicts( array $post_types, array $taxonomies ): array {
		$routes = array();

		foreach ( $post_types as $post_type ) {
			$routes[ $this->get_post_type_route_slug( $post_type ) ][] = $post_type;
		}

		foreach ( $taxonomies as $taxonomy ) {
			$routes[ $this->get_taxonomy_route_slug( $taxonomy ) ][] = $taxonomy;
		}

		return array_filter(
			$routes,
			static function ( array $content_types ): bool {
				return 1 < count( $content_types );
			}
		);
	}

	/**
	 * Adds settings errors for duplicate rewrite slugs.
	 *
	 * @param array $conflicts Conflicts keyed by rewrite slug.
	 * @param bool  $disabled  Whether conflicting saved selections were disabled.
	 */
	private function add_route_slug_conflict_errors( array $conflicts, bool $disabled = false ): void {
		if ( $disabled ) {
			/* translators: 1: rewrite slug, 2: selected content type names. */
			$message = __( 'The rewrite slug "%1$s" is shared by selected content types: %2$s. The conflicting saved selections were disabled.', 'slug-free-permalinks' );
		} else {
			/* translators: 1: rewrite slug, 2: selected content type names. */
			$message = __( 'The rewrite slug "%1$s" is shared by selected content types: %2$s. Choose unique rewrite slugs before saving.', 'slug-free-permalinks' );
		}

		foreach ( $conflicts as $route_slug => $content_types ) {
			add_settings_error(
				self::OPTION_NAME,
				'rewrite_slug_conflict_' . sanitize_key( $route_slug ),
				sprintf(
					/* translators: 1: rewrite slug, 2: selected content type names. */
					$message,
					esc_html( $route_slug ),
					esc_html( implode( ', ', $content_types ) )
				),
				'error'
			);
		}
	}

	/**
	 * Removes all content types involved in rewrite slug conflicts.
	 *
	 * @param array $settings  Normalized settings.
	 * @param array $conflicts Conflicts keyed by rewrite slug.
	 * @return array Settings without conflicting content types.
	 */
	private function remove_route_slug_conflicts( array $settings, array $conflicts ): array {
		$conflicting_types = array();

		foreach ( $conflicts as $content_types ) {
			$conflicting_types = array_merge( $conflicting_types, $content_types );
		}

		$settings['post_types'] = array_values( array_diff( $settings['post_types'], $conflicting_types ) );
		$settings['taxonomies'] = array_values( array_diff( $settings['taxonomies'], $conflicting_types ) );

		return $settings;
	}

	/**
	 * Returns the selected permalink structure.
	 *
	 * @return string Permalink structure name.
	 */
	private function get_permalink_structure(): string {
		if ( is_string( $this->permalink_structure_cache ) ) {
			return $this->permalink_structure_cache;
		}

		$settings                        = $this->get_settings();
		$this->permalink_structure_cache = $this->sanitize_structure( $settings['structure'] ?? '' );

		return $this->permalink_structure_cache;
	}

	/**
	 * Sanitizes a submitted permalink structure.
	 *
	 * @param mixed $structure Submitted structure value.
	 * @return string Sanitized structure name.
	 */
	private function sanitize_structure( $structure ): string {
		return 'hyphen' === $structure ? 'hyphen' : 'slash';
	}

	/**
	 * Normalizes a settings array against available content types.
	 *
	 * @param array $settings Settings to normalize.
	 * @return array Normalized settings.
	 */
	private function normalize_settings( array $settings ): array {
		$defaults = self::default_settings();

		return array(
			'structure'       => $this->sanitize_structure( $settings['structure'] ?? $defaults['structure'] ),
			'post_types'      => $this->sanitize_enabled_items(
				$settings['post_types'] ?? $defaults['post_types'],
				array_keys( $this->get_available_post_types() )
			),
			'taxonomies'      => $this->sanitize_enabled_items(
				$settings['taxonomies'] ?? $defaults['taxonomies'],
				array_keys( $this->get_available_taxonomies() )
			),
			'redirect_legacy' => ! empty( $settings['redirect_legacy'] ),
		);
	}

	/**
	 * Builds permalink context for a valid post.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 * @return array|null Permalink context, or null when unavailable.
	 */
	private function get_post_permalink_context( $post ): ?array {

		if ( ! is_object( $post ) ) {
			return null;
		}

		$post_id     = absint( $post->ID ?? 0 );
		$post_type   = isset( $post->post_type ) ? sanitize_key( (string) $post->post_type ) : '';
		$post_status = isset( $post->post_status ) ? sanitize_key( (string) $post->post_status ) : '';
		if ( 1 > $post_id || '' === $post_type || '' === $post_status ) {
			return null;
		}

		return array(
			'ID'          => $post_id,
			'post_type'   => $post_type,
			'post_status' => $post_status,
		);
	}

	/**
	 * Builds an ID-based path segment.
	 *
	 * @param string $slug Content type slug.
	 * @param int    $id   Content object ID.
	 * @return string ID-based relative path.
	 */
	private function build_id_path( string $slug, int $id ): string {

		if ( 'hyphen' === $this->get_permalink_structure() ) {
			return $slug . '-' . $id;
		}

			return $slug . '/' . $id;
	}

	/**
	 * Builds an ID-based permalink while preserving language and path context.
	 *
	 * @param string $existing_url      Existing content URL.
	 * @param string $slug              Content type slug.
	 * @param int    $id                Content object ID.
	 * @param string $language_home_url Optional language home URL.
	 * @return string ID-based content permalink.
	 */
	private function build_content_permalink( string $existing_url, string $slug, int $id, string $language_home_url = '' ): string {
		$relative_id_path = user_trailingslashit( $this->build_id_path( $slug, $id ) );

		if ( '' !== $language_home_url ) {
			$language_url = $this->join_url_path( $language_home_url, $relative_id_path );
			if ( '' !== $language_url ) {
				return $language_url;
			}
		}

		$prefix = $this->get_existing_url_prefix( $existing_url, $slug );
		if ( '' !== $prefix ) {
			return home_url( $prefix . '/' . $relative_id_path );
		}

		return home_url( $relative_id_path );
	}

	/**
	 * Extracts path prefixes from an existing content URL.
	 *
	 * The final path segment is treated as the existing content slug. A
	 * preceding segment matching the content type is also treated as the
	 * canonical content base, while any earlier segments are preserved as a
	 * prefix. Query-style WordPress permalinks use every path segment as the
	 * prefix. The site's own home path is excluded from the result.
	 *
	 * @param string $existing_url Existing content URL.
	 * @param string $slug         Content type slug.
	 * @return string Relative path prefix, or an empty string.
	 */
	private function get_existing_url_prefix( string $existing_url, string $slug ): string {
		$existing_parts = wp_parse_url( $existing_url );
		$home_parts     = wp_parse_url( home_url( '/' ) );

		if ( ! is_array( $existing_parts ) || ! is_array( $home_parts ) ) {
			return '';
		}

		$existing_host = isset( $existing_parts['host'] ) ? strtolower( (string) $existing_parts['host'] ) : '';
		$home_host     = isset( $home_parts['host'] ) ? strtolower( (string) $home_parts['host'] ) : '';
		if ( '' !== $existing_host && '' !== $home_host && $existing_host !== $home_host ) {
			return '';
		}

		$existing_path = trim( isset( $existing_parts['path'] ) ? (string) $existing_parts['path'] : '', '/' );
		$home_path     = trim( isset( $home_parts['path'] ) ? (string) $home_parts['path'] : '', '/' );

		if ( '' !== $home_path ) {
			if ( $existing_path === $home_path ) {
				$existing_path = '';
			} elseif ( 0 === strpos( $existing_path, $home_path . '/' ) ) {
				$existing_path = substr( $existing_path, strlen( $home_path ) + 1 );
			} else {
				return '';
			}
		}

		$segments = array_values(
			array_filter(
				explode( '/', $existing_path ),
				static function ( $segment ): bool {
					return '' !== $segment;
				}
			)
		);

		$query_args = array();
		if ( isset( $existing_parts['query'] ) && '' !== (string) $existing_parts['query'] ) {
			parse_str( (string) $existing_parts['query'], $query_args );
		}

		foreach ( array( 'p', 'page_id', 'post_type', 'name', 'attachment_id', 'cat', 'tag', 'tag_id', 'taxonomy', 'term' ) as $routing_query_var ) {
			if ( array_key_exists( $routing_query_var, $query_args ) ) {
				return implode( '/', $segments );
			}
		}

		if ( count( $segments ) < 2 ) {
			return '';
		}

		array_pop( $segments );

		$slug_segments = array_values(
			array_filter(
				explode( '/', trim( $slug, '/' ) ),
				static function ( $segment ): bool {
					return '' !== $segment;
				}
			)
		);
		$slug_length   = count( $slug_segments );
		$segment_count = count( $segments );

		if ( 0 < $slug_length && $segment_count >= $slug_length && array_slice( $segments, -$slug_length ) === $slug_segments ) {
			$segments = array_slice( $segments, 0, $segment_count - $slug_length );
		}

		return implode( '/', $segments );
	}

	/**
	 * Determines whether settings contain enabled targets.
	 *
	 * @param array $settings Normalized settings.
	 * @return bool Whether at least one target is enabled.
	 */
	private function has_enabled_targets( array $settings ): bool {
		return array() !== $settings['post_types'] || array() !== $settings['taxonomies'];
	}

	/**
	 * Returns the Polylang home URL for a post language.
	 *
	 * @param int $post_id Post ID.
	 * @return string Language home URL, or an empty string.
	 */
	private function get_polylang_home_url_for_post( int $post_id ): string {
		return $this->get_polylang_home_url( 'pll_get_post_language', $post_id );
	}

	/**
	 * Returns the Polylang home URL for a term language.
	 *
	 * @param int $term_id Term ID.
	 * @return string Language home URL, or an empty string.
	 */
	private function get_polylang_home_url_for_term( int $term_id ): string {
		return $this->get_polylang_home_url( 'pll_get_term_language', $term_id );
	}

	/**
	 * Determines whether legacy permalink redirects are enabled.
	 *
	 * @return bool Whether legacy redirects are enabled.
	 */
	private function should_redirect_legacy_requests(): bool {
		$settings = $this->get_settings();

		return ! empty( $settings['redirect_legacy'] );
	}

	/**
	 * Returns the current request URL.
	 *
	 * @return string Current request URL, or an empty string.
	 */
	private function get_current_request_url(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) )
			: '';
		$host        = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) )
			: '';

		if ( '' === $request_uri || '' === $host ) {
			return '';
		}

		return ( is_ssl() ? 'https://' : 'http://' ) . $host . $request_uri;
	}

	/**
	 * Determines whether the current URL differs from a redirect target.
	 *
	 * @param string $current_url Current request URL.
	 * @param string $target_url  Redirect target URL.
	 * @return bool Whether a redirect is required.
	 */
	private function should_redirect_to_target( string $current_url, string $target_url ): bool {
		return $this->normalize_url_for_compare( $current_url ) !== $this->normalize_url_for_compare( $target_url );
	}

	/**
	 * Normalizes a URL for redirect comparison.
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	private function normalize_url_for_compare( string $url ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return untrailingslashit( $url );
		}

		$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path  = isset( $parts['path'] ) ? user_trailingslashit( ltrim( (string) $parts['path'], '/' ) ) : '';
		$query = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . (string) $parts['query'] : '';

		return $host . '|' . $path . $query;
	}

	/**
	 * Appends current query arguments to a redirect target.
	 *
	 * @param string $target_url  Redirect target URL.
	 * @param string $current_url Current request URL.
	 * @return string Target URL with current query arguments.
	 */
	private function append_current_query_args( string $target_url, string $current_url ): string {
		$query = wp_parse_url( $current_url, PHP_URL_QUERY );

		if ( ! is_string( $query ) || '' === $query ) {
			return $target_url;
		}

		parse_str( $query, $query_args );

		if ( array() === $query_args ) {
			return $target_url;
		}

		foreach ( array( 'p', 'page_id', 'post_type', 'name', 'attachment_id', 'cat', 'tag', 'tag_id', 'taxonomy', 'term' ) as $routing_query_var ) {
			unset( $query_args[ $routing_query_var ] );
		}

		if ( array() === $query_args ) {
			return $target_url;
		}

		return add_query_arg( $query_args, $target_url );
	}

	/**
	 * Joins a base URL and a relative path.
	 *
	 * @param string $base_url      Base URL.
	 * @param string $relative_path Relative path.
	 * @return string Joined URL.
	 */
	private function join_url_path( string $base_url, string $relative_path ): string {
		$parts = wp_parse_url( $base_url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$base_path = isset( $parts['path'] ) ? trim( (string) $parts['path'], '/' ) : '';
		$new_path  = trim( $relative_path, '/' );

		if ( '' !== $base_path ) {
			$new_path = $base_path . '/' . $new_path;
		}

		$parts['path'] = '/' . ltrim( user_trailingslashit( $new_path ), '/' );

		return $this->build_url_from_parts( $parts );
	}

	/**
	 * Rebuilds a URL from parsed URL parts.
	 *
	 * @param array $parts Parsed URL parts.
	 * @return string Rebuilt URL.
	 */
	private function build_url_from_parts( array $parts ): string {
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$scheme   = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : ( is_ssl() ? 'https://' : 'http://' );
		$user     = isset( $parts['user'] ) ? (string) $parts['user'] : '';
		$pass     = isset( $parts['pass'] ) ? ':' . (string) $parts['pass'] : '';
		$auth     = '' !== $user ? $user . $pass . '@' : '';
		$host     = (string) $parts['host'];
		$port     = isset( $parts['port'] ) ? ':' . (string) $parts['port'] : '';
		$path     = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		$query    = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . (string) $parts['query'] : '';
		$fragment = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . (string) $parts['fragment'] : '';

		return $scheme . $auth . $host . $port . $path . $query . $fragment;
	}

	/**
	 * Sanitizes enabled content type names.
	 *
	 * @param mixed $items   Submitted item names.
	 * @param array $allowed Optional allowed names.
	 * @return array Sanitized item names.
	 */
	private function sanitize_enabled_items( $items, array $allowed = array() ): array {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$allowed_lookup = array() === $allowed ? array() : array_fill_keys( $allowed, true );
		$sanitized      = array();

		foreach ( $items as $item ) {
			$item = sanitize_key( $item );
			if ( '' === $item ) {
				continue;
			}

			if ( array() !== $allowed_lookup && ! isset( $allowed_lookup[ $item ] ) ) {
				continue;
			}

			$sanitized[] = $item;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Returns a Polylang home URL for a content object's language.
	 *
	 * @param string $language_callback Polylang language callback name.
	 * @param int    $object_id         Content object ID.
	 * @return string Language home URL, or an empty string.
	 */
	private function get_polylang_home_url( string $language_callback, int $object_id ): string {
		if ( ! function_exists( $language_callback ) || ! function_exists( 'pll_home_url' ) ) {
			return '';
		}

		$language = $language_callback( $object_id, 'slug' );

		if ( ! is_string( $language ) || '' === $language ) {
			return '';
		}

		$home_url = pll_home_url( $language );

		return is_string( $home_url ) ? $home_url : '';
	}

	/**
	 * Registers rewrite rules for configured content types.
	 *
	 * @param string $structure  Permalink structure name.
	 * @param array  $post_types Enabled post type names.
	 * @param array  $taxonomies Enabled taxonomy names.
	 * @param bool   $enabled    Whether routing is enabled.
	 */
	private function register_rewrite_rules_for( string $structure, array $post_types, array $taxonomies, bool $enabled ): void {
		$this->remove_plugin_rewrite_rules();

		if ( ! $enabled ) {
			return;
		}

		$separator      = 'hyphen' === $structure ? '-' : '/';
		$prefix_pattern = '^(?:[^/]+/)*';

		foreach ( $post_types as $post_type ) {
			$route_slug = preg_quote( $this->get_post_type_route_slug( $post_type ), '#' );
			add_rewrite_rule(
				$prefix_pattern . $route_slug . $separator . '([0-9]+)/?$',
				'index.php?post_type=' . $post_type . '&p=$matches[1]&' . self::REWRITE_MARKER,
				'top'
			);
		}

		foreach ( $taxonomies as $taxonomy ) {
			$route_slug = preg_quote( $this->get_taxonomy_route_slug( $taxonomy ), '#' );
			add_rewrite_rule(
				$prefix_pattern . $route_slug . $separator . '([0-9]+)/?$',
				'index.php?ptid_taxonomy=' . $taxonomy . '&ptid_term_id=$matches[1]&' . self::REWRITE_MARKER,
				'top'
			);
		}
	}

	/**
	 * Removes rewrite rules previously registered by this plugin.
	 */
	private function remove_plugin_rewrite_rules(): void {
		global $wp_rewrite;

		foreach ( array( 'extra_rules_top', 'extra_rules' ) as $rules_property ) {
			if ( ! isset( $wp_rewrite->{$rules_property} ) || ! is_array( $wp_rewrite->{$rules_property} ) ) {
				continue;
			}

			foreach ( $wp_rewrite->{$rules_property} as $regex => $query ) {
				if ( $this->is_plugin_rewrite_query( $query ) ) {
					unset( $wp_rewrite->{$rules_property}[ $regex ] );
				}
			}
		}
	}

	/**
	 * Determines whether persisted rewrite rules differ from current plugin rules.
	 *
	 * @return bool Whether a one-time flush is needed.
	 */
	private function has_stale_persisted_rewrite_rules(): bool {
		$persisted_rules = get_option( 'rewrite_rules', array() );
		if ( ! is_array( $persisted_rules ) ) {
			return true;
		}

		$current_rules = $this->get_current_plugin_rewrite_rules();
		foreach ( $current_rules as $regex => $query ) {
			if ( ! isset( $persisted_rules[ $regex ] ) || $persisted_rules[ $regex ] !== $query ) {
				return true;
			}
		}

		foreach ( $persisted_rules as $regex => $query ) {
			if ( $this->is_plugin_rewrite_query( $query ) && ! isset( $current_rules[ $regex ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the plugin's current in-memory rewrite rules.
	 *
	 * @return array Current plugin rewrite rules keyed by regex.
	 */
	private function get_current_plugin_rewrite_rules(): array {
		global $wp_rewrite;

		$rules = array();
		foreach ( array( 'extra_rules_top', 'extra_rules' ) as $rules_property ) {
			if ( ! isset( $wp_rewrite->{$rules_property} ) || ! is_array( $wp_rewrite->{$rules_property} ) ) {
				continue;
			}

			foreach ( $wp_rewrite->{$rules_property} as $regex => $query ) {
				if ( $this->is_plugin_rewrite_query( $query ) ) {
					$rules[ $regex ] = $query;
				}
			}
		}

		return $rules;
	}

	/**
	 * Determines whether a query belongs to this plugin's rewrite rules.
	 *
	 * @param mixed $query Rewrite query value.
	 * @return bool Whether the query belongs to this plugin.
	 */
	private function is_plugin_rewrite_query( $query ): bool {
		if ( ! is_string( $query ) ) {
			return false;
		}

		return false !== strpos( $query, '&' . self::REWRITE_MARKER )
			&& (
				0 === strpos( $query, 'index.php?ptid_taxonomy=' )
				|| 0 === strpos( $query, 'index.php?post_type=' )
			);
	}

	/**
	 * Stores normalized settings in request-local caches.
	 *
	 * @param array $settings Normalized settings.
	 * @return array Cached settings.
	 */
	private function prime_settings_cache( array $settings ): array {
		$this->settings_cache            = wp_parse_args( $settings, self::default_settings() );
		$this->enabled_post_types_cache  = null;
		$this->enabled_taxonomies_cache  = null;
		$this->permalink_structure_cache = null;

		return $this->settings_cache;
	}
}

PTID_Permalink_Plugin::bootstrap();
register_activation_hook( __FILE__, array( 'PTID_Permalink_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PTID_Permalink_Plugin', 'deactivate' ) );
