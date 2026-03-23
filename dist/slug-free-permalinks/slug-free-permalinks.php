<?php
/*
Plugin Name: Slug-Free Permalinks
Description: Use ID based permalinks for selected post types and taxonomies without managing slugs.
Version: 1.4.3
Requires at least: 5.8
Requires PHP: 7.4
Author: happas
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: slug-free-permalinks
Domain Path: /languages
*/

if (! defined('ABSPATH')) {
    exit;
}

final class PTID_Permalink_Plugin
{
    private const OPTION_NAME = 'ptid_permalink_settings';
    private const MENU_SLUG = 'ptid-permalink-settings';
    private ?array $settings_cache = null;
    private ?array $enabled_post_types_cache = null;
    private ?array $enabled_taxonomies_cache = null;
    private ?string $permalink_structure_cache = null;

    public static function bootstrap(): void
    {
        $instance = new self();
        add_action('init', array($instance, 'register_rewrite_rules'));
        add_filter('post_link', array($instance, 'filter_permalink'), 10, 2);
        add_filter('post_type_link', array($instance, 'filter_permalink'), 10, 2);
        add_filter('term_link', array($instance, 'filter_term_link'), 10, 3);
        add_filter('query_vars', array($instance, 'register_query_vars'));
        add_action('parse_request', array($instance, 'resolve_term_request'));
        add_action('template_redirect', array($instance, 'redirect_legacy_permalink'));
        add_action('admin_init', array($instance, 'register_settings'));
        add_action('admin_menu', array($instance, 'register_settings_page'));
        add_filter(
            'plugin_action_links_' . plugin_basename(__FILE__),
            array($instance, 'add_settings_link')
        );
    }

