<?php
/**
 * Exercises routing and settings behavior in WordPress Studio.
 *
 * @package Slug_Free_Permalinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file through WordPress Studio WP-CLI.\n" );
	exit( 1 );
}

/**
 * Fails the smoke test when two values are not identical.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Failure message.
 */
function ptid_studio_assert_same( $expected, $actual, string $message ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite(
		STDERR,
		$message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n"
	);
	exit( 1 );
}

$settings_option = 'ptid_permalink_settings';

if ( ! function_exists( 'pll_languages_list' ) ) {
	/**
	 * Provides a minimal Polylang language list for rewrite smoke tests.
	 *
	 * @param array $args Polylang language query arguments.
	 * @return array Language slugs.
	 */
	function pll_languages_list( $args = array() ): array {
		unset( $args );

		return array( 'ja', 'en' );
	}
}

if ( ! function_exists( 'pll_home_url' ) ) {
	/**
	 * Provides language home URLs for rewrite smoke tests.
	 *
	 * @param string $language Language slug.
	 * @return string Language home URL.
	 */
	function pll_home_url( $language = '' ): string {
		$home_url = untrailingslashit( (string) get_option( 'home' ) );

		return 'en' === $language ? $home_url . '/en/' : $home_url . '/';
	}
}

register_post_type(
	'studio_book',
	array(
		'public'       => true,
		'show_ui'      => true,
		'supports'     => array( 'title' ),
		'rewrite'      => array( 'slug' => 'books/archive' ),
		'show_in_rest' => true,
	)
);
register_taxonomy(
	'studio_genre',
	array( 'studio_book' ),
	array(
		'public'       => true,
		'show_ui'      => true,
		'rewrite'      => array( 'slug' => 'genres/archive' ),
		'show_in_rest' => true,
	)
);
register_taxonomy(
	'studio_post_route',
	array( 'post' ),
	array(
		'public'       => true,
		'show_ui'      => true,
		'rewrite'      => array( 'slug' => 'post' ),
		'show_in_rest' => true,
	)
);

$settings = array(
	'structure'       => 'slash',
	'post_types'      => array( 'post', 'studio_book' ),
	'taxonomies'      => array( 'category', 'studio_genre' ),
	'redirect_legacy' => true,
	'use_legacy_id'   => false,
	'legacy_fallback' => 'id',
);

update_option( $settings_option, $settings );

$smoke_plugin = new PTID_Permalink_Plugin();

ptid_studio_assert_same(
	$settings,
	$smoke_plugin->sanitize_settings(
		array(
			'structure'       => 'slash',
			'post_types'      => array( 'post' ),
			'taxonomies'      => array( 'studio_post_route' ),
			'redirect_legacy' => false,
			'use_legacy_id'   => false,
			'legacy_fallback' => 'id',
		)
	),
	'Conflicting rewrite slugs must keep the previous settings.'
);

/*
 * Saving custom-target settings must not silently change legacy regular
 * post URLs or remove the post target before an explicit migration.
 */
ptid_studio_assert_same(
	$settings,
	$smoke_plugin->sanitize_settings(
		array(
			'structure'       => 'hyphen',
			'post_types'      => array( 'studio_book' ),
			'taxonomies'      => array( 'category', 'studio_genre' ),
			'redirect_legacy' => true,
			'use_legacy_id'   => false,
			'legacy_fallback' => 'id',
		)
	),
	'Saving custom-target settings must preserve the legacy post URL format and target.'
);

$settings['post_types'][] = 'studio_late_book';
update_option( $settings_option, $settings );
register_post_type(
	'studio_late_book',
	array(
		'public'       => true,
		'show_ui'      => true,
		'supports'     => array( 'title' ),
		'rewrite'      => array( 'slug' => 'late-books' ),
		'show_in_rest' => true,
	)
);

ptid_studio_assert_same(
	true,
	isset( $GLOBALS['wp_rewrite']->extra_rules_top['^late-books/([0-9]+)/?$'] ),
	'Rewrite rules must use the registered slug when a post type is registered after the plugin.'
);
$smoke_plugin->flush_pending_rewrite_rules();
$persisted_rewrite_rules = get_option( 'rewrite_rules', array() );
ptid_studio_assert_same(
	true,
	isset( $persisted_rewrite_rules['^late-books/([0-9]+)/?$'] ),
	'Late-registered post type rewrite rules must be persisted after the deferred flush.'
);

