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
);

update_option( $settings_option, $settings );

$smoke_plugin    = new PTID_Permalink_Plugin();

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
	isset( $GLOBALS['wp_rewrite']->extra_rules_top['^(?:[^/]+/)*late-books/([0-9]+)/?$'] ),
	'Rewrite rules must use the registered slug when a post type is registered after the plugin.'
);
$smoke_plugin->flush_pending_rewrite_rules();
$persisted_rewrite_rules = get_option( 'rewrite_rules', array() );
ptid_studio_assert_same(
	true,
	isset( $persisted_rewrite_rules['^(?:[^/]+/)*late-books/([0-9]+)/?$'] ),
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
	home_url( '/en/post/' . $created_post_id . '/' ),
	$prefixed_post_url,
	'Existing post URL prefixes must be preserved.'
);

$query_prefixed_post_url = $smoke_plugin->filter_permalink( home_url( '/en/?p=' . $created_post_id ), $created_post );
ptid_studio_assert_same(
	home_url( '/en/post/' . $created_post_id . '/' ),
	$query_prefixed_post_url,
	'Query-style prefixed post URLs must preserve the path prefix.'
);

$prefixed_term_url = $smoke_plugin->filter_term_link( home_url( '/en/studio-smoke-test-category/' ), $created_term, 'category' );
ptid_studio_assert_same(
	home_url( '/en/category/' . $term_id . '/' ),
	$prefixed_term_url,
	'Existing term URL prefixes must be preserved.'
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

$smoke_plugin->register_rewrite_rules();
$rewrite_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^(?:[^/]+/)*post/([0-9]+)/?$'] ),
	'Post ID rewrite rule must be registered.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^(?:[^/]+/)*category/([0-9]+)/?$'] ),
	'Taxonomy ID rewrite rule must be registered.'
);
ptid_studio_assert_same(
	'index.php?post_type=post&p=$matches[1]&ptid_route=1',
	$rewrite_rules['^(?:[^/]+/)*post/([0-9]+)/?$'],
	'Post ID rewrite rules must carry the plugin ownership marker.'
);
ptid_studio_assert_same(
	'index.php?ptid_taxonomy=category&ptid_term_id=$matches[1]&ptid_route=1',
	$rewrite_rules['^(?:[^/]+/)*category/([0-9]+)/?$'],
	'Taxonomy ID rewrite rules must carry the plugin ownership marker.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^(?:[^/]+/)*books/archive/([0-9]+)/?$'] ),
	'Custom post type rewrite rule must use the configured rewrite slug.'
);
ptid_studio_assert_same(
	true,
	isset( $rewrite_rules['^(?:[^/]+/)*genres/archive/([0-9]+)/?$'] ),
	'Custom taxonomy rewrite rule must use the configured rewrite slug.'
);

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
		'structure'       => 'slash',
		'post_types'      => array(),
		'taxonomies'      => array( 'category' ),
		'redirect_legacy' => false,
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
$GLOBALS['wp_rewrite']->extra_rules['third-party-post-rule'] = 'index.php?post_type=post&p=$matches[1]';

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
	array(
		'structure'       => 'slash',
		'post_types'      => array(),
		'taxonomies'      => array(),
		'redirect_legacy' => false,
	),
	get_option( $settings_option ),
	'Admin-side normalization must disable stored content types with conflicting rewrite slugs.'
);
ptid_studio_assert_same(
	false,
	isset( $GLOBALS['wp_rewrite']->extra_rules_top['^(?:[^/]+/)*post/([0-9]+)/?$'] ),
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
		'structure'       => 'slash',
		'post_types'      => array( 'post' ),
		'taxonomies'      => array( 'category' ),
		'redirect_legacy' => false,
	),
	get_option( $settings_option ),
	'Admin-side normalization must discard unavailable content types.'
);
ptid_studio_assert_same(
	get_option( $settings_option ),
	$get_settings->invoke( $read_only_plugin ),
	'Admin-side normalization must refresh the request-local settings cache.'
);

echo "Slug-Free Permalinks Studio smoke tests passed.\n";
