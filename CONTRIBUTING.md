# Contributing

Thanks for contributing to Slug-Free Permalinks.

## Repository Layout

- Source files live at the repository root.
- The distributable plugin is built into `dist/slug-free-permalinks`.
- `dist/` is local build output and is not tracked in Git.
- WordPress.org distribution metadata is maintained in `readme.txt`.

## Local Build

- Keep shared release metadata in `plugin-meta.json`.
- Synchronize metadata into the plugin header, readmes, and translation metadata with `node scripts/sync-meta.mjs`.
- Build the distributable plugin with `node scripts/build-dist.mjs`.
- Build the distributable plugin and versioned ZIP with `node scripts/build-dist.mjs --zip`.
- Run Plugin Check against `dist/slug-free-permalinks`, not the repository root.

## Coding Standards

- Install the development tools with `composer install`.
- Check PHP files with `composer check`.
- Apply automatically fixable formatting changes with `composer fix`, then review the diff and run `composer check` again.
- GitHub Actions runs the same check on pull requests and pushes to `main`.

## Release Tooling Tests

- Run the release tooling tests with `node --test tests/scripts/validate-release-metadata.test.mjs`.
- GitHub Actions runs the tests with Node.js 20 on pull requests and pushes to `main`.

## Studio Smoke Test

- The Studio smoke test builds the shipping artifact, installs it into an isolated Studio site, and checks ID-based post and taxonomy links, rewrite-rule registration, and settings normalization behavior.
- Create a dedicated Studio site before running it. Studio 1.15 supports PHP 8.2 or newer; the test does not replace PHP 7.4 compatibility checks.
- Run `powershell -ExecutionPolicy Bypass -File scripts/run-studio-smoke.ps1 -SitePath <Studio site path>`.
- If the WordPress Studio CLI is not the `studio` command on your PATH, also pass `-StudioCommand <path to studio.bat>`.

## Release Workflow

- GitHub is the source of truth for development.
- Decide whether the user-visible change is a patch, minor, or major release and choose a semantic version.
- Add the user-facing change to both `CHANGELOG.md` and `CHANGELOG.en.md`. Keep entries concise; put implementation rationale in the commit or pull request.
- Update the `version` field in `plugin-meta.json` and run `node scripts/sync-meta.mjs` to synchronize the plugin header, readmes, and translation metadata.
- Keep the latest three release sections in `readme.txt`; move older history to `CHANGELOG.md` and `CHANGELOG.en.md` and keep the complete-history link current.
- Run `node scripts/validate-release-metadata.mjs <version>` with the selected version, then run `node --test tests/scripts/validate-release-metadata.test.mjs`.
- Run `composer check` and rebuild the distributable with `node scripts/build-dist.mjs` when the release changes affect PHP, metadata, or package contents.
- Review `dist/slug-free-permalinks`, run `git diff --check`, and confirm that no unrelated or local-only files are included.
- Commit and push the release changes to `main`.
- Push the matching semantic version tag, such as `1.4.5`, to trigger WordPress.org deployment and automatic GitHub Release creation.
- After the tag push, confirm GitHub Actions, Plugin Check against `dist/slug-free-permalinks`, the GitHub Release, and the WordPress.org deployment result.
- If a tag-triggered WordPress.org deployment fails before publication, rerun the “Deploy to WordPress.org” workflow manually with the existing semantic version. The workflow validates the requested version against the release metadata before deploying.
- `scripts/create-github-release.mjs` uses the same version tag convention for GitHub Releases.

## WordPress.org Assets

- Optional WordPress.org assets live in `.wordpress-org/`.
- Common filenames are `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, and `screenshot-1.png`.
- The deploy workflow syncs `.wordpress-org/` to the WordPress.org `assets/` directory when those files exist.

## Notes

- Deployment uses the built artifact in `dist/slug-free-permalinks`, not the repository root.
- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