$created_post_id = wp_insert_post(
	array(
		'post_title'   => 'Studio smoke test post',
		'post_content' => 'Studio smoke test content.',
		'post_status'  => 'publish',
	),
	true
);

if ( is_wp_error( $created_post_id ) ) {
	fwrite( STDERR, 'Could not create the smoke-test post: ' . $created_post_id->get_error_message() . "\n" );
	exit( 1 );
}

$created_post = get_post( $created_post_id );
$post_url     = $smoke_plugin->filter_permalink( home_url( '/?p=' . $created_post_id ), $created_post );

ptid_studio_assert_same(
	home_url( '/post/' . $created_post_id . '/' ),
	$post_url,
	'Published posts must use the configured ID-based permalink.'
);

$term_result = wp_insert_term( 'Studio smoke test category ' . wp_generate_uuid4(), 'category' );
if ( is_wp_error( $term_result ) ) {
	fwrite( STDERR, 'Could not create the smoke-test category: ' . $term_result->get_error_message() . "\n" );
	exit( 1 );
}

$term_id      = (int) $term_result['term_id'];
$created_term = get_term( $term_id, 'category' );
$term_url     = $smoke_plugin->filter_term_link( home_url( '/category/' . $created_term->slug . '/' ), $created_term, 'category' );

ptid_studio_assert_same(
	home_url( '/category/' . $term_id . '/' ),
	$term_url,
	'Selected taxonomies must use the configured ID-based permalink.'
);

$prefixed_post_url = $smoke_plugin->filter_permalink( home_url( '/en/studio-smoke-test-post/' ), $created_post );
ptid_studio_assert_same(
	home_url( '/post/' . $created_post_id . '/' ),
	$prefixed_post_url,
	'Arbitrary prefixes from existing post URLs must not be preserved.'
);

$query_prefixed_post_url = $smoke_plugin->filter_permalink( home_url( '/en/?p=' . $created_post_id ), $created_post );
ptid_studio_assert_same(
	home_url( '/post/' . $created_post_id . '/' ),
	$query_prefixed_post_url,
	'Query-style existing URLs must not add an arbitrary path prefix.'
);

$prefixed_term_url = $smoke_plugin->filter_term_link( home_url( '/en/studio-smoke-test-category/' ), $created_term, 'category' );
ptid_studio_assert_same(
	home_url( '/category/' . $term_id . '/' ),
	$prefixed_term_url,
	'Arbitrary prefixes from existing term URLs must not be preserved.'
);

$append_query_args = new ReflectionMethod( PTID_Permalink_Plugin::class, 'append_current_query_args' );
$append_query_args->setAccessible( true );
$should_redirect_to_target = new ReflectionMethod( PTID_Permalink_Plugin::class, 'should_redirect_to_target' );
$should_redirect_to_target->setAccessible( true );

ptid_studio_assert_same(
	home_url( '/post/' . $created_post_id . '/' ),
	$append_query_args->invoke(
		$smoke_plugin,
		home_url( '/post/' . $created_post_id . '/' ),
		home_url( '/?p=' . $created_post_id )
	),
	'WordPress routing query vars must not be copied to canonical URLs.'
);

ptid_studio_assert_same(
	home_url( '/post/' . $created_post_id . '/?utm_source=legacy' ),
	$append_query_args->invoke(
		$smoke_plugin,
		home_url( '/post/' . $created_post_id . '/' ),
		home_url( '/?p=' . $created_post_id . '&utm_source=legacy' )
	),
	'Non-routing query vars must be preserved during canonical redirects.'
);

$canonical_tracking_url = $append_query_args->invoke(
	$smoke_plugin,
	home_url( '/post/' . $created_post_id . '/' ),
	home_url( '/post/' . $created_post_id . '/?utm_source=legacy' )
);
ptid_studio_assert_same(
	false,
	$should_redirect_to_target->invoke( $smoke_plugin, $canonical_tracking_url, $canonical_tracking_url ),
	'Canonical URLs with preserved query vars must not redirect to themselves.'
);

