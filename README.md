# Slug-Free Permalinks

Japanese: [README-ja.md](README-ja.md)

Slug-Free Permalinks is a WordPress plugin that switches selected post types and taxonomies to ID-based permalinks without using slugs.

## Features

- Select individual public post types with UI support
- Select individual public taxonomies with UI support
- Choose `/post/123/` or `/post-123/`
- Optionally redirect legacy slug URLs to the current ID-based permalink
- Flush rewrite rules automatically when settings change

## Requirements

- WordPress 5.8 or later
- PHP 7.4 or later

These minimum versions are based on the PHP syntax and WordPress APIs used by the plugin. The current `readme.txt` is marked as tested up to WordPress 6.9.

## Installation

1. Download this repository as a ZIP, or place it as `slug-free-permalinks` under `/wp-content/plugins/`.
2. Activate the plugin from the WordPress `Plugins` screen.
3. Go to `Settings > Slug-Free Permalinks`.
4. Choose the permalink format and the target post types or taxonomies.

## Distribution

- Source files live in the repository root.
- Build the distributable plugin into `dist/slug-free-permalinks` with `node scripts/build-dist.mjs`.
- Run Plugin Check against `dist/slug-free-permalinks` when preparing a release.

## Notes

- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
- WordPress.org distribution metadata is maintained in `readme.txt`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
