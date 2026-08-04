=== Slug-Free Permalinks - Simple ID-Based URLs ===
Contributors: cck23
Tags: permalinks, slugs, custom post types, taxonomy, urls
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use ID-based permalinks for selected post types and taxonomies without managing slugs.

== Description ==

Choosing a new slug for every post, term, or language version is extra URL design work. Slug-Free Permalinks lets you move that work out of the publishing flow by using an item's ID in the permalink instead.

Select the public post types and taxonomies you want to change, choose a URL format, and keep the rest of your site on its existing permalink structure. The plugin is intentionally focused: it changes URL structure without adding content features or front-end UI.

== Why use ID-based permalinks? ==

* Reduce the number of slug decisions and maintenance steps as your content grows.
* Keep the URL pattern consistent even when titles, translations, or editorial wording change.
* Apply the change selectively instead of switching every post type at once.

You can choose between:

* `/post/123/`
* `/post-123/`

The plugin only affects the post types and taxonomies you enable in the settings screen.

What you can configure:

* Select individual public post types
* Select individual public taxonomies
* Use each selected type's registered rewrite slug for the ID-based route
* Choose slash or hyphen based ID permalink format
* Optionally redirect legacy slug URLs to the current ID-based permalink when WordPress can resolve the request
* Preserve prefixed permalink bases such as `/en/` when another plugin adds them
* Flush rewrite rules automatically when settings change

== A focused, predictable change ==

Legacy slug redirects are optional and only run when WordPress can already resolve the request. Slug-Free Permalinks does not guess at every 404, so it avoids turning an ID-based permalink plugin into a site-wide slug lookup system.

The plugin is intended for new sites, structured-content sites, or projects that are still defining a permalink policy.

If your site already has many established slug-based URLs, review existing inbound links, search traffic, social shares, and editorial workflow assumptions before enabling it.

* [Plugin page (English)](https://happas.jp/en/slug-free-permalinks/)
* [Plugin page (Japanese)](https://happas.jp/slug-free-permalinks/)
* [Development repository](https://github.com/1008k/slug-free-permalinks)

Known limitations:

* The settings screen rejects selected post types or taxonomies with identical registered rewrite slugs.
* Prefixed ID routes reserve the matching path shape, so language or path prefixes should not overlap existing page routes.

== Installation ==

1. In the WordPress admin screen, go to `Plugins > Add New`.
2. Search for `Slug-Free Permalinks`.
3. Click `Install Now`, then activate the plugin.
4. Go to `Settings > Slug-Free Permalinks`.
5. Choose a permalink format.
6. Check the post types and taxonomies you want to convert to ID-based permalinks.
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

Yes. Public taxonomies with UI support can be switched to the same ID-based format.

= Can a post type and taxonomy share the same slug? =

The settings screen rejects selected post types or taxonomies with identical registered rewrite slugs, keeping the ID-based routes unambiguous.

= Does it work with Polylang or language-directory URLs such as `/en/`? =

Yes. The canonical ID-based permalink stays rooted at the site home, and language-directory plugins can add their own prefix on top of that.

For example, the plugin keeps using `/post/123/` as the base shape, while Polylang style setups can expose `/en/post/123/` and `/en/category/45/`.

== Screenshots ==

1. Settings screen for choosing the ID permalink format, target post types, target taxonomies, and optional legacy redirect behavior.

== Changelog ==

= 1.4.8 =

* Avoid database writes when settings are normalized during public requests

= 1.4.7 =

* Fix sitemap and indexing compatibility so permalink integrations receive canonical ID-based URLs consistently

= 1.4.6 =

* Confirm compatibility with WordPress 7.0

For the complete release history, see the [English changelog](https://happas.jp/en/slug-free-permalinks/).