$custom_post_id = wp_insert_post(
	array(
		'post_title'  => 'Studio smoke test book',
		'post_status' => 'publish',
		'post_type'   => 'studio_book',
	),
	true
);
if ( is_wp_error( $custom_post_id ) ) {
	fwrite( STDERR, 'Could not create the smoke-test book: ' . $custom_post_id->get_error_message() . "\n" );
	exit( 1 );
}

$custom_post     = get_post( $custom_post_id );
$custom_post_url = $smoke_plugin->filter_permalink( home_url( '/books/archive/studio-smoke-test-book/' ), $custom_post );
ptid_studio_assert_same(
	home_url( '/books/archive/' . $custom_post_id . '/' ),
	$custom_post_url,
	'Custom post type rewrite slugs must be used for ID-based permalinks.'
);

$custom_term_result = wp_insert_term( 'Studio smoke test genre ' . wp_generate_uuid4(), 'studio_genre' );
if ( is_wp_error( $custom_term_result ) ) {
	fwrite( STDERR, 'Could not create the smoke-test genre: ' . $custom_term_result->get_error_message() . "\n" );
	exit( 1 );
}

$custom_term_id  = (int) $custom_term_result['term_id'];
$custom_term     = get_term( $custom_term_id, 'studio_genre' );
$custom_term_url = $smoke_plugin->filter_term_link( home_url( '/genres/archive/studio-smoke-test-genre/' ), $custom_term, 'studio_genre' );
ptid_studio_assert_same(
	home_url( '/genres/archive/' . $custom_term_id . '/' ),
	$custom_term_url,
	'Custom taxonomy rewrite slugs must be used for ID-based permalinks.'
);

$filter_current_language_home = static function ( string $url, string $path ): string {
	if ( '/' !== $path ) {
		return $url;
	}

	return untrailingslashit( $url ) . '/en/';
};
add_filter( 'home_url', $filter_current_language_home, 10, 2 );

$smoke_plugin->register_rewrite_rules();

remove_filter( 'home_url', $filter_current_language_home, 10 );
$rewrite_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^post/([0-9]+)/?$'] ),
	'Post ID base rewrite rule must be registered.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^en/post/([0-9]+)/?$'] ),
	'Polylang language path rewrite rule must be registered.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^category/([0-9]+)/?$'] ),
	'Taxonomy ID base rewrite rule must be registered.'
);
ptid_studio_assert_same(
	'index.php?post_type=post&p=$matches[1]&ptid_route=1',
	$rewrite_rules['^post/([0-9]+)/?$'],
	'Post ID rewrite rules must carry the plugin ownership marker.'
);
ptid_studio_assert_same(
	'index.php?ptid_taxonomy=category&ptid_term_id=$matches[1]&ptid_route=1',
	$rewrite_rules['^en/category/([0-9]+)/?$'],
	'Polylang taxonomy rewrite rules must carry the plugin ownership marker.'
);
ptid_studio_assert_same(
	false,
	isset( $rewrite_rules['^(?:[^/]+/)*post/([0-9]+)/?$'] ),
	'Arbitrary path-prefix rewrite rules must not be registered.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^books/archive/([0-9]+)/?$'] ),
	'Custom post type rewrite rule must use the configured rewrite slug.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^en/genres/archive/([0-9]+)/?$'] ),
	'Polylang custom taxonomy rewrite rule must use the configured rewrite slug.'
);

/*
 * Freeze v1 behavior before introducing configurable permalink structures in v2.
 * WordPress's permalink setting must not leak date/category prefixes into
 * selected posts' ID URLs, and both existing plugin formats must stay stable.
 */
