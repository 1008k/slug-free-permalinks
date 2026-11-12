# Slug-Free Permalinks

Japanese: [README-ja.md](README-ja.md)

Plugin page: [English](https://happas.jp/en/slug-free-permalinks/) | [Japanese](https://happas.jp/slug-free-permalinks/)

WordPress.org: [Plugin directory](https://wordpress.org/plugins/slug-free-permalinks/)

Changelog: [English](CHANGELOG.en.md) | [Japanese](CHANGELOG.md)

Slug-Free Permalinks uses WordPress's native permalinks for regular posts and adds ID-based URLs for selected custom post types and taxonomies.

## Why This Plugin Exists

WordPress slugs are useful, but in day-to-day operation they can become unnecessary overhead.

This plugin is aimed at sites where:

- editors do not want to think about slugs every time they publish posts or add categories and tags
- multibyte characters can make URLs long and hard to read when they are encoded
- changing a title later should not leave the URL and the content title out of sync

Slug-Free Permalinks uses native WordPress permalink settings for regular posts and lets you give selected custom post types and taxonomies stable ID-based URLs.

It is a better fit for new sites, structured-content setups, or projects that are still deciding their permalink policy.

If a site already has a large volume of published content and established slug-based URLs, review the migration impact carefully before enabling it. Existing inbound links, search traffic, social shares, and editorial workflow assumptions may all be affected.

## Features

- Select individual public custom post types with UI support
- Select individual public taxonomies with UI support
- Use each selected type's registered rewrite slug for the ID-based route
- Choose slash or hyphen ID URLs for custom post types and taxonomies
- Optionally redirect legacy slug URLs to the current ID-based permalink
- Preserve Polylang language URLs using the language home URLs Polylang actually exposes
- View WordPress's native post permalink structure and explicitly migrate legacy regular post ID URLs
- Flush rewrite rules automatically when settings change

## FAQ

**Does the plugin work with pages?**

No. Pages are intentionally excluded to avoid conflicts with common WordPress page structures and existing permalink configurations.

Regular posts use WordPress's own permalink settings. The plugin provides ID-based URLs for custom post types and taxonomies.

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

The settings screen rejects a selection where a post type and taxonomy share the same registered rewrite slug. Using distinct slugs keeps the ID-based routes unambiguous.

---

**Does it work with Polylang or language-directory URLs such as `/en/`?**

Yes. Polylang integration uses the language home URL returned by Polylang instead of accepting arbitrary path prefixes.

For example, when Polylang exposes `/en/` as a language path, Slug-Free Permalinks registers `/en/post/123/` and `/en/category/45/` alongside the base routes. Domain-based or query-based language modes continue to use the URL returned by Polylang without inventing an extra path prefix.

**Can I let WordPress manage regular post ID URLs?**

Yes. The Regular posts section offers an explicit migration for sites still using the legacy plugin URL format, moving to WordPress's native permalink structure using `%post_id%`. The migration preserves the current slash or hyphen ID URL format and removes regular posts from the plugin's selected targets. Custom post types and taxonomies remain under their existing plugin settings. **The action changes the site-wide WordPress permalink structure**, which can also affect custom post type URLs. Back up the site and verify existing URLs and redirects before migrating.

**How can I deactivate the plugin without changing regular post URLs?**

When regular posts are enabled, the settings screen shows the equivalent WordPress Custom Structure for the current ID URL format, such as `/post/%post_id%/` or `/post-%post_id%/`. The displayed value follows the site's current trailing-slash policy. Use the migration button to switch to WordPress's native structure before deactivating the plugin.

Custom post types and taxonomies have their own rewrite settings and need to be checked separately before deactivation.

---

## Requirements

- WordPress 6.4 or later
- Tested through WordPress 7.1
- PHP 8.2 or later

These are the supported minimum versions. Older WordPress and PHP releases are outside the support scope.

## Installation

1. In the WordPress admin screen, go to `Plugins > Add New`.
2. Search for `Slug-Free Permalinks`.
3. Click `Install Now`, then activate the plugin.
4. Go to `Settings > Slug-Free Permalinks`.
5. Use WordPress permalink settings for regular posts. In the plugin settings, choose the ID format and custom post types or taxonomies to convert.

For manual installation, upload the `slug-free-permalinks` folder to `/wp-content/plugins/` and activate it from the `Plugins` screen.

## Notes

- The settings screen rejects selected post types or taxonomies with identical registered rewrite slugs.
- Only path prefixes exposed by Polylang language home URLs are registered as prefixed ID routes; arbitrary prefixes are not accepted.
- Contributor and release workflow notes are documented in [CONTRIBUTING.md](CONTRIBUTING.md).

## Support

Slug-Free Permalinks is completely free to use. If you find it useful, you're welcome to support the project on GitHub Sponsors.

[Support the project on GitHub Sponsors](https://github.com/sponsors/1008k)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
