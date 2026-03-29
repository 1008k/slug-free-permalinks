# Slug-Free Permalinks

Japanese: [README-ja.md](README-ja.md)

Slug-Free Permalinks is a WordPress plugin that switches selected post types and taxonomies to ID-based permalinks without using slugs.

## Why This Plugin Exists

WordPress slugs are useful, but in day-to-day operation they can become unnecessary overhead.

This plugin is aimed at sites where:

- editors do not want to think about slugs every time they publish posts or add categories and tags
- multibyte characters can make URLs long and hard to read when they are encoded
- changing a title later should not leave the URL and the content title out of sync

Slug-Free Permalinks switches selected post types and taxonomies to stable ID-based URLs, so permalink management stays simple and less dependent on titles or language-specific slugs.

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

1. In the WordPress admin screen, go to `Plugins > Add New`.
2. Search for `Slug-Free Permalinks`.
3. Click `Install Now`, then activate the plugin.
4. Go to `Settings > Slug-Free Permalinks`.
5. Choose the permalink format and the target post types or taxonomies.

For manual installation, upload the `slug-free-permalinks` folder to `/wp-content/plugins/` and activate it from the `Plugins` screen.

## Distribution

- Source files live in the repository root.
- `dist/` is a local build output and is not tracked in Git.
- Build the distributable plugin into `dist/slug-free-permalinks` with `node scripts/build-dist.mjs`.
- Build the versioned release ZIP with `node scripts/build-dist.mjs --zip`.
- GitHub Actions runs Plugin Check against `dist/slug-free-permalinks` on pull requests and on pushes to `main`.
- Push a Git tag such as `1.4.4` to trigger automatic WordPress.org deployment from GitHub Actions.
- The deploy workflow only accepts tags in `X.Y.Z` format.
- The workflow validates that the Git tag, `Version:` in `slug-free-permalinks.php`, and `Stable tag:` in `readme.txt` all match exactly.
- `scripts/create-github-release.mjs` creates a GitHub Release using the same version tag convention.
- Run Plugin Check against `dist/slug-free-permalinks` when preparing a release.

## WordPress.org Assets

- Add optional WordPress.org assets in `.wordpress-org/`.
- Common filenames are `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, and `screenshot-1.png`.
- The deploy workflow syncs `.wordpress-org/` to the WordPress.org `assets/` directory when those files exist.

## Notes

- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
- WordPress.org distribution metadata is maintained in `readme.txt`.
- WordPress.org deployment uses the built artifact in `dist/slug-free-permalinks`, not the repository root.
- WordPress.org icons, banners, and screenshots are optional and can be added later in a `.wordpress-org/` directory.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
