# Contributing

Thanks for contributing to Slug-Free Permalinks.

## Project Records

- Use GitHub Issues for problem reports, investigation, open questions, and work that benefits from discussion before implementation.
- Small, self-contained changes do not require an Issue; a focused Pull Request is enough.
- Use Pull Requests as the primary record of what changed, why it changed, and how it was reviewed.
- Keep current product behavior and constraints in the relevant repository documentation instead of duplicating them in Issues or Pull Requests.
- Update long-lived documentation only when a change affects information that future contributors or users need to understand.

## Repository Layout

- Source files live at the repository root.
- Product behavior and constraints are documented in `docs/project-spec.md`.
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
- GitHub Actions runs PHP checks only for pull requests that change PHP, Composer, PHPCS configuration, or the PHP-check workflow.

## Release Tooling Tests

- Run the release tooling tests with `node --test tests/scripts/*.test.mjs`.
- GitHub Actions runs these tests only when release metadata, release scripts, their tests, or release workflows change.
- Metadata synchronization is also checked so generated release metadata remains committed and consistent.

## Studio Smoke Test

- The Studio smoke test builds the shipping artifact, installs it into an isolated Studio site, and checks ID-based post and taxonomy links, rewrite-rule registration, and settings normalization behavior.
- Create a dedicated Studio site before running it. Studio 1.15 supports PHP 8.2 or newer; the test does not replace PHP 7.4 compatibility checks.
- Run `powershell -ExecutionPolicy Bypass -File scripts/run-studio-smoke.ps1 -SitePath <Studio site path>`.
- If the WordPress Studio CLI is not the `studio` command on your PATH, also pass `-StudioCommand <path to studio.bat>`.

## Pull Requests

- Keep each pull request focused on one coherent change.
- Explain the reason for the change and reference a related Issue when one exists.
- Update repository documentation in the same pull request when user-visible behavior, constraints, setup, or release procedures change.
- Do not add generated files, local-only files, or unrelated cleanup unless the pull request explicitly requires them.
- Before merging, confirm that the relevant checks pass and that the diff contains no unrelated changes.

## Release Workflow

- GitHub is the source of truth for development and review history.
- Decide whether the user-visible change is a patch, minor, or major release and choose a semantic version.
- Add the user-facing change to both `CHANGELOG.md` and `CHANGELOG.en.md`. Keep entries concise; implementation rationale belongs in the Pull Request when it does not affect the long-term product specification.
- Update the `version` field in `plugin-meta.json` and run `node scripts/sync-meta.mjs` to synchronize the plugin header, readmes, and translation metadata.
- Keep the latest three release sections in `readme.txt`; move older history to `CHANGELOG.md` and `CHANGELOG.en.md` and keep the complete-history link current.
- Run `node scripts/validate-release-metadata.mjs <version>` and `node --test tests/scripts/*.test.mjs`.
- Run `composer check` and rebuild the distributable with `node scripts/build-dist.mjs` when the release changes affect PHP, metadata, or package contents.
- Review `dist/slug-free-permalinks`, run `git diff --check`, and confirm that no unrelated or local-only files are included.
- Commit and merge the release changes to `main`.
- Push the matching semantic version tag, such as `1.5.1`, to trigger the unified `Release` workflow.
- The `Release` workflow validates release metadata, builds the distributable, runs Plugin Check, publishes or updates the GitHub Release, and deploys the same artifact to WordPress.org in that order.
- After the tag push, confirm the `Release` workflow, GitHub Release, and WordPress.org deployment result.
- If a release must be retried for an existing version, run the `Release` workflow manually with that semantic version; the workflow validates the requested version before publishing or deploying.

## WordPress.org Assets

- Optional WordPress.org assets live in `.wordpress-org/`.
- Common filenames are `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, and `screenshot-1.png`.
- The release workflow syncs `.wordpress-org/` to the WordPress.org `assets/` directory when those files exist.

## Notes

- Deployment uses the built artifact in `dist/slug-free-permalinks`, not the repository root.
- If a post type slug and taxonomy slug are identical, their ID-based rewrite rules can conflict.