$original_wp_structure = get_option( 'permalink_structure' );
foreach ( array( '/%year%/%monthnum%/%postname%/', '/%category%/%postname%/' ) as $wp_structure ) {
	$GLOBALS['wp_rewrite']->set_permalink_structure( $wp_structure );

	foreach ( array( 'slash', 'hyphen' ) as $plugin_structure ) {
		$compat_settings               = $settings;
		$compat_settings['structure']  = $plugin_structure;
		$compat_settings['post_types'] = array( 'post', 'studio_book' );
		$compat_settings['taxonomies'] = array( 'category', 'studio_genre' );
		update_option( $settings_option, $compat_settings );
		$compat_plugin = new PTID_Permalink_Plugin();

		// Obtain the actual WordPress permalink without the plugin's post_link filter.
		$plugin_post_link_callbacks = array();
		foreach ( $GLOBALS['wp_filter']['post_link']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$callback = $entry['function'];
				if ( is_array( $callback ) && $callback[0] instanceof PTID_Permalink_Plugin ) {
					$plugin_post_link_callbacks[] = array( $callback, $priority, $entry['accepted_args'] );
					remove_filter( 'post_link', $callback, $priority );
				}
			}
		}
		$core_post_url = get_permalink( $created_post );
		foreach ( $plugin_post_link_callbacks as $entry ) {
			add_filter( 'post_link', $entry[0], $entry[1], $entry[2] );
		}

		ptid_studio_assert_same(
			true,
			false !== strpos( $core_post_url, '/' . $created_post->post_name . '/' ),
			'WordPress must generate a slug-based URL before the plugin replaces it.'
		);

		$post_path = 'slash' === $plugin_structure
			? '/post/' . $created_post_id . '/'
			: '/post-' . $created_post_id . '/';
		ptid_studio_assert_same(
			home_url( $post_path ),
			$compat_plugin->filter_permalink( $core_post_url, $created_post ),
			'Selected post URL must remain in the v1 ID format regardless of WordPress structure: ' . $wp_structure . ' / ' . $plugin_structure
		);

		$book_path = 'slash' === $plugin_structure
			? '/books/archive/' . $custom_post_id . '/'
			: '/books/archive-' . $custom_post_id . '/';
		ptid_studio_assert_same(
			home_url( $book_path ),
			$compat_plugin->filter_permalink( home_url( '/books/archive/studio-smoke-test-book/' ), $custom_post ),
			'Custom post type ID URLs must remain stable: ' . $wp_structure . ' / ' . $plugin_structure
		);

		$category_path = 'slash' === $plugin_structure
			? '/category/' . $term_id . '/'
			: '/category-' . $term_id . '/';
		ptid_studio_assert_same(
			home_url( $category_path ),
			$compat_plugin->filter_term_link( home_url( '/category/studio-smoke-test-category/' ), $created_term, 'category' ),
			'Selected taxonomy ID URLs must remain stable: ' . $wp_structure . ' / ' . $plugin_structure
		);

		$genre_path = 'slash' === $plugin_structure
			? '/genres/archive/' . $custom_term_id . '/'
			: '/genres/archive-' . $custom_term_id . '/';
		ptid_studio_assert_same(
			home_url( $genre_path ),
			$compat_plugin->filter_term_link( home_url( '/genres/archive/studio-smoke-test-genre/' ), $custom_term, 'studio_genre' ),
			'Custom taxonomy ID URLs must remain stable: ' . $wp_structure . ' / ' . $plugin_structure
		);
	}
}
$GLOBALS['wp_rewrite']->set_permalink_structure( $original_wp_structure );
update_option( $settings_option, $settings );

$legacy_settings = array(
	'structure'       => 'unexpected-format',
	'post_types'      => 'not-an-array',
	'taxonomies'      => array( 'category', 'category' ),
	'redirect_legacy' => false,
	'use_legacy_id'   => false,
	'legacy_fallback' => 'id',
);
update_option( $settings_option, $legacy_settings );

$read_only_plugin = new PTID_Permalink_Plugin();
$get_settings     = new ReflectionMethod( PTID_Permalink_Plugin::class, 'get_settings' );
$get_settings->setAccessible( true );
$get_settings->invoke( $read_only_plugin );

ptid_studio_assert_same(
	$legacy_settings,
	get_option( $settings_option ),
	'Reading settings must not persist normalized values.'
);

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
	array(
		'structure'       => 'slash',
		'post_types'      => array(),
		'taxonomies'      => array( 'category' ),
		'redirect_legacy' => false,
		'use_legacy_id'   => false,
		'legacy_fallback' => 'id',
	),
	get_option( $settings_option ),
	'Admin-side normalization must persist the normalized settings.'
);

