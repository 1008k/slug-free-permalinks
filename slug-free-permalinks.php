<?php
/**
 * Plugin Name: Slug-Free Permalinks
 * Plugin URI: https://happas.jp/en/slug-free-permalinks/
 * Description: Use ID-based permalinks for selected post types and taxonomies without managing slugs.
 * Version: 2.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.2
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

	private const PLUGIN_VERSION                = '2.0.0';
	private const OPTION_NAME                   = 'ptid_permalink_settings';
	private const MENU_SLUG                     = 'ptid-permalink-settings';
	private const LEGACY_META_KEY               = 'legacy_id';
	private const REWRITE_MARKER                = 'ptid_route=1';
	private const REWRITE_MARKER_VERSION        = '2';
	private const REWRITE_MARKER_VERSION_OPTION = 'ptid_rewrite_marker_version';
	private const REWRITE_SIGNATURE_OPTION      = 'ptid_rewrite_signature';
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
	 * Whether the initial rewrite-rule registration has completed.
	 *
	 * @var bool
	 */
	private bool $rewrite_rules_registered = false;

	/**
	 * Cached post type route slugs for the current request.
	 *
	 * @var array
	 */
	private array $post_type_route_slug_cache = array();

	/**
	 * Cached taxonomy route slugs for the current request.
	 *
	 * @var array
	 */
	private array $taxonomy_route_slug_cache = array();

	/**
	 * Registers plugin hooks.
	 */
	public static function bootstrap(): void {
		$instance = new self();
		add_action( 'init', array( $instance, 'register_legacy_rewrite_tag' ), 9 );
		add_action( 'init', array( $instance, 'register_rewrite_rules' ), 99 );
		add_action( 'registered_post_type', array( $instance, 'refresh_rewrite_rules_after_registration' ), 10, 0 );
		add_action( 'registered_taxonomy', array( $instance, 'refresh_rewrite_rules_after_registration' ), 10, 0 );
		add_action( 'wp_loaded', array( $instance, 'flush_pending_rewrite_rules' ), 999 );
		add_filter( 'post_link', array( $instance, 'filter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( $instance, 'filter_permalink' ), 10, 2 );
		add_filter( 'term_link', array( $instance, 'filter_term_link' ), 10, 3 );
		add_filter( 'query_vars', array( $instance, 'register_query_vars' ) );
		add_action( 'parse_request', array( $instance, 'resolve_legacy_request' ), 9 );
		add_action( 'parse_request', array( $instance, 'resolve_term_request' ) );
		add_action( 'template_redirect', array( $instance, 'redirect_legacy_permalink' ) );
		add_action( 'admin_init', array( $instance, 'register_settings' ) );
		add_action( 'admin_init', array( $instance, 'normalize_stored_settings' ) );
		add_action( 'admin_menu', array( $instance, 'register_settings_page' ) );
		add_action( 'admin_post_ptid_migrate_post_structure', array( $instance, 'migrate_post_structure' ) );
		add_action( 'admin_post_ptid_save_post_structure', array( $instance, 'save_post_structure' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_admin_assets' ) );
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
		$plugin->persist_rewrite_state();
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

		$settings = $this->get_settings();
		if ( ! $this->is_feature_enabled() && empty( $settings['use_legacy_id'] ) ) {
			return $post_link;
		}

		$post_context = $this->get_post_permalink_context( $post );
		if ( null === $post_context ) {
			return $post_link;
		}

		if ( ! in_array( $post_context['post_status'], array( 'publish', 'private' ), true ) ) {
			return $post_link;
		}

		if ( 'post' === $post_context['post_type'] && ! empty( $settings['use_legacy_id'] ) && false !== strpos( $post_link, '%legacy_id%' ) ) {
			return str_replace( '%legacy_id%', $this->get_post_identifier( $post ), $post_link );
		}

		if ( ! in_array( $post_context['post_type'], $this->get_enabled_post_types(), true ) ) {
			return $post_link;
		}

		$post_type_slug = $this->get_post_type_route_slug( $post_context['post_type'] );
		$identifier     = ! empty( $settings['use_legacy_id'] ) && 'post' !== $post_context['post_type']
			? $this->get_post_identifier( $post )
			: (string) $post_context['ID'];

		return $this->build_content_permalink( $post_type_slug, $identifier, $this->get_polylang_home_url_for_post( $post_context['ID'] ) );
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
			$taxonomy_slug,
			$term->term_id,
			$this->get_polylang_home_url_for_term( $term->term_id )
		);
	}

	/**
	 * Registers the custom tag only when the migration identifier is enabled.
	 */
	public function register_legacy_rewrite_tag(): void {
		if ( ! empty( $this->get_settings()['use_legacy_id'] ) ) {
			add_rewrite_tag( '%legacy_id%', '([^/]+)', 'ptid_legacy_id=' );
		}
	}

	/**
	 * Returns a safe URL identifier from existing post metadata or its fallback.
	 *
	 * @param WP_Post $post Post.
	 * @return string Identifier.
	 */
	private function get_post_identifier( WP_Post $post ): string {
		$value = get_post_meta( $post->ID, self::LEGACY_META_KEY, true );
		if ( is_string( $value ) && '' !== $value ) {
			// Keep the original value unchanged; never silently normalize two IDs together.
			if ( 1 === preg_match( '/\\A[A-Za-z0-9_-]+\\z/D', $value ) ) {
				return $value;
			}
			return (string) $post->ID;
		}

		$settings = $this->get_settings();
		$slug     = (string) $post->post_name;
		return 'slug' === $settings['legacy_fallback'] && 1 === preg_match( '/\\A[A-Za-z0-9_-]+\\z/D', $slug )
			? $slug
			: (string) $post->ID;
	}

	/**
	 * Resolves an identifier within one post type; ambiguous values fail closed.
	 *
	 * @param string $identifier URL segment.
	 * @param string $post_type  Target post type.
	 * @return int Resolved post ID or zero for ambiguous/missing values.
	 */
	private function lookup_legacy_post( string $identifier, string $post_type ): int {
		if ( '' === $identifier || 1 !== preg_match( '/\\A[A-Za-z0-9_-]+\\z/D', $identifier ) ) {
			return 0;
		}

		$settings = $this->get_settings();
		$matched  = array();
		$posts    = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'meta_key'         => self::LEGACY_META_KEY,
				'meta_value'       => $identifier,
				'posts_per_page'   => 20,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $posts as $post_id ) {
			if ( get_post_meta( (int) $post_id, self::LEGACY_META_KEY, true ) === $identifier ) {
				$matched[ (int) $post_id ] = true;
			}
		}

		$fallbacks = array();
		if ( 'slug' === $settings['legacy_fallback'] ) {
			$fallbacks[] = get_page_by_path( $identifier, OBJECT, $post_type );
		}
		if ( ctype_digit( $identifier ) ) {
			$fallbacks[] = get_post( (int) $identifier );
		}
		foreach ( $fallbacks as $fallback ) {
			if ( $fallback instanceof WP_Post && $post_type === $fallback->post_type ) {
				$raw_value = get_post_meta( $fallback->ID, self::LEGACY_META_KEY, true );
				if ( ( '' === $raw_value || ! is_string( $raw_value ) || 1 !== preg_match( '/\\A[A-Za-z0-9_-]+\\z/D', $raw_value ) ) && $identifier === $this->get_post_identifier( $fallback ) ) {
					$matched[ $fallback->ID ] = true;
				}
			}
		}

		if ( 1 !== count( $matched ) ) {
			return 0;
		}

		$id   = (int) array_key_first( $matched );
		$post = get_post( $id );
		return $post instanceof WP_Post && in_array( $post->post_status, array( 'publish', 'private' ), true ) ? $id : 0;
	}

	/**
	 * Resolves legacy identifiers from native post and plugin CPT rewrite rules.
	 *
	 * @param WP $wp WordPress request object.
	 */
	public function resolve_legacy_request( WP $wp ): void {
		if ( empty( $this->get_settings()['use_legacy_id'] ) ) {
			return;
		}

		$is_native = isset( $wp->query_vars['ptid_legacy_id'] );
		$is_custom = isset( $wp->query_vars['ptid_identifier'] ) && isset( $wp->query_vars['ptid_route'] );
		if ( ! $is_native && ! $is_custom ) {
			return;
		}

		$post_type = $is_custom ? sanitize_key( (string) ( $wp->query_vars['post_type'] ?? '' ) ) : 'post';
		if ( $is_custom && ! in_array( $post_type, $this->get_enabled_post_types(), true ) ) {
			return;
		}

		$identifier = (string) ( $is_custom ? $wp->query_vars['ptid_identifier'] : $wp->query_vars['ptid_legacy_id'] );
		$post_id    = $this->lookup_legacy_post( $identifier, $post_type );

		// Never let WordPress fall through to a different post on a conflicting route.
		$wp->query_vars['post_type'] = $post_type;
		unset( $wp->query_vars['name'], $wp->query_vars['year'], $wp->query_vars['monthnum'], $wp->query_vars['day'], $wp->query_vars['category_name'] );
		if ( $post_id ) {
			$wp->query_vars['p'] = $post_id;
		} else {
			$wp->query_vars['p']        = 0;
			$wp->query_vars['post__in'] = array( 0 );
		}
	}

	/**
	 * Registers ID-based rewrite rules for enabled content types.
	 */
	public function register_rewrite_rules(): void {
		$structure  = $this->get_permalink_structure();
		$post_types = $this->get_enabled_post_types();
		$taxonomies = $this->get_enabled_taxonomies();
		$enabled    = array() !== $post_types || array() !== $taxonomies;
		$settings   = $this->get_settings();
		$signature  = $this->build_rewrite_signature( $structure, $post_types, $taxonomies, $enabled, ! empty( $settings['redirect_legacy'] ) );

		$this->register_rewrite_rules_for( $structure, $post_types, $taxonomies, $enabled, ! empty( $settings['redirect_legacy'] ) );
		$this->rewrite_rules_registered = true;

		if (
			(string) get_option( self::REWRITE_SIGNATURE_OPTION, '' ) !== $signature
			|| self::REWRITE_MARKER_VERSION !== (string) get_option( self::REWRITE_MARKER_VERSION_OPTION, '' )
		) {
			$this->rewrite_rules_flush_pending = true;
		}
	}

	/**
	 * Refreshes rewrite rules after a post type or taxonomy is registered.
	 */
	public function refresh_rewrite_rules_after_registration(): void {
		$this->settings_cache             = null;
		$this->enabled_post_types_cache   = null;
		$this->enabled_taxonomies_cache   = null;
		$this->permalink_structure_cache  = null;
		$this->post_type_route_slug_cache = array();
		$this->taxonomy_route_slug_cache  = array();

		if ( ! $this->rewrite_rules_registered ) {
			return;
		}

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
		$this->persist_rewrite_state();
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
		$query_vars[] = 'ptid_identifier';
		$query_vars[] = 'ptid_legacy_id';

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
				$this->has_enabled_targets( $normalized ),
				$normalized['redirect_legacy']
			);
			flush_rewrite_rules();
			$this->prime_settings_cache( $normalized );
			$this->persist_rewrite_state();
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
	 * Moves regular post URL handling to the WordPress permalink setting.
	 */
	public function migrate_post_structure(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change permalink settings.', 'slug-free-permalinks' ) );
		}

		check_admin_referer( 'ptid_migrate_post_structure' );

		$settings           = $this->get_settings();
		$expected_structure = '/' . user_trailingslashit(
			'hyphen' === $settings['structure'] ? 'post-%post_id%' : 'post/%post_id%',
			'single'
		);
		$submitted_structure = isset( $_POST['ptid_post_structure'] ) && is_string( $_POST['ptid_post_structure'] )
			? sanitize_text_field( wp_unslash( $_POST['ptid_post_structure'] ) )
			: '';

		if ( $expected_structure !== $submitted_structure ) {
			wp_die( esc_html__( 'To preserve existing post URLs, migrate using the current legacy structure before editing it.', 'slug-free-permalinks' ) );
		}

		$this->apply_post_structure_migration();

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Applies the explicitly requested migration while keeping custom targets.
	 */
	private function apply_post_structure_migration(): void {
		$settings = $this->get_settings();
		if ( ! in_array( 'post', $settings['post_types'], true ) ) {
			return;
		}

		$structure = '/' . user_trailingslashit(
			'hyphen' === $settings['structure'] ? 'post-%post_id%' : 'post/%post_id%',
			'single'
		);

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( $structure );

		$settings['post_types'] = array_values( array_diff( $settings['post_types'], array( 'post' ) ) );
		update_option( self::OPTION_NAME, $settings );
		$this->prime_settings_cache( $settings );
		// Refresh after a fresh request registers CPT rewrite rules with the new front.
		delete_option( self::REWRITE_SIGNATURE_OPTION );
	}

	/**
	 * Saves the standard WordPress permalink structure when regular posts
	 * are no longer routed by this plugin.
	 */
	public function save_post_structure(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change permalink settings.', 'slug-free-permalinks' ) );
		}

		check_admin_referer( 'ptid_save_post_structure' );

		if ( in_array( 'post', $this->get_settings()['post_types'], true ) ) {
			wp_die( esc_html__( 'Migrate legacy regular posts before changing the WordPress permalink structure.', 'slug-free-permalinks' ) );
		}

		$structure = isset( $_POST['ptid_post_structure'] ) && is_string( $_POST['ptid_post_structure'] )
			? sanitize_text_field( wp_unslash( $_POST['ptid_post_structure'] ) )
			: '';

		if ( '' !== $structure && ( '/' !== substr( $structure, 0, 1 ) || false !== strpbrk( $structure, '?#' ) ) ) {
			wp_die( esc_html__( 'Enter a valid WordPress permalink structure starting with a slash.', 'slug-free-permalinks' ) );
		}

		if ( false !== strpos( $structure, '%legacy_id%' ) && empty( $this->get_settings()['use_legacy_id'] ) ) {
			wp_die( esc_html__( 'Enable legacy identifiers before using the %legacy_id% tag.', 'slug-free-permalinks' ) );
		}

		$this->apply_native_post_structure( $structure );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Applies a WordPress-native post permalink structure without creating
	 * additional plugin settings.
	 *
	 * @param string $structure Native permalink structure, or empty for plain.
	 */
	private function apply_native_post_structure( string $structure ): void {
		global $wp_rewrite;

		if ( (string) get_option( 'permalink_structure', '' ) === $structure ) {
			return;
		}

		$wp_rewrite->set_permalink_structure( $structure );
		// Rebuild after CPT rewrite registrations have picked up the new front.
		delete_option( self::REWRITE_SIGNATURE_OPTION );
	}

	/**
	 * Enqueues assets only on the plugin settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'slug-free-permalinks-admin',
			plugins_url( 'assets/admin.css', __FILE__ ),
			array(),
			self::PLUGIN_VERSION
		);

		wp_enqueue_script(
			'slug-free-permalinks-admin',
			plugins_url( 'assets/admin.js', __FILE__ ),
			array(),
			self::PLUGIN_VERSION,
			true
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

		$settings = array(
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
			'use_legacy_id'   => ! empty( $input['use_legacy_id'] ),
			'legacy_fallback' => 'slug' === ( $input['legacy_fallback'] ?? '' ) ? 'slug' : 'id',
		);
		if ( in_array( 'post', $previous['post_types'], true ) ) {
			// Native custom tags require a non-plain structure and explicit v1 migration.
			$settings['use_legacy_id'] = false;
		}
		// Only the explicit migration may remove legacy post routing.
		if ( in_array( 'post', $previous['post_types'], true ) ) {
			$settings['structure'] = $previous['structure'];
			if ( ! in_array( 'post', $settings['post_types'], true ) ) {
				array_unshift( $settings['post_types'], 'post' );
			}
		} else {
			$settings['post_types'] = array_diff( $settings['post_types'], array( 'post' ) );
		}
		$settings['post_types'] = array_values( array_unique( $settings['post_types'] ) );

		$conflicts = $this->get_route_slug_conflicts( $settings['post_types'], $settings['taxonomies'] );

		if ( array() !== $conflicts ) {
			$this->add_route_slug_conflict_errors( $conflicts );
			return $previous;
		}

		if ( false !== strpos( (string) get_option( 'permalink_structure', '' ), '%legacy_id%' ) && ! $settings['use_legacy_id'] ) {
			add_settings_error( self::OPTION_NAME, 'legacy_tag_in_use', __( 'Remove %legacy_id% from the WordPress permalink structure before disabling legacy identifiers.', 'slug-free-permalinks' ) );
			return $previous;
		}

		$settings_changed = $previous !== $settings;
		if ( $settings_changed ) {
			$this->prime_settings_cache( $settings );
			if ( $settings['use_legacy_id'] ) {
				$this->register_legacy_rewrite_tag();
			}
			$this->register_rewrite_rules_for(
				$settings['structure'],
				$settings['post_types'],
				$settings['taxonomies'],
				$this->has_enabled_targets( $settings ),
				$settings['redirect_legacy']
			);
			flush_rewrite_rules();
		}

		$this->prime_settings_cache( $settings );

		if ( $settings_changed ) {
			$this->persist_rewrite_state();
		}

		return $settings;
	}

	/**
	 * Renders the plugin settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings     = $this->get_settings();
		$post_types   = $this->get_available_post_types();
		$taxonomies   = $this->get_available_taxonomies();
		$post_enabled = in_array( 'post', $settings['post_types'], true );
		unset( $post_types['post'] );
		$current_wp_structure   = (string) get_option( 'permalink_structure', '' );
		$wp_permalink_structure = '/' . user_trailingslashit(
			'hyphen' === $settings['structure'] ? 'post-%post_id%' : 'post/%post_id%',
			'single'
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Slug-Free Permalinks', 'slug-free-permalinks' ); ?></h1>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<div class="ptid-admin-layout">
				<div class="ptid-admin-main">
					<section class="ptid-settings-section" aria-labelledby="ptid-regular-posts-title">
						<h2 id="ptid-regular-posts-title"><?php echo esc_html__( 'Regular posts', 'slug-free-permalinks' ); ?></h2>
						<?php if ( $post_enabled ) : ?>
							<div class="notice notice-warning inline">
								<p><?php echo esc_html__( 'Regular posts still use the legacy plugin URL format. Migrate them explicitly to use WordPress settings.', 'slug-free-permalinks' ); ?></p>
							</div>
						<?php endif; ?>
						<p><?php echo esc_html__( 'Choose a preset or insert WordPress permalink tags, then edit the resulting structure directly.', 'slug-free-permalinks' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ptid-native-permalink-form">
							<input type="hidden" name="action" value="<?php echo esc_attr( $post_enabled ? 'ptid_migrate_post_structure' : 'ptid_save_post_structure' ); ?>" />
							<?php wp_nonce_field( $post_enabled ? 'ptid_migrate_post_structure' : 'ptid_save_post_structure' ); ?>
							<div class="ptid-permalink-presets" role="group" aria-label="<?php echo esc_attr__( 'Permalink presets', 'slug-free-permalinks' ); ?>">
							<?php
							$presets = array(
								'/%post_id%/'                   => __( 'Post ID', 'slug-free-permalinks' ),
								'/post/%post_id%/'              => __( 'Post / ID', 'slug-free-permalinks' ),
								'/%year%/%monthnum%/%post_id%/' => __( 'Year / Month / ID', 'slug-free-permalinks' ),
								'/%category%/%post_id%/'        => __( 'Category / ID', 'slug-free-permalinks' ),
							);
							foreach ( $presets as $preset => $label ) :
								?>
								<button type="button" class="button" data-ptid-preset="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
						<p>
							<label for="ptid-post-structure"><strong><?php echo esc_html__( 'WordPress custom structure', 'slug-free-permalinks' ); ?></strong></label>
							<input type="text" id="ptid-post-structure" name="ptid_post_structure" class="large-text code" value="<?php echo esc_attr( $post_enabled ? $wp_permalink_structure : $current_wp_structure ); ?>" spellcheck="false" autocomplete="off" />
						</p>
						<div class="ptid-permalink-tags" role="group" aria-label="<?php echo esc_attr__( 'Insert permalink tags', 'slug-free-permalinks' ); ?>">
							<?php foreach ( ! empty( $settings['use_legacy_id'] ) ? array( '%post_id%', '%postname%', '%legacy_id%', '%category%', '%year%', '%monthnum%', '%day%' ) : array( '%post_id%', '%postname%', '%category%', '%year%', '%monthnum%', '%day%' ) as $tag ) : ?>
								<button type="button" class="button button-small" data-ptid-tag="<?php echo esc_attr( $tag ); ?>"><code><?php echo esc_html( $tag ); ?></code></button>
							<?php endforeach; ?>
						</div>
						<?php if ( $post_enabled ) : ?>
							<p class="description"><?php echo esc_html__( 'Migration preserves the existing regular post URLs. The structure must match the legacy format shown when this page loaded. After migration, you can customize it.', 'slug-free-permalinks' ); ?></p>
							<p class="description"><?php echo esc_html__( 'Changing WordPress permalinks can affect custom post type URLs. Back up the site and check for URL conflicts first.', 'slug-free-permalinks' ); ?></p>
							<?php submit_button( __( 'Migrate regular posts to WordPress permalinks', 'slug-free-permalinks' ), 'secondary', 'submit', false ); ?>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'Saving changes the WordPress permalink structure for regular posts and may also affect custom post type URLs.', 'slug-free-permalinks' ); ?></p>
							<?php submit_button( __( 'Save WordPress permalinks', 'slug-free-permalinks' ), 'primary', 'submit', false ); ?>
						<?php endif; ?>
						</form>
					</section>
					<section class="ptid-settings-section" aria-labelledby="ptid-custom-targets-title">
						<h2 id="ptid-custom-targets-title"><?php echo esc_html__( 'Custom post types and taxonomies', 'slug-free-permalinks' ); ?></h2>
						<p><?php echo esc_html__( 'Select content types for plugin-managed ID URLs. These settings do not change regular post permalinks.', 'slug-free-permalinks' ); ?></p>
						<form action="options.php" method="post">
							<?php settings_fields( 'ptid_permalink_settings_group' ); ?>
							<?php if ( $post_enabled ) : ?>
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]" value="post" />
							<?php endif; ?>
						<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Permalink format', 'slug-free-permalinks' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[structure]" <?php disabled( $post_enabled ); ?>>
									<option value="slash" <?php selected( $settings['structure'], 'slash' ); ?>>
									<?php echo esc_html( '/{type}/123/' ); ?>
									</option>
									<option value="hyphen" <?php selected( $settings['structure'], 'hyphen' ); ?>>
									<?php echo esc_html( '/{type}-123/' ); ?>
									</option>
								</select>
								<?php if ( $post_enabled ) : ?>
									<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[structure]" value="<?php echo esc_attr( $settings['structure'] ); ?>" />
									<p class="description"><?php echo esc_html__( 'Migrate legacy regular posts before changing the ID URL format.', 'slug-free-permalinks' ); ?></p>
								<?php endif; ?>
								<p class="description">
									<?php echo esc_html__( '{type} is replaced with the post type or taxonomy rewrite slug.', 'slug-free-permalinks' ); ?>
									<?php echo esc_html__( ' Example: /book/123/ or /book-123/.', 'slug-free-permalinks' ); ?>
								</p>
							</td>
						</tr>
						<?php if ( ! empty( $post_types ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Target post types', 'slug-free-permalinks' ); ?></th>
							<td>
								<fieldset class="ptid-target-list">
								<?php foreach ( $post_types as $post_type => $object ) : ?>
										<label class="ptid-target-list__item">
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
						<?php endif; ?>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Target taxonomies', 'slug-free-permalinks' ); ?></th>
							<td>
								<fieldset class="ptid-target-list">
								<?php foreach ( $taxonomies as $taxonomy => $object ) : ?>
										<label class="ptid-target-list__item">
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
							<th scope="row"><?php echo esc_html__( 'Use legacy identifiers', 'slug-free-permalinks' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[use_legacy_id]" value="1" <?php checked( ! empty( $settings['use_legacy_id'] ) ); ?> <?php disabled( $post_enabled ); ?> />
									<?php echo esc_html__( 'Use the legacy_id custom field for post URLs. Posts without a value use the selected fallback.', 'slug-free-permalinks' ); ?>
								</label>
								<?php if ( $post_enabled ) : ?>
									<p class="description"><?php echo esc_html__( 'Migrate regular posts before enabling legacy identifiers.', 'slug-free-permalinks' ); ?></p>
								<?php endif; ?>
								<details>
									<summary><?php echo esc_html__( 'Fallback identifier', 'slug-free-permalinks' ); ?></summary>
									<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[legacy_fallback]">
										<option value="id" <?php selected( $settings['legacy_fallback'], 'id' ); ?>><?php echo esc_html__( 'Post ID', 'slug-free-permalinks' ); ?></option>
										<option value="slug" <?php selected( $settings['legacy_fallback'], 'slug' ); ?>><?php echo esc_html__( 'Post slug', 'slug-free-permalinks' ); ?></option>
									</select>
								</details>
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
					</section>
				</div>

				<aside class="ptid-admin-sidebar" aria-label="<?php echo esc_attr__( 'Helpful links', 'slug-free-permalinks' ); ?>">
					<?php echo $this->render_helpful_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped in render_helpful_links(). ?>
				</aside>
			</div>
		</div>
			<?php
	}

	/**
	 * Renders helpful links for the plugin settings screen.
	 *
	 * @return string Escaped sidebar markup.
	 */
	private function render_helpful_links(): string {
		$locale            = get_user_locale();
		$official_site_url = 0 === strpos( $locale, 'ja' )
			? 'https://happas.jp/slug-free-permalinks/'
			: 'https://happas.jp/en/slug-free-permalinks/';

		$links = array(
			array(
				'title'       => __( 'Official site', 'slug-free-permalinks' ),
				'description' => __( 'See the feature overview, installation steps, FAQ, and recent changes.', 'slug-free-permalinks' ),
				'url'         => $official_site_url,
				'label'       => __( 'Visit official site', 'slug-free-permalinks' ),
			),
			array(
				'title'       => __( 'Introduction article', 'slug-free-permalinks' ),
				'description' => __( 'Read why the plugin uses ID-based URLs and how the design works.', 'slug-free-permalinks' ),
				'url'         => 'https://happas.jp/blog/post/84/',
				'label'       => __( 'Read article', 'slug-free-permalinks' ),
			),
			array(
				'title'       => __( 'Share feedback', 'slug-free-permalinks' ),
				'description' => __( 'If the plugin helps your site, feedback on WordPress.org is appreciated.', 'slug-free-permalinks' ),
				'url'         => 'https://wordpress.org/support/plugin/slug-free-permalinks/reviews/',
				'label'       => __( 'Open reviews', 'slug-free-permalinks' ),
			),
		);

		$markup = '<section class="ptid-side-card">';

		foreach ( $links as $index => $link ) {
			if ( 0 < $index ) {
				$markup .= '<hr class="ptid-side-card__divider">';
			}

			$markup .= sprintf(
				'<div class="ptid-side-card__section"><h2>%1$s</h2><p>%2$s</p><p><a class="button button-secondary" href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a></p></div>',
				esc_html( $link['title'] ),
				esc_html( $link['description'] ),
				esc_url( $link['url'] ),
				esc_html( $link['label'] )
			);
		}

		$markup .= '</section>';

		return $markup;
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
			'use_legacy_id'   => false,
			'legacy_fallback' => 'id',
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
		$this->enabled_post_types_cache = $settings['post_types'];

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
		$this->enabled_taxonomies_cache = $settings['taxonomies'];

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
		if ( isset( $this->post_type_route_slug_cache[ $post_type ] ) ) {
			return $this->post_type_route_slug_cache[ $post_type ];
		}

		$post_type_object = get_post_type_object( $post_type );
		$route_slug       = $post_type;

		if ( $post_type_object && is_array( $post_type_object->rewrite ) && ! empty( $post_type_object->rewrite['slug'] ) ) {
			$registered_slug = trim( (string) $post_type_object->rewrite['slug'], '/' );
			if ( '' !== $registered_slug ) {
				$route_slug = $registered_slug;
			}
		}

		$this->post_type_route_slug_cache[ $post_type ] = $route_slug;

		return $route_slug;
	}

	/**
	 * Returns the rewrite slug used for a taxonomy's public URL.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string Route slug.
	 */
	private function get_taxonomy_route_slug( string $taxonomy ): string {
		if ( isset( $this->taxonomy_route_slug_cache[ $taxonomy ] ) ) {
			return $this->taxonomy_route_slug_cache[ $taxonomy ];
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		$route_slug      = $taxonomy;

		if ( $taxonomy_object && is_array( $taxonomy_object->rewrite ) && ! empty( $taxonomy_object->rewrite['slug'] ) ) {
			$registered_slug = trim( (string) $taxonomy_object->rewrite['slug'], '/' );
			if ( '' !== $registered_slug ) {
				$route_slug = $registered_slug;
			}
		}

		$this->taxonomy_route_slug_cache[ $taxonomy ] = $route_slug;

		return $route_slug;
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
			'use_legacy_id'   => ! empty( $settings['use_legacy_id'] ),
			'legacy_fallback' => 'slug' === ( $settings['legacy_fallback'] ?? '' ) ? 'slug' : 'id',
		);
	}

	/**
	 * Builds permalink context for a valid post.
	 *
	 * @param WP_Post $post Post object.
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
			'post_name'   => isset( $post->post_name ) ? (string) $post->post_name : '',
		);
	}


	/**
	 * Builds an ID-based path segment.
	 *
	 * @param string $slug Content type slug.
	 * @param string $id   Content identifier.
	 * @return string ID-based relative path.
	 */
	private function build_id_path( string $slug, string $id ): string {

		if ( 'hyphen' === $this->get_permalink_structure() ) {
			return $slug . '-' . $id;
		}

		return $slug . '/' . $id;
	}

	/**
	 * Builds an ID-based permalink from the site or language home URL.
	 *
	 * @param string $slug              Content type slug.
	 * @param string $id                Content identifier.
	 * @param string $language_home_url Optional language home URL.
	 * @return string ID-based content permalink.
	 */
	private function build_content_permalink( string $slug, string $id, string $language_home_url = '' ): string {
		$relative_id_path = user_trailingslashit( $this->build_id_path( $slug, $id ) );

		if ( '' !== $language_home_url ) {
			$language_url = $this->join_url_path( $language_home_url, $relative_id_path );
			if ( '' !== $language_url ) {
				return $language_url;
			}
		}

		return home_url( $relative_id_path );
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
	 * Returns path prefixes that Polylang exposes through language home URLs.
	 *
	 * Query-based and domain-based language modes do not need an additional
	 * rewrite path unless the language home URL also contains a path prefix.
	 *
	 * @return array Language path prefixes relative to the WordPress home path.
	 */
	private function get_polylang_rewrite_prefixes(): array {
		if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_home_url' ) ) {
			return array();
		}

		$languages  = pll_languages_list( array( 'fields' => 'slug' ) );
		$home_parts = wp_parse_url( (string) get_option( 'home' ) );

		if ( ! is_array( $languages ) || ! is_array( $home_parts ) ) {
			return array();
		}

		$home_path = trim( isset( $home_parts['path'] ) ? (string) $home_parts['path'] : '', '/' );
		$prefixes  = array();

		foreach ( $languages as $language ) {
			if ( ! is_string( $language ) || '' === $language ) {
				continue;
			}

			$language_home_url = pll_home_url( $language );
			if ( ! is_string( $language_home_url ) || '' === $language_home_url ) {
				continue;
			}

			$language_parts = wp_parse_url( $language_home_url );
			if ( ! is_array( $language_parts ) ) {
				continue;
			}

			$language_path = trim( isset( $language_parts['path'] ) ? (string) $language_parts['path'] : '', '/' );

			if ( $language_path === $home_path ) {
				continue;
			}

			if ( '' !== $home_path ) {
				if ( 0 !== strpos( $language_path, $home_path . '/' ) ) {
					continue;
				}

				$language_path = substr( $language_path, strlen( $home_path ) + 1 );
			}

			if ( '' !== $language_path ) {
				$prefixes[] = $language_path;
			}
		}

		$prefixes = array_values( array_unique( $prefixes ) );
		sort( $prefixes );

		return $prefixes;
	}

	/**
	 * Returns the path prefixes accepted by this plugin's rewrite rules.
	 *
	 * @return array Relative path prefixes. The empty string is the base route.
	 */
	private function get_rewrite_path_prefixes(): array {
		return array_merge( array( '' ), $this->get_polylang_rewrite_prefixes() );
	}

	/**
	 * Registers rewrite rules for configured content types.
	 *
	 * The non-selected ID format is also registered while legacy redirects
	 * are enabled, so requests in the previous format can be redirected
	 * to the canonical URL.
	 *
	 * @param string $structure              Permalink structure name.
	 * @param array  $post_types             Enabled post type names.
	 * @param array  $taxonomies             Enabled taxonomy names.
	 * @param bool   $enabled                Whether routing is enabled.
	 * @param bool   $redirect_legacy        Whether legacy redirects are enabled.
	 */
	private function register_rewrite_rules_for( string $structure, array $post_types, array $taxonomies, bool $enabled, bool $redirect_legacy = false ): void {
		$this->remove_plugin_rewrite_rules();

		if ( ! $enabled ) {
			return;
		}

		$legacy_enabled    = ! empty( $this->get_settings()['use_legacy_id'] );
		$current_separator = 'hyphen' === $structure ? '-' : '/';
		$legacy_separator  = 'hyphen' === $structure ? '/' : '-';
		foreach ( $this->get_rewrite_path_prefixes() as $path_prefix ) {
			$prefix_pattern = '^';
			if ( '' !== $path_prefix ) {
				$prefix_pattern .= preg_quote( trim( $path_prefix, '/' ), '#' ) . '/';
			}

			foreach ( $post_types as $post_type ) {
				$route_slug = preg_quote( $this->get_post_type_route_slug( $post_type ), '#' );
				$use_legacy = $legacy_enabled && 'post' !== $post_type;
				$query      = 'index.php?post_type=' . $post_type . ( $use_legacy ? '&ptid_identifier=$matches[1]&' : '&p=$matches[1]&' ) . self::REWRITE_MARKER;
				$pattern    = $use_legacy ? '([A-Za-z0-9_-]+)' : '([0-9]+)';

				add_rewrite_rule( $prefix_pattern . $route_slug . $current_separator . $pattern . '/?$', $query, 'top' );

				if ( $redirect_legacy ) {
					add_rewrite_rule( $prefix_pattern . $route_slug . $legacy_separator . $pattern . '/?$', $query, 'top' );
				}
			}

			foreach ( $taxonomies as $taxonomy ) {
				$route_slug = preg_quote( $this->get_taxonomy_route_slug( $taxonomy ), '#' );
				$query      = 'index.php?ptid_taxonomy=' . $taxonomy . '&ptid_term_id=$matches[1]&' . self::REWRITE_MARKER;

				add_rewrite_rule( $prefix_pattern . $route_slug . $current_separator . '([0-9]+)/?$', $query, 'top' );

				if ( $redirect_legacy ) {
					add_rewrite_rule( $prefix_pattern . $route_slug . $legacy_separator . '([0-9]+)/?$', $query, 'top' );
				}
			}
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
	 * Builds a signature for the rewrite rules generated by current settings.
	 *
	 * @param string $structure  Permalink structure name.
	 * @param array  $post_types Enabled post type names.
	 * @param array  $taxonomies Enabled taxonomy names.
	 * @param bool   $enabled                Whether routing is enabled.
	 * @param bool   $redirect_legacy        Whether legacy redirects are enabled.
	 * @return string Rewrite signature.
	 */
	private function build_rewrite_signature( string $structure, array $post_types, array $taxonomies, bool $enabled, bool $redirect_legacy = false ): string {
		$post_routes = array();
		foreach ( $post_types as $post_type ) {
			$post_routes[ $post_type ] = $this->get_post_type_route_slug( $post_type );
		}

		$taxonomy_routes = array();
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_routes[ $taxonomy ] = $this->get_taxonomy_route_slug( $taxonomy );
		}

		return (string) wp_json_encode(
			array(
				'structure'       => $structure,
				'enabled'         => $enabled,
				'redirect_legacy' => $redirect_legacy,
				'use_legacy_id'   => ! empty( $this->get_settings()['use_legacy_id'] ),
				'post_routes'     => $post_routes,
				'taxonomy_routes' => $taxonomy_routes,
				'path_prefixes'   => $this->get_rewrite_path_prefixes(),
			)
		);
	}

	/**
	 * Persists the rewrite marker version and current rewrite signature.
	 */
	private function persist_rewrite_state(): void {
		$structure  = $this->get_permalink_structure();
		$post_types = $this->get_enabled_post_types();
		$taxonomies = $this->get_enabled_taxonomies();
		$enabled    = array() !== $post_types || array() !== $taxonomies;
		$settings   = $this->get_settings();

		update_option( self::REWRITE_MARKER_VERSION_OPTION, self::REWRITE_MARKER_VERSION, true );
		update_option(
			self::REWRITE_SIGNATURE_OPTION,
			$this->build_rewrite_signature( $structure, $post_types, $taxonomies, $enabled, ! empty( $settings['redirect_legacy'] ) ),
			true
		);
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
