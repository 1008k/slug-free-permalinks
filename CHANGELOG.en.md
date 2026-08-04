# Changelog

## [Unreleased]

## [1.4.8]

- Avoid database writes when settings are normalized during public requests.

## [1.4.7]

- Fix sitemap and indexing compatibility so permalink integrations receive canonical ID-based URLs consistently.

## [1.4.6]

- Confirm compatibility with WordPress 7.0.

## [1.4.5]

- Improve internal permalink handling consistency.

## [1.4.4]

- Keep canonical ID permalinks consistent with or without Polylang.
- Continue supporting language-directory prefixes such as `/en/`.

## [1.4.3]

- Preserve Polylang and language-directory permalink prefixes for ID-based URLs.
- Accept prefixed ID routes such as `/en/post/123/` and `/en/category/45/`.

## [1.4.2]

- Add a guarded Japanese l10n PHP translation file for Plugin Check compatibility.
- Update the distribution package for the latest Plugin Check fixes.

## [1.4.1]

- Remove unnecessary manual translation loading to satisfy current Plugin Check guidance.
- Refine the FAQ and release packaging workflow.

## [1.4.0]

- Rebrand the plugin as Slug-Free Permalinks.
- Add the WordPress.org readme and distribution metadata.
- Add an optional legacy slug redirect setting.

## [1.3.4]

- Add an optional redirect from legacy slug URLs to the current ID-based permalink.

## [1.3.3]

- Add taxonomy support and selectable slash or hyphen formats.
