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
	'structure'             => 'slash',
	'post_types'            => array( 'post', 'studio_book' ),
	'taxonomies'            => array( 'category', 'studio_genre' ),
	'redirect_legacy'       => true,
	'preserve_wp_structure' => false,
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
		)
	),
	'Conflicting rewrite slugs must keep the previous settings.'
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
		'structure'             => 'slash',
		'post_types'            => array(),
		'taxonomies'            => array( 'category' ),
		'redirect_legacy'       => false,
		'preserve_wp_structure' => false,
	),
	get_option( $settings_option ),
	'Admin-side normalization must persist the normalized settings.'
);

$conflicting_settings = array(
	'structure'       => 'slash',
	'post_types'      => array( 'post' ),
	'taxonomies'      => array( 'studio_post_route' ),
	'redirect_legacy' => false,
);
update_option( $settings_option, $conflicting_settings );
$GLOBALS['wp_rewrite']->extra_rules['legacy-plugin-post-rule'] = 'index.php?post_type=post&p=$matches[1]&ptid_route=1';
$GLOBALS['wp_rewrite']->extra_rules['third-party-post-rule']   = 'index.php?post_type=post&p=$matches[1]';

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
	array(
		'structure'             => 'slash',
		'post_types'            => array(),
		'taxonomies'            => array(),
		'redirect_legacy'       => false,
		'preserve_wp_structure' => false,
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
);
update_option( $settings_option, $invalid_settings );

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
	array(
		'structure'             => 'slash',
		'post_types'            => array( 'post' ),
		'taxonomies'            => array( 'category' ),
		'redirect_legacy'       => false,
		'preserve_wp_structure' => false,
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
	'structure'             => 'slash',
	'post_types'            => array( 'post' ),
	'taxonomies'            => array( 'category' ),
	'redirect_legacy'       => true,
	'preserve_wp_structure' => false,
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
 * v2 behavior: regular posts can keep the WordPress permalink structure
 * with only the slug replaced by the post ID.
 */
$original_wp_structure = get_option( 'permalink_structure' );
update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

$preserve_settings = array(
	'structure'             => 'slash',
	'post_types'            => array( 'post', 'studio_book' ),
	'taxonomies'            => array( 'category' ),
	'redirect_legacy'       => false,
	'preserve_wp_structure' => true,
);
update_option( $settings_option, $preserve_settings );
$preserve_plugin = new PTID_Permalink_Plugin();

$post_year  = substr( (string) $created_post->post_date, 0, 4 );
$post_month = substr( (string) $created_post->post_date, 5, 2 );

ptid_studio_assert_same(
	home_url( '/' . $post_year . '/' . $post_month . '/' . $created_post_id . '/' ),
	$preserve_plugin->filter_permalink(
		home_url( '/' . $post_year . '/' . $post_month . '/' . $created_post->post_name . '/' ),
		$created_post
	),
	'Preserve mode must replace only the slug in a date-based structure.'
);

update_option( 'permalink_structure', '/%category%/%postname%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%category%/%postname%/' );

$created_categories = get_the_category( $created_post_id );
$created_category   = $created_categories[0];

ptid_studio_assert_same(
	home_url( '/' . $created_category->slug . '/' . $created_post_id . '/' ),
	$preserve_plugin->filter_permalink(
		home_url( '/' . $created_category->slug . '/' . $created_post->post_name . '/' ),
		$created_post
	),
	'Preserve mode must replace only the slug in a category-based structure.'
);

$resolved_vars = $preserve_plugin->resolve_structure_post_request(
	array(
		'name'     => (string) $created_post_id,
		'year'     => $post_year,
		'monthnum' => $post_month,
	)
);
ptid_studio_assert_same(
	$created_post_id,
	$resolved_vars['p'] ?? 0,
	'Preserve mode must resolve a numeric name segment to the post ID.'
);
ptid_studio_assert_same(
	false,
	isset( $resolved_vars['name'] ),
	'Preserve mode must clear the name query variable after resolving an ID.'
);
ptid_studio_assert_same(
	array( 'name' => $created_post->post_name ),
	$preserve_plugin->resolve_structure_post_request( array( 'name' => $created_post->post_name ) ),
	'Preserve mode must leave slug-based requests untouched.'
);

$preserve_plugin->register_rewrite_rules();
$preserve_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	false,
	isset( $preserve_rules['^post/([0-9]+)/?$'] ),
	'Preserve mode must not register a canonical plugin rule for regular posts.'
);
ptid_studio_assert_same(
	true,
	isset( $preserve_rules['^books/archive/([0-9]+)/?$'] ),
	'Preserve mode must keep plugin rules for custom post types.'
);
ptid_studio_assert_same(
	home_url( '/books/archive/' . $custom_post_id . '/' ),
	$preserve_plugin->filter_permalink( home_url( '/books/archive/studio-smoke-test-book/' ), $custom_post ),
	'Preserve mode must not change custom post type permalinks.'
);

