=== Slug-Free Permalinks ===
Contributors: happas
Tags: permalinks, slugs, custom post types, taxonomy, urls
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.4.0
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
* Flush rewrite rules automatically when settings change

This plugin is focused on permalink structure only. It does not add content features or front-end UI.

Development repository: https://github.com/1008k/slug-free-permalinks

Known limitations:

* If a post type slug and taxonomy slug are identical, their ID based rewrite patterns can conflict.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Go to `Settings > Slug-Free Permalinks`.
4. Choose a permalink format.
5. Check the post types and taxonomies you want to convert to ID based permalinks.
6. Optionally enable legacy permalink redirects.
7. Save changes.

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

== Changelog ==

= 1.4.0 =

* Rebrand plugin as Slug-Free Permalinks
* Add WordPress.org readme and distribution metadata
* Add optional legacy slug redirect setting

= 1.3.4 =

* Add optional redirect from legacy slug URLs to the current ID based permalink

= 1.3.3 =

* Add taxonomy support and selectable slash or hyphen formats