$conflicting_settings = array(
	'structure'       => 'slash',
	'post_types'      => array( 'post' ),
	'taxonomies'      => array( 'studio_post_route' ),
	'redirect_legacy' => false,
	'use_legacy_id'   => false,
	'legacy_fallback' => 'id',
);
update_option( $settings_option, $conflicting_settings );
$GLOBALS['wp_rewrite']->extra_rules['legacy-plugin-post-rule'] = 'index.php?post_type=post&p=$matches[1]&ptid_route=1';
$GLOBALS['wp_rewrite']->extra_rules['third-party-post-rule']   = 'index.php?post_type=post&p=$matches[1]';

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
	array(
		'structure'       => 'slash',
		'post_types'      => array(),
		'taxonomies'      => array(),
		'redirect_legacy' => false,
		'use_legacy_id'   => false,
		'legacy_fallback' => 'id',
	),
	get_option( $settings_option ),
	'Admin-side normalization must disable stored content types with conflicting rewrite slugs.'
);
ptid_studio_assert_same(
	false,
	isset( $GLOBALS['wp_rewrite']->extra_rules_top['^post/([0-9]+)/?$'] ),
	'Admin-side normalization must remove stale plugin rewrite rules when disabling conflicting selections.'
);
ptid_studio_assert_same(
	false,
	isset( $GLOBALS['wp_rewrite']->extra_rules['legacy-plugin-post-rule'] ),
	'Admin-side normalization must remove stale bottom-priority plugin rewrite rules.'
);
ptid_studio_assert_same(
	true,
	isset( $GLOBALS['wp_rewrite']->extra_rules['third-party-post-rule'] ),
	'Admin-side normalization must keep third-party rewrite rules with similar query shapes.'
);

$invalid_settings = array(
	'structure'       => 'slash',
	'post_types'      => array( 'post', 'page', 'not_registered' ),
	'taxonomies'      => array( 'category', 'post_format', 'not_registered' ),
	'redirect_legacy' => false,
	'use_legacy_id'   => false,
	'legacy_fallback' => 'id',
);
update_option( $settings_option, $invalid_settings );

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
	array(
		'structure'       => 'slash',
		'post_types'      => array( 'post' ),
		'taxonomies'      => array( 'category' ),
		'redirect_legacy' => false,
		'use_legacy_id'   => false,
		'legacy_fallback' => 'id',
	),
	get_option( $settings_option ),
	'Admin-side normalization must discard unavailable content types.'
);
ptid_studio_assert_same(
	get_option( $settings_option ),
	$get_settings->invoke( $read_only_plugin ),
	'Admin-side normalization must refresh the request-local settings cache.'
);

/*
 * v2 behavior: the non-selected ID format resolves while legacy redirects
 * are enabled, so format switches can redirect to the canonical URL.
 */
$alternate_settings = array(
	'structure'       => 'slash',
	'post_types'      => array( 'post' ),
	'taxonomies'      => array( 'category' ),
	'redirect_legacy' => true,
	'use_legacy_id'   => false,
	'legacy_fallback' => 'id',
);
update_option( $settings_option, $alternate_settings );
$alternate_plugin = new PTID_Permalink_Plugin();
$alternate_plugin->register_rewrite_rules();
$alternate_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	true,
	isset( $alternate_rules['^post-([0-9]+)/?$'] ),
	'The non-selected post ID format must resolve while legacy redirects are enabled.'
);
ptid_studio_assert_same(
	'index.php?post_type=post&p=$matches[1]&ptid_route=1',
	$alternate_rules['^post-([0-9]+)/?$'],
	'Alternate format rules must resolve to the same post query as the canonical format.'
);
ptid_studio_assert_same(
	true,
	isset( $alternate_rules['^category-([0-9]+)/?$'] ),
	'The non-selected taxonomy ID format must resolve while legacy redirects are enabled.'
);

$alternate_settings['redirect_legacy'] = false;
update_option( $settings_option, $alternate_settings );
$alternate_plugin = new PTID_Permalink_Plugin();
$alternate_plugin->register_rewrite_rules();
$alternate_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	false,
	isset( $alternate_rules['^post-([0-9]+)/?$'] ),
	'The non-selected post ID format must not resolve while legacy redirects are disabled.'
);
ptid_studio_assert_same(
	true,
	isset( $alternate_rules['^post/([0-9]+)/?$'] ),
	'The selected post ID format must stay registered while legacy redirects are disabled.'
);

