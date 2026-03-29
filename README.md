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

It is a better fit for new sites, structured-content setups, or projects that are still deciding their permalink policy.

If a site already has a large volume of published content and established slug-based URLs, review the migration impact carefully before enabling it. Existing inbound links, search traffic, social shares, and editorial workflow assumptions may all be affected.

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

These minimum versions are based on the PHP syntax and WordPress APIs used by the plugin.

## Installation

1. In the WordPress admin screen, go to `Plugins > Add New`.
2. Search for `Slug-Free Permalinks`.
3. Click `Install Now`, then activate the plugin.
4. Go to `Settings > Slug-Free Permalinks`.
5. Choose the permalink format and the target post types or taxonomies.

For manual installation, upload the `slug-free-permalinks` folder to `/wp-content/plugins/` and activate it from the `Plugins` screen.

## Notes

- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
- Contributor and release workflow notes are documented in [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
