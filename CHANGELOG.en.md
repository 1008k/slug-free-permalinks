# Changelog

## [Unreleased]

## [1.7.0]

- Improve target selection and guidance on the settings screen.
- Refine ID-based URL handling for Polylang language paths.

## [1.6.0]

- Add deactivation guidance and a copyable permalink structure to preserve regular post ID URLs.
- Reduce rewrite checks and permalink processing overhead.

## [1.5.1]

- Update compatibility metadata for WordPress 7.1.
- Improve CI and distribution validation.

## [1.5.0]

- Support ID-based URLs using registered rewrite slugs for post types and taxonomies.
- Detect conflicting rewrite slugs and prevent ambiguous URL routes.
- Improve rewrite-rule refresh after post type and taxonomy registration and remove stale plugin rules.
- Improve compatibility with language and path-prefixed URLs and existing permalink integrations.

## [1.4.8]

- Prevent unnecessary database writes during settings normalization on public requests.

## [1.4.7]

- Fix canonical ID-based URLs in sitemap and indexing integrations.

## [1.4.6]

- Confirm compatibility with WordPress 7.0.

## [1.4.5]

- Improve internal permalink handling consistency.

## [1.4.4]

- Unify ID-based URL handling with or without Polylang.
- Support language-directory URLs such as `/en/`.

## [1.4.3]

- Preserve Polylang and language-directory permalink prefixes for ID-based URLs.
- Accept prefixed ID routes such as `/en/post/123/` and `/en/category/45/`.

## [1.4.2]

- Add Japanese translation support compatible with Plugin Check.
- Update the distribution package for the latest Plugin Check fixes.

## [1.4.1]

- Remove unnecessary translation loading for Plugin Check compatibility.
- Refine the FAQ and release packaging workflow.

## [1.4.0]

- Rebrand the plugin as Slug-Free Permalinks.
- Add the WordPress.org readme and distribution metadata.
- Add an optional legacy slug redirect setting.

## [1.3.4]

- Add an optional redirect from legacy slug URLs to the current ID-based permalink.

## [1.3.3]

- Add taxonomy support and selectable slash or hyphen formats.