/*
 * Native WordPress migration keeps regular-post ID URLs and leaves custom
 * post types and taxonomies under the plugin's existing rewrite rules.
 */
// Render the settings screen as an administrator to verify its visible layout.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
$studio_admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ids',
	)
);
ptid_studio_assert_same( true, ! empty( $studio_admins ), 'Studio must provide an administrator for settings UI tests.' );
$original_user_id = get_current_user_id();
wp_set_current_user( (int) $studio_admins[0] );

$previous_wp_structure = get_option( 'permalink_structure' );
$migrate_method        = new ReflectionMethod( PTID_Permalink_Plugin::class, 'apply_post_structure_migration' );
$migrate_method->setAccessible( true );

foreach ( array( 'slash', 'hyphen' ) as $format ) {
	$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
	$migration_settings = array(
		'structure'       => $format,
		'post_types'      => array( 'post', 'studio_book' ),
		'taxonomies'      => array( 'category', 'studio_genre' ),
		'redirect_legacy' => false,
		'use_legacy_id'   => false,
		'legacy_fallback' => 'id',
	);
	update_option( $settings_option, $migration_settings );
	update_option( 'ptid_rewrite_signature', 'pre-migration-signature' );
	$migration_plugin = new PTID_Permalink_Plugin();
	ob_start();
	$migration_plugin->render_settings_page();
	$legacy_ui = ob_get_clean();
	ptid_studio_assert_same(
		true,
		false !== strpos( $legacy_ui, 'name="action" value="ptid_migrate_post_structure"' ),
		'Legacy sites must show an explicit migration action in the regular-post section.'
	);
	ptid_studio_assert_same(
		true,
		false !== strpos( $legacy_ui, 'name="ptid_permalink_settings[post_types][]" value="post"' ),
		'Legacy regular post routing must be preserved as a hidden option in the custom-target form.'
	);
	ptid_studio_assert_same(
		0,
		preg_match( '/<input\\b(?=[^>]*\\btype="checkbox")(?=[^>]*\\bvalue="post")[^>]*>/s', $legacy_ui ),
		'Regular posts must not appear as a selectable plugin target.'
	);
	ptid_studio_assert_same(
		true,
		false !== strpos( $legacy_ui, 'name="ptid_post_structure"' ),
		'Legacy migration must use the same editable WordPress structure field.'
	);
	ptid_studio_assert_same(
		true,
		false !== strpos( $legacy_ui, 'data-ptid-preset="/post/%post_id%/"' ),
		'The permalink builder must provide WordPress-native presets.'
	);
	ptid_studio_assert_same(
		true,
		false !== strpos( $legacy_ui, 'data-ptid-tag="%post_id%"' ),
		'The permalink builder must offer token insertion into the structure input.'
	);
	ptid_studio_assert_same(
		1,
		preg_match_all( '/<input\\b[^>]*\\btype="text"[^>]*>/s', $legacy_ui ),
		'The permalink builder must show only one editable text input.'
	);
	$migrate_method->invoke( $migration_plugin );
	ob_start();
	$migration_plugin->render_settings_page();
	$native_ui = ob_get_clean();
	ptid_studio_assert_same(
		false,
		false !== strpos( $native_ui, 'name="action" value="ptid_migrate_post_structure"' ),
		'Native regular post settings must not show the legacy migration action.'
	);
	ptid_studio_assert_same(
		true,
		false !== strpos( $native_ui, 'options-permalink.php' ),
		'Native regular post settings must link to WordPress permalink settings.'
	);

	ptid_studio_assert_same(
		true,
		false !== strpos( $native_ui, 'name="action" value="ptid_save_post_structure"' ),
		'Native posts must provide an explicit save action.'
	);
	ptid_studio_assert_same(
		1,
		preg_match_all( '/<input\\b[^>]*\\btype="text"[^>]*>/s', $native_ui ),
		'The native permalink editor must use one text field.'
	);
	$expected_structure = 'hyphen' === $format ? '/post-%post_id%/' : '/post/%post_id%/';
	ptid_studio_assert_same(
		$expected_structure,
		get_option( 'permalink_structure' ),
		'Migration must set the matching WordPress native post ID structure.'
	);
	ptid_studio_assert_same(
		false,
		get_option( 'ptid_rewrite_signature', false ),
		'Migration must defer rewriting until the next request can rebuild CPT routes with the new permalink front.'
	);
	$no_reenable_settings = $migration_plugin->sanitize_settings(
		array(
			'structure'       => $format,
			'post_types'      => array( 'post', 'studio_book' ),
			'taxonomies'      => array( 'category', 'studio_genre' ),
			'redirect_legacy' => false,
			'use_legacy_id'   => false,
			'legacy_fallback' => 'id',
		)
	);
	ptid_studio_assert_same(
		false,
		in_array( 'post', $no_reenable_settings['post_types'], true ),
		'Plugin settings must not re-enable regular post routing after migration.'
	);
	$migrated_settings = get_option( $settings_option );
	ptid_studio_assert_same(
		array( 'studio_book' ),
		$migrated_settings['post_types'],
		'Migration must only deselect regular posts.'
	);
	ptid_studio_assert_same(
		array( 'category', 'studio_genre' ),
		$migrated_settings['taxonomies'],
		'Migration must preserve taxonomy settings.'
	);
	ptid_studio_assert_same(
		home_url( 'hyphen' === $format ? '/post-' . $created_post_id . '/' : '/post/' . $created_post_id . '/' ),
		$migration_plugin->filter_permalink( home_url( 'hyphen' === $format ? '/post-' . $created_post_id . '/' : '/post/' . $created_post_id . '/' ), $created_post ),
		'Plugin must not rewrite native regular post ID URLs after migration.'
	);
	ptid_studio_assert_same(
		home_url( 'hyphen' === $format ? '/books/archive-' . $custom_post_id . '/' : '/books/archive/' . $custom_post_id . '/' ),
		$migration_plugin->filter_permalink( home_url( '/books/archive/book/' ), $custom_post ),
		'Migration must retain custom post type ID URLs.'
	);
	// WordPress-native edits must not recreate plugin regular-post routing.
	$native_edit = new ReflectionMethod( PTID_Permalink_Plugin::class, 'apply_native_post_structure' );
	$native_edit->setAccessible( true );
	$native_edit->invoke( $migration_plugin, '/%year%/%monthnum%/%post_id%/' );
	ptid_studio_assert_same(
		'/%year%/%monthnum%/%post_id%/',
		get_option( 'permalink_structure' ),
		'Editing the structure must update the WordPress native permalink option.'
	);
	ptid_studio_assert_same(
		false,
		get_option( 'ptid_rewrite_signature', false ),
		'Changing the native structure must defer rebuilding CPT rewrite rules.'
	);
	ptid_studio_assert_same(
		array( 'studio_book' ),
		get_option( $settings_option )['post_types'],
		'Editing WordPress permalinks must not restore regular-post plugin routing.'
	);
}
/*
 * Legacy metadata is supplied by importers. This plugin only uses existing
 * values, resolves incoming routes and fails closed when IDs overlap.
 */
