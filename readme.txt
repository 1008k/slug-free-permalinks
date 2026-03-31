=== Slug-Free Permalinks - Simple ID-Based URLs ===
Contributors: cck23
Tags: permalinks, slugs, custom post types, taxonomy, urls
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use ID based permalinks for selected post types and taxonomies without managing slugs.

== Description ==

Slug-Free Permalinks lets you switch selected post types and taxonomies to simple ID based permalink formats.

You can choose between:

* `/post/123/`
* `/post-123/`

The plugin only affects the post types and taxonomies you enable in the settings screen.

Features:

* Select individual public post types
* Select individual public taxonomies
* Choose slash or hyphen based ID permalink format
* Optionally redirect legacy slug URLs to the current ID based permalink when WordPress can resolve the request
* Preserve prefixed permalink bases such as `/en/` when another plugin adds them
* Flush rewrite rules automatically when settings change

This plugin is focused on permalink structure only. It does not add content features or front-end UI.

Plugin page (English): https://happas.jp/en/slug-free-permalinks/
Plugin page (Japanese): https://happas.jp/slug-free-permalinks/

Development repository: https://github.com/1008k/slug-free-permalinks

This plugin is best suited to new sites, sites still defining their permalink policy, or structured-content use cases where slug management is unnecessary.

If your site already has a large number of published posts and established slug based URLs, review the impact carefully before enabling it. Check existing inbound links, search traffic, social shares, and editorial workflow assumptions.

Known limitations:

* If a post type slug and taxonomy slug are identical, their ID based rewrite patterns can conflict.

== Installation ==

1. In the WordPress admin screen, go to `Plugins > Add New`.
2. Search for `Slug-Free Permalinks`.
3. Click `Install Now`, then activate the plugin.
4. Go to `Settings > Slug-Free Permalinks`.
5. Choose a permalink format.
6. Check the post types and taxonomies you want to convert to ID based permalinks.
7. Optionally enable legacy permalink redirects.
8. Save changes.

If you prefer manual installation, upload the plugin folder to `/wp-content/plugins/` and activate it from the `Plugins` screen.

== Frequently Asked Questions ==

= Does this change every post type automatically? =

No. Only the post types and taxonomies you check in the settings screen are affected.

= Does it redirect every old slug URL? =

No. Slug-Free Permalinks avoids aggressive 404 slug guessing.
Redirects only run when WordPress can already resolve the request.

This keeps the plugin lightweight and predictable.

= Why not attempt slug lookups for every 404? =

Performing slug lookups on every 404 can introduce unnecessary database queries and unpredictable behavior.

The plugin prioritizes performance and compatibility with WordPress routing.

= Does it support pages? =

No. Pages are intentionally excluded to avoid conflicts with typical WordPress page permalink structures.

= Does it support taxonomies too? =

Yes. Public taxonomies with UI support can be switched to the same ID based format.

= Can a post type and taxonomy share the same slug? =

This is not recommended.

If a custom post type and a taxonomy share the same slug, WordPress rewrite rules may conflict.

= Does it work with Polylang or language-directory URLs such as `/en/`? =

Yes. The canonical ID based permalink stays rooted at the site home, and language-directory plugins can add their own prefix on top of that.

For example, the plugin keeps using `/post/123/` as the base shape, while Polylang style setups can expose `/en/post/123/` and `/en/category/45/`.

== Screenshots ==

1. Settings screen for choosing the ID permalink format, target post types, target taxonomies, and optional legacy redirect behavior.

== Changelog ==

= 1.4.6 =

* Confirm compatibility with WordPress 7.0

= 1.4.5 =

* Improve internal permalink handling consistency

= 1.4.4 =

* Keep canonical ID permalinks consistent with or without Polylang
* Continue supporting language-directory prefixes such as `/en/`

= 1.4.3 =

* Preserve Polylang and language-directory permalink prefixes for ID-based URLs
* Accept prefixed ID routes such as `/en/post/123/` and `/en/category/45/`

= 1.4.2 =

* Add a guarded Japanese l10n PHP translation file for Plugin Check compatibility
* Update distribution package for the latest Plugin Check fixes

= 1.4.1 =

* Remove unnecessary manual translation loading to satisfy current Plugin Check guidance
* Refine FAQ and release packaging workflow

= 1.4.0 =

* Rebrand plugin as Slug-Free Permalinks
* Add WordPress.org readme and distribution metadata
* Add optional legacy slug redirect setting

= 1.3.4 =

* Add optional redirect from legacy slug URLs to the current ID based permalink

= 1.3.3 =

* Add taxonomy support and selectable slash or hyphen formats
