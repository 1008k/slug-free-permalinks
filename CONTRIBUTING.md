# Contributing

Thanks for contributing to Slug-Free Permalinks.

## Repository Layout

- Source files live at the repository root.
- The distributable plugin is built into `dist/slug-free-permalinks`.
- `dist/` is local build output and is not tracked in Git.
- WordPress.org distribution metadata is maintained in `readme.txt`.

## Local Build

- Build the distributable plugin with `node scripts/build-dist.mjs`.
- Build the distributable plugin and versioned ZIP with `node scripts/build-dist.mjs --zip`.
- Run Plugin Check against `dist/slug-free-permalinks`, not the repository root.

## Release Workflow

- GitHub is the source of truth for development.
- Push a semantic version tag such as `1.4.5` to trigger WordPress.org deployment.
- The deploy workflow validates that the Git tag, `Version:` in `slug-free-permalinks.php`, and `Stable tag:` in `readme.txt` match exactly.
- GitHub Actions runs Plugin Check against `dist/slug-free-permalinks` on pull requests and pushes to `main`.
- `scripts/create-github-release.mjs` uses the same version tag convention for GitHub Releases.

## WordPress.org Assets

- Optional WordPress.org assets live in `.wordpress-org/`.
- Common filenames are `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, and `screenshot-1.png`.
- The deploy workflow syncs `.wordpress-org/` to the WordPress.org `assets/` directory when those files exist.

## Notes

- Deployment uses the built artifact in `dist/slug-free-permalinks`, not the repository root.
- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