$legacy_settings = array(
	'structure'       => 'slash',
	'post_types'      => array( 'studio_book' ),
	'taxonomies'      => array( 'category' ),
	'redirect_legacy' => true,
	'use_legacy_id'   => true,
	'legacy_fallback' => 'id',
);
update_option( $settings_option, $legacy_settings );
$legacy_plugin = new PTID_Permalink_Plugin();
$legacy_plugin->register_legacy_rewrite_tag();
$legacy_plugin->register_rewrite_rules();
$lookup_legacy = new ReflectionMethod( PTID_Permalink_Plugin::class, 'lookup_legacy_post' );
$lookup_legacy->setAccessible( true );

ptid_studio_assert_same(
	true,
	isset( $GLOBALS['wp_rewrite']->extra_rules_top['^books/archive/([A-Za-z0-9_-]+)/?$'] ),
	'Legacy-enabled CPT rewrite rules must accept string identifiers.'
);
ptid_studio_assert_same(
	home_url( '/books/archive/' . $custom_post_id . '/' ),
	$legacy_plugin->filter_permalink( home_url( '/books/archive/book/' ), $custom_post ),
	'Missing legacy metadata must use the WordPress post ID.'
);
ptid_studio_assert_same(
	$custom_post_id,
	$lookup_legacy->invoke( $legacy_plugin, (string) $custom_post_id, 'studio_book' ),
	'A fallback WordPress post ID must resolve to the same CPT.'
);

