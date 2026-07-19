<?php

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run this file through WordPress Studio WP-CLI.\n");
    exit(1);
}

function ptid_studio_assert_same($expected, $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(
        STDERR,
        $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n"
    );
    exit(1);
}

$settings_option = 'ptid_permalink_settings';
$settings = array(
    'structure' => 'slash',
    'post_types' => array('post'),
    'taxonomies' => array('category'),
    'redirect_legacy' => true,
);

update_option($settings_option, $settings);

$plugin = new PTID_Permalink_Plugin();
$post_id = wp_insert_post(
    array(
        'post_title' => 'Studio smoke test post',
        'post_content' => 'Studio smoke test content.',
        'post_status' => 'publish',
    ),
    true
);

if (is_wp_error($post_id)) {
    fwrite(STDERR, 'Could not create the smoke-test post: ' . $post_id->get_error_message() . "\n");
    exit(1);
}

$post = get_post($post_id);
$post_url = $plugin->filter_permalink(home_url('/?p=' . $post_id), $post);

ptid_studio_assert_same(
    home_url('/post/' . $post_id . '/'),
    $post_url,
    'Published posts must use the configured ID-based permalink.'
);

$term_result = wp_insert_term('Studio smoke test category ' . wp_generate_uuid4(), 'category');
if (is_wp_error($term_result)) {
    fwrite(STDERR, 'Could not create the smoke-test category: ' . $term_result->get_error_message() . "\n");
    exit(1);
}

$term_id = (int) $term_result['term_id'];
$term = get_term($term_id, 'category');
$term_url = $plugin->filter_term_link(home_url('/category/' . $term->slug . '/'), $term, 'category');

ptid_studio_assert_same(
    home_url('/category/' . $term_id . '/'),
    $term_url,
    'Selected taxonomies must use the configured ID-based permalink.'
);

$plugin->register_rewrite_rules();
$rewrite_rules = $GLOBALS['wp_rewrite']->extra_rules_top;

ptid_studio_assert_same(
    true,
    isset($rewrite_rules['^(?:[^/]+/)*(post)/([0-9]+)/?$']),
    'Post ID rewrite rule must be registered.'
);
ptid_studio_assert_same(
    true,
    isset($rewrite_rules['^(?:[^/]+/)*(category)/([0-9]+)/?$']),
    'Taxonomy ID rewrite rule must be registered.'
);

$legacy_settings = array(
    'structure' => 'unexpected-format',
    'post_types' => 'not-an-array',
    'taxonomies' => array('category', 'category'),
    'redirect_legacy' => false,
);
update_option($settings_option, $legacy_settings);

$read_only_plugin = new PTID_Permalink_Plugin();
$get_settings = new ReflectionMethod(PTID_Permalink_Plugin::class, 'get_settings');
$get_settings->setAccessible(true);
$get_settings->invoke($read_only_plugin);

ptid_studio_assert_same(
    $legacy_settings,
    get_option($settings_option),
    'Reading settings must not persist normalized values.'
);

$read_only_plugin->normalize_stored_settings();

ptid_studio_assert_same(
    array(
        'structure' => 'slash',
        'post_types' => array(),
        'taxonomies' => array('category'),
        'redirect_legacy' => false,
    ),
    get_option($settings_option),
    'Admin-side normalization must persist the normalized settings.'
);

echo "Slug-Free Permalinks Studio smoke tests passed.\n";
