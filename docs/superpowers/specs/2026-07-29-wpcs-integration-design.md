# WordPress Coding Standards Integration Design

## Goal

Add WordPress Coding Standards as a reproducible development dependency and make coding-standard violations fail continuous integration.

## Approach

Use Composer to install `wp-coding-standards/wpcs` and its PHPCS integration. Keep the project-specific policy in one `phpcs.xml.dist` file and expose the check through a Composer script. A dedicated GitHub Actions workflow will install the locked dependencies and run the same script used locally.

This is preferred over using only `WordPress-Core`, because the complete `WordPress` ruleset also covers documentation and WordPress-specific best practices. It is preferred over an advisory-only local command because coding standards otherwise depend on each contributor remembering to run the check.

## Scan Scope

PHPCS will scan the distributable PHP source files at the repository root and the Studio smoke test. It will exclude generated or third-party content, including `dist/`, `vendor/`, and generated translation PHP files.

The ruleset will declare the plugin's supported WordPress baseline of 5.8. It will use PHP 7.4 syntax compatibility through the configured PHPCS runtime; a separate PHPCompatibility ruleset is outside this change.

## Developer Workflow

- `composer install` installs the locked tooling versions.
- `composer check` runs PHPCS without changing files.
- `composer fix` runs PHPCBF for automatically fixable violations.
- `CONTRIBUTING.md` documents these commands.

## Continuous Integration

A dedicated workflow will run for pull requests and pushes to `main`. It will set up a PHP version compatible with the plugin's minimum requirement, install Composer dependencies from `composer.lock`, and run `composer check`.

Plugin Check remains a separate artifact-focused workflow against `dist/slug-free-permalinks`. WPCS checks source consistency; it does not replace Plugin Check or the Studio smoke test.

## Existing Violations

After installing the tools, run PHPCS against the proposed ruleset. Fix violations required to establish a clean baseline without changing plugin behavior. Any rule exclusion must be narrow, documented in `phpcs.xml.dist`, and used only when the rule conflicts with an intentional project constraint.

## Verification

The change is complete when all of the following succeed:

1. A clean Composer install from the lock file.
2. `composer check` with zero errors.
3. `node scripts/build-dist.mjs`.
4. PHP syntax checks for the source and built distributable.
5. `git diff --check`.