update_post_meta( $custom_post_id, 'legacy_id', 'old-book-45' );
ptid_studio_assert_same(
	home_url( '/books/archive/old-book-45/' ),
	$legacy_plugin->filter_permalink( home_url( '/books/archive/book/' ), $custom_post ),
	'Existing legacy_id metadata must determine the public CPT URL.'
);
ptid_studio_assert_same(
	$custom_post_id,
	$lookup_legacy->invoke( $legacy_plugin, 'old-book-45', 'studio_book' ),
	'Legacy ID requests must resolve to the original CPT.'
);
ptid_studio_assert_same(
	0,
	$lookup_legacy->invoke( $legacy_plugin, (string) $custom_post_id, 'studio_book' ),
	'A post ID must not remain an alias when the same post uses a legacy ID.'
);

$legacy_second_id = wp_insert_post(
	array(
		'post_title'  => 'Second legacy smoke book',
		'post_status' => 'publish',
		'post_type'   => 'studio_book',
	),
	true
);
if ( is_wp_error( $legacy_second_id ) ) {
	fwrite( STDERR, 'Could not create the legacy collision book.' . "\\n" );
	exit( 1 );
}
update_post_meta( $legacy_second_id, 'legacy_id', 'old-book-45' );
ptid_studio_assert_same(
	0,
	$lookup_legacy->invoke( $legacy_plugin, 'old-book-45', 'studio_book' ),
	'Duplicate legacy IDs must not resolve to an arbitrary post.'
);
update_post_meta( $legacy_second_id, 'legacy_id', (string) $custom_post_id );
delete_post_meta( $custom_post_id, 'legacy_id' );
ptid_studio_assert_same(
	0,
	$lookup_legacy->invoke( $legacy_plugin, (string) $custom_post_id, 'studio_book' ),
	'A legacy ID colliding with a different post ID must fail closed.'
);
delete_post_meta( $legacy_second_id, 'legacy_id' );

$legacy_slug_settings                    = $legacy_settings;
$legacy_slug_settings['legacy_fallback'] = 'slug';
update_option( $settings_option, $legacy_slug_settings );
$legacy_slug_plugin = new PTID_Permalink_Plugin();
ptid_studio_assert_same(
	$custom_post_id,
	$lookup_legacy->invoke( $legacy_slug_plugin, $custom_post->post_name, 'studio_book' ),
	'Missing metadata must resolve by post slug when selected.'
);
ptid_studio_assert_same(
	home_url( '/books/archive/' . $custom_post->post_name . '/' ),
	$legacy_slug_plugin->filter_permalink( home_url( '/books/archive/book/' ), $custom_post ),
	'Post slug must be used when it is the selected fallback.'
);

$GLOBALS['wp_rewrite']->set_permalink_structure( '/entry/%legacy_id%.html' );
update_option( $settings_option, $legacy_settings );
$native_legacy_plugin = new PTID_Permalink_Plugin();
update_post_meta( $created_post_id, 'legacy_id', 'migration-123' );
ptid_studio_assert_same(
	home_url( '/entry/migration-123.html' ),
	$native_legacy_plugin->filter_permalink( home_url( '/entry/%legacy_id%.html' ), $created_post ),
	'Native WordPress custom tags must be replaced using the imported identifier.'
);
ptid_studio_assert_same(
	$created_post_id,
	$lookup_legacy->invoke( $native_legacy_plugin, 'migration-123', 'post' ),
	'Native post legacy IDs must resolve using the same lookup logic.'
);
delete_post_meta( $created_post_id, 'legacy_id' );
delete_post_meta( $custom_post_id, 'legacy_id' );
delete_post_meta( $legacy_second_id, 'legacy_id' );
wp_delete_post( $legacy_second_id, true );

wp_set_current_user( $original_user_id );
$GLOBALS['wp_rewrite']->set_permalink_structure( $previous_wp_structure );
update_option( $settings_option, $settings );

echo "Slug-Free Permalinks Studio smoke tests passed.\n";
