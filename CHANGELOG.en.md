# Changelog

## [Unreleased]

## [2.0.0]

- Build native WordPress post permalinks using presets and editable tags, with separate custom content settings and legacy migration.
- Add optional `legacy_id` custom field permalinks for CMS migrations, with ID or slug fallback.
- Redirect URLs in the non-selected ID format (slash or hyphen) when legacy redirects are enabled.

## [1.7.0]

- Make post type and taxonomy selection easier on the settings screen.
- Improve ID-based URL handling for Polylang language paths.

## [1.6.0]

- Add deactivation guidance and a copyable permalink structure for keeping regular post ID URLs.
- Reduce overhead in permalink processing and rewrite-rule checks.

## [1.5.1]

- Update compatibility metadata for WordPress 7.1.
- Improve release validation.

## [1.5.0]

- Support ID-based URLs using registered rewrite slugs for custom post types and taxonomies.
- Prevent selecting content types with conflicting rewrite slugs.
- Improve ID URL routing when post types or taxonomies are registered.
- Improve compatibility with language-prefixed URLs and existing permalink settings.

## [1.4.8]

- Reduce unnecessary database writes during page requests.

## [1.4.7]

- Fix canonical ID-based URLs in sitemaps and indexing integrations.

## [1.4.6]

- Confirm compatibility with WordPress 7.0.

## [1.4.5]

- Improve permalink generation.

## [1.4.4]

- Support ID-based URLs with language directories such as `/en/`, with or without Polylang.

## [1.4.3]

- Generate and resolve language-prefixed ID URLs such as `/en/post/123/` and `/en/category/45/`.

## [1.4.2]

- Add Japanese translations and improve Plugin Check compatibility.

## [1.4.1]

- Improve translation-loading compatibility.
- Update the FAQ and release process.

## [1.4.0]

- Rebrand the plugin as Slug-Free Permalinks.
- Add the WordPress.org readme and distribution metadata.
- Add a setting for redirecting legacy slug URLs to ID-based URLs.

## [1.3.4]

- Allow redirects from legacy slug URLs to ID-based URLs.

## [1.3.3]

- Add taxonomy support and selectable slash or hyphen formats.