    public static function activate(): void
    {
        if (! get_option(self::OPTION_NAME)) {
            add_option(self::OPTION_NAME, self::default_settings());
        }

        $plugin = new self();
        $plugin->register_rewrite_rules();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function filter_permalink(string $post_link, WP_Post $post): string
    {
        if (is_admin() || ! $this->is_feature_enabled()) {
            return $post_link;
        }

        if (! in_array($post->post_status, array('publish', 'private'), true)) {
            return $post_link;
        }

        if (! in_array($post->post_type, $this->get_enabled_post_types(), true)) {
            return $post_link;
        }

        return $this->build_content_permalink(
            $post_link,
            $post->post_type,
            $post->ID,
            $this->get_polylang_home_url_for_post($post->ID)
        );
    }

    public function filter_term_link(string $term_link, WP_Term $term, string $taxonomy): string
    {
        if (is_admin() || ! $this->is_feature_enabled()) {
            return $term_link;
        }

        if (! in_array($taxonomy, $this->get_enabled_taxonomies(), true)) {
            return $term_link;
        }

        return $this->build_content_permalink(
            $term_link,
            $taxonomy,
            $term->term_id,
            $this->get_polylang_home_url_for_term($term->term_id)
        );
    }

    public function register_rewrite_rules(): void
    {
        $this->register_rewrite_rules_for(
            $this->get_permalink_structure(),
            $this->get_enabled_post_types(),
            $this->get_enabled_taxonomies(),
            $this->is_feature_enabled()
        );
    }

    public function register_query_vars(array $query_vars): array
    {
        $query_vars[] = 'ptid_taxonomy';
        $query_vars[] = 'ptid_term_id';

        return $query_vars;
    }

    public function resolve_term_request(WP $wp): void
    {
        if (! $this->is_feature_enabled()) {
            return;
        }

        $taxonomy = sanitize_key((string) ($wp->query_vars['ptid_taxonomy'] ?? ''));
        $term_id = absint($wp->query_vars['ptid_term_id'] ?? 0);

        if ($taxonomy === '' || $term_id < 1) {
            return;
        }

        if (! in_array($taxonomy, $this->get_enabled_taxonomies(), true)) {
            return;
        }

        $term = get_term($term_id, $taxonomy);
        if (! ($term instanceof WP_Term) || is_wp_error($term)) {
            return;
        }

        $wp->query_vars['taxonomy'] = $taxonomy;
        $wp->query_vars['term'] = $term->slug;

        $taxonomy_object = get_taxonomy($taxonomy);
        if ($taxonomy_object && is_string($taxonomy_object->query_var) && $taxonomy_object->query_var !== '') {
            $wp->query_vars[$taxonomy_object->query_var] = $term->slug;
        }
    }

    public function register_settings(): void
    {
        register_setting(
            'ptid_permalink_settings_group',
            self::OPTION_NAME,
            array($this, 'sanitize_settings')
        );
    }

    public function register_settings_page(): void
    {
        add_options_page(
            __('Slug-Free Permalinks', 'slug-free-permalinks'),
            __('Slug-Free Permalinks', 'slug-free-permalinks'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_settings_page')
        );
    }

    public function add_settings_link(array $links): array
    {
        $url = admin_url('options-general.php?page=' . self::MENU_SLUG);
        array_unshift(
            $links,
            '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'slug-free-permalinks') . '</a>'
        );

        return $links;
    }

    public function sanitize_settings($input): array
    {
        $previous = $this->get_settings();
        $post_types = array();
        $taxonomies = array();

        if (! empty($input['post_types']) && is_array($input['post_types'])) {
            $allowed = array_keys($this->get_available_post_types());
            foreach ($input['post_types'] as $post_type) {
                $post_type = sanitize_key($post_type);
                if (in_array($post_type, $allowed, true)) {
                    $post_types[] = $post_type;
                }
            }
        }

        if (! empty($input['taxonomies']) && is_array($input['taxonomies'])) {
            $allowed = array_keys($this->get_available_taxonomies());
            foreach ($input['taxonomies'] as $taxonomy) {
                $taxonomy = sanitize_key($taxonomy);
                if (in_array($taxonomy, $allowed, true)) {
                    $taxonomies[] = $taxonomy;
                }
            }
        }

        $settings = array(
            'structure' => $this->sanitize_structure($input['structure'] ?? ''),
            'post_types' => array_values(array_unique($post_types)),
            'taxonomies' => array_values(array_unique($taxonomies)),
            'redirect_legacy' => ! empty($input['redirect_legacy']),
        );

        if ($previous !== $settings) {
            $this->register_rewrite_rules_for(
                $settings['structure'],
                $settings['post_types'],
                $settings['taxonomies'],
                $this->has_enabled_targets($settings)
            );
            flush_rewrite_rules();
        }

        $this->prime_settings_cache($settings);

        return $settings;
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $post_types = $this->get_available_post_types();
        $taxonomies = $this->get_available_taxonomies();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Slug-Free Permalinks', 'slug-free-permalinks'); ?></h1>
            <p><?php echo esc_html__('Checked post types and taxonomies will use the selected ID based permalink format. Clear all checks to disable. Rewrite rules are flushed automatically when settings change.', 'slug-free-permalinks'); ?></p>

            <form action="options.php" method="post">
                <?php settings_fields('ptid_permalink_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Permalink format', 'slug-free-permalinks'); ?></th>
                            <td>
                                <select name="<?php echo esc_attr(self::OPTION_NAME); ?>[structure]">
                                    <option value="slash" <?php selected($settings['structure'], 'slash'); ?>>
                                        <?php echo esc_html('/post/123/'); ?>
                                    </option>
                                    <option value="hyphen" <?php selected($settings['structure'], 'hyphen'); ?>>
                                        <?php echo esc_html('/post-123/'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Target post types', 'slug-free-permalinks'); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ($post_types as $post_type => $object) : ?>
                                        <label style="display:block; margin-bottom:0.5rem;">
                                            <input
                                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[post_types][]"
                                                type="checkbox"
                                                value="<?php echo esc_attr($post_type); ?>"
                                                <?php checked(in_array($post_type, $settings['post_types'], true)); ?>
                                            />
                                            <?php echo esc_html($object->labels->singular_name . ' (' . $post_type . ')'); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Target taxonomies', 'slug-free-permalinks'); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ($taxonomies as $taxonomy => $object) : ?>
                                        <label style="display:block; margin-bottom:0.5rem;">
                                            <input
                                                name="<?php echo esc_attr(self::OPTION_NAME); ?>[taxonomies][]"
                                                type="checkbox"
                                                value="<?php echo esc_attr($taxonomy); ?>"
                                                <?php checked(in_array($taxonomy, $settings['taxonomies'], true)); ?>
                                            />
                                            <?php echo esc_html($object->labels->singular_name . ' (' . $taxonomy . ')'); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Redirect legacy permalinks', 'slug-free-permalinks'); ?></th>
                            <td>
                                <label>
                                    <input
                                        name="<?php echo esc_attr(self::OPTION_NAME); ?>[redirect_legacy]"
                                        type="checkbox"
                                        value="1"
                                        <?php checked(! empty($settings['redirect_legacy'])); ?>
                                    />
                                    <?php echo esc_html__('Redirect old slug based URLs to the current ID based permalink when WordPress can resolve the request.', 'slug-free-permalinks'); ?>
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

    public function redirect_legacy_permalink(): void
    {
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

        if (is_singular()) {
            $post = get_queried_object();

            if (! ($post instanceof WP_Post)) {
                return;
            }

            if (! in_array($post->post_type, $this->get_enabled_post_types(), true)) {
                return;
            }

            if (! in_array($post->post_status, array('publish', 'private'), true)) {
                return;
            }

            $target_url = get_permalink($post);
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();

            if (! ($term instanceof WP_Term)) {
                return;
            }

            if (! in_array($term->taxonomy, $this->get_enabled_taxonomies(), true)) {
                return;
            }

            $term_link = get_term_link($term);
            if (is_wp_error($term_link)) {
                return;
            }

            $target_url = $term_link;
        } else {
            return;
        }

        if (! is_string($target_url) || $target_url === '') {
            return;
        }

        $current_url = $this->get_current_request_url();

        if ($current_url === '' || ! $this->should_redirect_to_target($current_url, $target_url)) {
            return;
        }

        wp_safe_redirect($this->append_current_query_args($target_url, $current_url), 301, 'Post Type ID Permalink');
        exit;
    }

    private static function default_settings(): array
    {
        return array(
            'structure' => 'slash',
            'post_types' => array(),
            'taxonomies' => array(),
            'redirect_legacy' => false,
        );
    }

    private function get_settings(): array
    {
        if (is_array($this->settings_cache)) {
            return $this->settings_cache;
        }

        $settings = get_option(self::OPTION_NAME, array());
        $normalized = $this->normalize_settings(is_array($settings) ? $settings : array());

        if (is_array($settings) && $settings !== $normalized) {
            update_option(self::OPTION_NAME, $normalized);
        }

        return $this->prime_settings_cache($normalized);
    }

    private function is_feature_enabled(): bool
    {
        return $this->has_enabled_targets($this->get_settings());
    }

    private function get_enabled_post_types(): array
    {
        if (is_array($this->enabled_post_types_cache)) {
            return $this->enabled_post_types_cache;
        }

        $settings = $this->get_settings();
        $this->enabled_post_types_cache = array_values(
            array_filter(array_map('sanitize_key', (array) $settings['post_types']))
        );

        return $this->enabled_post_types_cache;
    }

    private function get_enabled_taxonomies(): array
    {
        if (is_array($this->enabled_taxonomies_cache)) {
            return $this->enabled_taxonomies_cache;
        }

        $settings = $this->get_settings();
        $this->enabled_taxonomies_cache = array_values(
            array_filter(array_map('sanitize_key', (array) $settings['taxonomies']))
        );

        return $this->enabled_taxonomies_cache;
    }

    private function get_available_post_types(): array
    {
        $post_types = get_post_types(
            array(
                'public' => true,
                'show_ui' => true,
            ),
            'objects'
        );

        unset($post_types['attachment'], $post_types['page']);

        return $post_types;
    }

    private function get_available_taxonomies(): array
    {
        $taxonomies = get_taxonomies(
            array(
                'public' => true,
                'show_ui' => true,
            ),
            'objects'
        );

        unset($taxonomies['post_format']);

        return $taxonomies;
    }

    private function get_permalink_structure(): string
    {
        if (is_string($this->permalink_structure_cache)) {
            return $this->permalink_structure_cache;
        }

        $settings = $this->get_settings();
        $this->permalink_structure_cache = $this->sanitize_structure($settings['structure'] ?? '');

        return $this->permalink_structure_cache;
    }

    private function sanitize_structure($structure): string
    {
        return $structure === 'hyphen' ? 'hyphen' : 'slash';
    }

    private function normalize_settings(array $settings): array
    {
        $defaults = self::default_settings();

        return array(
            'structure' => $this->sanitize_structure($settings['structure'] ?? $defaults['structure']),
            'post_types' => array_values(
                array_filter(array_map('sanitize_key', (array) ($settings['post_types'] ?? $defaults['post_types'])))
            ),
            'taxonomies' => array_values(
                array_filter(array_map('sanitize_key', (array) ($settings['taxonomies'] ?? $defaults['taxonomies'])))
            ),
            'redirect_legacy' => ! empty($settings['redirect_legacy']),
        );
    }

    private function build_id_path(string $slug, int $id): string
    {
        if ($this->get_permalink_structure() === 'hyphen') {
            return $slug . '-' . $id;
        }

        return $slug . '/' . $id;
    }

    private function build_content_permalink(string $existing_url, string $slug, int $id, string $language_home_url = ''): string
    {
        $relative_id_path = user_trailingslashit($this->build_id_path($slug, $id));

        if ($language_home_url !== '') {
            $language_url = $this->join_url_path($language_home_url, $relative_id_path);
            if ($language_url !== '') {
                return $language_url;
            }
        }

        $updated_url = $this->replace_url_path_suffix($existing_url, $slug, $relative_id_path);

        if ($updated_url !== '') {
            return $updated_url;
        }

        return home_url($relative_id_path);
    }

    private function has_enabled_targets(array $settings): bool
    {
        return $settings['post_types'] !== array() || $settings['taxonomies'] !== array();
    }

    private function get_polylang_home_url_for_post(int $post_id): string
    {
        if (! function_exists('pll_get_post_language') || ! function_exists('pll_home_url')) {
            return '';
        }

        $language = pll_get_post_language($post_id, 'slug');

        if (! is_string($language) || $language === '') {
            return '';
        }

        $home_url = pll_home_url($language);

        return is_string($home_url) ? $home_url : '';
    }

    private function get_polylang_home_url_for_term(int $term_id): string
    {
        if (! function_exists('pll_get_term_language') || ! function_exists('pll_home_url')) {
            return '';
        }

        $language = pll_get_term_language($term_id, 'slug');

        if (! is_string($language) || $language === '') {
            return '';
        }

        $home_url = pll_home_url($language);

        return is_string($home_url) ? $home_url : '';
    }

    private function should_redirect_legacy_requests(): bool
    {
        $settings = $this->get_settings();

        return ! empty($settings['redirect_legacy']);
    }

    private function get_current_request_url(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']))
            : '';
        $host = isset($_SERVER['HTTP_HOST'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']))
            : '';

        if ($request_uri === '' || $host === '') {
            return '';
        }

        return (is_ssl() ? 'https://' : 'http://') . $host . $request_uri;
    }

    private function should_redirect_to_target(string $current_url, string $target_url): bool
    {
        return $this->normalize_url_for_compare($current_url) !== $this->normalize_url_for_compare($target_url);
    }

    private function normalize_url_for_compare(string $url): string
    {
        $parts = wp_parse_url($url);

        if (! is_array($parts)) {
            return untrailingslashit($url);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = isset($parts['path']) ? user_trailingslashit(ltrim((string) $parts['path'], '/')) : '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';

        return $host . '|' . $path . $query;
    }

    private function append_current_query_args(string $target_url, string $current_url): string
    {
        $query = wp_parse_url($current_url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return $target_url;
        }

        parse_str($query, $query_args);

        if ($query_args === array()) {
            return $target_url;
        }

        return add_query_arg($query_args, $target_url);
    }

    private function replace_url_path_suffix(string $url, string $slug, string $relative_id_path): string
    {
        $parts = wp_parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $path = isset($parts['path']) ? (string) $parts['path'] : '';

        if ($path === '') {
            return '';
        }

        $parts['path'] = $this->replace_path_suffix($path, $slug, $relative_id_path);

        return $this->build_url_from_parts($parts);
    }

    private function replace_path_suffix(string $path, string $slug, string $relative_id_path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        $prefix_segments = $this->extract_prefix_segments($segments, $slug);
        $id_segments = array_values(array_filter(explode('/', trim($relative_id_path, '/')), 'strlen'));
        $new_segments = array_merge($prefix_segments, $id_segments);

        if ($new_segments === array()) {
            return '/';
        }

        return '/' . ltrim(user_trailingslashit(implode('/', $new_segments)), '/');
    }

    private function extract_prefix_segments(array $segments, string $slug): array
    {
        $slug_index = false;

        for ($index = count($segments) - 1; $index >= 0; $index--) {
            if ($segments[$index] === $slug) {
                $slug_index = $index;
                break;
            }
        }

        if ($slug_index !== false) {
            return array_slice($segments, 0, (int) $slug_index);
        }

        if (count($segments) <= 1) {
            return array();
        }

        return array_slice($segments, 0, -1);
    }

    private function join_url_path(string $base_url, string $relative_path): string
    {
        $parts = wp_parse_url($base_url);

        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $base_path = isset($parts['path']) ? trim((string) $parts['path'], '/') : '';
        $new_path = trim($relative_path, '/');

        if ($base_path !== '') {
            $new_path = $base_path . '/' . $new_path;
        }

        $parts['path'] = '/' . ltrim(user_trailingslashit($new_path), '/');

        return $this->build_url_from_parts($parts);
    }

    private function build_url_from_parts(array $parts): string
    {
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : (is_ssl() ? 'https://' : 'http://');
        $user = isset($parts['user']) ? (string) $parts['user'] : '';
        $pass = isset($parts['pass']) ? ':' . (string) $parts['pass'] : '';
        $auth = $user !== '' ? $user . $pass . '@' : '';
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . (string) $parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . (string) $parts['fragment'] : '';

        return $scheme . $auth . $host . $port . $path . $query . $fragment;
    }

    private function register_rewrite_rules_for(string $structure, array $post_types, array $taxonomies, bool $enabled): void
    {
        if (! $enabled) {
            return;
        }

        $separator = $structure === 'hyphen' ? '-' : '/';
        $prefix_pattern = '^(?:[^/]+/)*';

        if ($post_types !== array()) {
            $pattern = implode('|', array_map('preg_quote', $post_types));
            add_rewrite_rule(
                $prefix_pattern . '(' . $pattern . ')' . $separator . '([0-9]+)/?$',
                'index.php?post_type=$matches[1]&p=$matches[2]',
                'top'
            );
        }

        if ($taxonomies !== array()) {
            $pattern = implode('|', array_map('preg_quote', $taxonomies));
            add_rewrite_rule(
                $prefix_pattern . '(' . $pattern . ')' . $separator . '([0-9]+)/?$',
                'index.php?ptid_taxonomy=$matches[1]&ptid_term_id=$matches[2]',
                'top'
            );
        }
    }

    private function prime_settings_cache(array $settings): array
    {
        $this->settings_cache = wp_parse_args($settings, self::default_settings());
        $this->enabled_post_types_cache = null;
        $this->enabled_taxonomies_cache = null;
        $this->permalink_structure_cache = null;

        return $this->settings_cache;
    }
}

PTID_Permalink_Plugin::bootstrap();
register_activation_hook(__FILE__, array('PTID_Permalink_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('PTID_Permalink_Plugin', 'deactivate'));
