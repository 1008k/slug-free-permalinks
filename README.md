# Slug-Free Permalinks

Japanese: [README-ja.md](README-ja.md)

Slug-Free Permalinks is a WordPress plugin that switches selected post types and taxonomies to ID-based permalinks without using slugs.

## Features

- Select individual public post types with UI support
- Select individual public taxonomies with UI support
- Choose `/post/123/` or `/post-123/`
- Optionally redirect legacy slug URLs to the current ID-based permalink
- Preserve language or path prefixes already added by permalink plugins such as Polylang
- Flush rewrite rules automatically when settings change

## FAQ

**Does the plugin work with pages?**

No. Pages are intentionally excluded to avoid conflicts with common WordPress page structures and existing permalink configurations.

The plugin focuses on posts, custom post types, and taxonomies where ID-based permalinks are more predictable.

---

**Does it redirect every old slug URL?**

No. Slug-Free Permalinks intentionally avoids aggressive 404-based slug guessing.

Redirects only run when WordPress can already resolve the legacy request. This design keeps redirects lightweight, predictable, and compatible with standard WordPress routing.

---

**Why doesn't the plugin attempt slug lookups on every 404?**

Performing slug lookups for every 404 request can introduce unnecessary database queries, especially on large sites or when bots crawl invalid URLs.

Slug-Free Permalinks prioritizes performance and reliability over aggressive URL guessing.

---

**Can a post type and taxonomy share the same slug?**

This is not recommended.

If a custom post type and a taxonomy share the same slug, WordPress rewrite rules may conflict. Using distinct slugs for post types and taxonomies avoids ambiguity.

---

**Does it work with Polylang or language-directory URLs such as `/en/`?**

Yes. The canonical ID-based permalink stays rooted at the site home, and language-directory plugins can add their own prefix on top of that.

For example, the plugin keeps using `/post/123/` as the base shape, while Polylang style setups can expose `/en/post/123/` or `/en/category/45/`.

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
- `dist/` is a local build output and is not tracked in Git.
- Build the distributable plugin into `dist/slug-free-permalinks` with `node scripts/build-dist.mjs`.
- Build the versioned release ZIP with `node scripts/build-dist.mjs --zip`.
- Create a GitHub release with `node scripts/create-github-release.mjs`.
- Run Plugin Check against `dist/slug-free-permalinks` when preparing a release.

## Notes

- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
- WordPress.org distribution metadata is maintained in `readme.txt`.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