$preserve_settings['redirect_legacy'] = true;
update_option( $settings_option, $preserve_settings );
$preserve_plugin = new PTID_Permalink_Plugin();
$preserve_plugin->register_rewrite_rules();
$preserve_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	true,
	isset( $preserve_rules['^post/([0-9]+)/?$'] ) && isset( $preserve_rules['^post-([0-9]+)/?$'] ),
	'Preserve mode must register both plugin formats as legacy redirect sources while legacy redirects are enabled.'
);

update_option( 'permalink_structure', $original_wp_structure );
$GLOBALS['wp_rewrite']->set_permalink_structure( $original_wp_structure );
update_option( $settings_option, $settings );

/*
 * Review follow-ups: numeric structure rules must win over date archive
 * rules, numeric conversion must not touch other post types, and slugs
 * embedded in a structure segment must be replaced.
 */
$original_wp_structure                = get_option( 'permalink_structure' );
$preserve_settings['redirect_legacy'] = false;
update_option( $settings_option, $preserve_settings );

update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
$review_plugin = new PTID_Permalink_Plugin();
$review_plugin->register_rewrite_rules();
$review_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	'index.php?p=$matches[3]&ptid_route=1',
	$review_rules['^([0-9]{4})/([0-9]{1,2})/([0-9]+)/?$'] ?? '',
	'Preserve mode must register a numeric rule for date-based structures.'
);

update_option( 'permalink_structure', '/%postname%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
$review_plugin = new PTID_Permalink_Plugin();
$review_plugin->register_rewrite_rules();
$review_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	'index.php?p=$matches[1]&ptid_route=1',
	$review_rules['^([0-9]+)/?$'] ?? '',
	'Preserve mode must register a numeric rule for the postname-only structure.'
);

ptid_studio_assert_same(
	array(
		'post_type' => 'studio_book',
		'name'      => (string) $created_post_id,
	),
	$review_plugin->resolve_structure_post_request(
		array(
			'post_type' => 'studio_book',
			'name'      => (string) $created_post_id,
		)
	),
	'Numeric name conversion must not rewrite requests for other post types.'
);
ptid_studio_assert_same(
	$created_post_id,
	$review_plugin->resolve_structure_post_request(
		array(
			'post_type' => 'post',
			'name'      => (string) $created_post_id,
		)
	)['p'] ?? 0,
	'Numeric name conversion must accept requests explicitly targeting posts.'
);

update_option( 'permalink_structure', '/%post_id%-%postname%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%post_id%-%postname%/' );

ptid_studio_assert_same(
	home_url( '/' . $created_post_id . '-' . $created_post_id . '/' ),
	$review_plugin->filter_permalink(
		home_url( '/' . $created_post_id . '-' . $created_post->post_name . '/' ),
		$created_post
	),
	'Preserve mode must replace a slug embedded in a structure segment.'
);

update_option( 'permalink_structure', $original_wp_structure );
$GLOBALS['wp_rewrite']->set_permalink_structure( $original_wp_structure );
update_option( $settings_option, $settings );


/*
 * Regression: a broad category structure must not capture explicitly
 * configured custom post type and taxonomy ID routes.
 */
$original_wp_structure = get_option( 'permalink_structure' );
update_option( 'permalink_structure', '/%category%/%postname%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%category%/%postname%/' );
update_option( $settings_option, $preserve_settings );
$route_plugin = new PTID_Permalink_Plugin();
$route_plugin->register_rewrite_rules();
$route_keys = array_keys( $GLOBALS['wp_rewrite']->extra_rules_top );
ptid_studio_assert_same(
	true,
	array_search( '^books/archive/([0-9]+)/?$', $route_keys, true ) < array_search( '^(.+?)/([0-9]+)/?$', $route_keys, true ),
	'Custom post type ID routes must precede the broad category structure route.'
);
ptid_studio_assert_same(
	true,
	array_search( '^category/([0-9]+)/?$', $route_keys, true ) < array_search( '^(.+?)/([0-9]+)/?$', $route_keys, true ),
	'Taxonomy ID routes must precede the broad category structure route.'
);

/*
 * Regression: when the slug equals the category, replace the slug in
 * its configured structural position, not the last matching segment.
 */
update_option( 'permalink_structure', '/%postname%/%category%/' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/%category%/' );
$route_plugin               = new PTID_Permalink_Plugin();
$collision_post             = clone $created_post;
$collision_post->post_name  = 'news';
ptid_studio_assert_same(
	home_url( '/' . $created_post_id . '/news/' ),
	$route_plugin->filter_permalink( home_url( '/news/news/' ), $collision_post ),
	'Preserve mode must replace the postname token even when category and slug match.'
);
update_option( 'permalink_structure', $original_wp_structure );
$GLOBALS['wp_rewrite']->set_permalink_structure( $original_wp_structure );

echo "Slug-Free Permalinks Studio smoke tests passed.\n";
