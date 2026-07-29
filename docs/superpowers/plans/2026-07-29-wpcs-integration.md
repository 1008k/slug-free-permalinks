# WordPress Coding Standards Integration Implementation Plan

> **For agentic workers:** Implement this plan task-by-task. The optional `superpowers:executing-plans` and `superpowers:subagent-driven-development` skills are not installed in this workspace, so follow the checkpoints directly.

**Goal:** Add a reproducible WordPress Coding Standards toolchain and make violations fail continuous integration.

**Architecture:** Composer owns the locked PHPCS/WPCS dependencies. A single `phpcs.xml.dist` defines scan scope and policy, while local Composer scripts and a dedicated GitHub Actions workflow call the same rule set.

**Tech Stack:** PHP 7.4+, Composer 2, PHP_CodeSniffer, WordPress Coding Standards 3.x, GitHub Actions

## Global Constraints

- Minimum WordPress version: 5.8.
- Minimum PHP version: 7.4.
- The complete `WordPress` ruleset is the baseline.
- Generated translation PHP, `vendor/`, and `dist/` are outside the scan scope.
- Plugin Check remains a separate check against `dist/slug-free-permalinks`.
- Do not change routing or settings behavior while correcting coding-standard violations.
- Do not commit or push unless the user explicitly requests it.

---

### Task 1: Add the Composer-managed WPCS toolchain

**Files:**
- Create: `composer.json`
- Create: `composer.lock`
- Create: `phpcs.xml.dist`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `composer check` for non-mutating validation and `composer fix` for PHPCBF formatting.
- Produces: one ruleset consumed identically by local development and CI.

- [ ] **Step 1: Create `composer.json`**

```json
{
  "name": "1008k/slug-free-permalinks",
  "description": "WordPress plugin that provides ID-based post and taxonomy permalinks without permastruct slugs.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require-dev": {
    "wp-coding-standards/wpcs": "^3.0"
  },
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    },
    "platform": {
      "php": "7.4.33"
    },
    "sort-packages": true
  },
  "scripts": {
    "check": "phpcs",
    "fix": "phpcbf"
  }
}
```

- [ ] **Step 2: Create `phpcs.xml.dist` with an explicit scan scope**

```xml
<?xml version="1.0"?>
<ruleset name="Slug-Free Permalinks">
    <description>WordPress Coding Standards for Slug-Free Permalinks.</description>

    <arg name="basepath" value="."/>
    <arg name="colors"/>
    <arg value="sp"/>
    <arg name="extensions" value="php"/>

    <file>slug-free-permalinks.php</file>
    <file>uninstall.php</file>
    <file>tests/studio/smoke.php</file>

    <config name="minimum_supported_wp_version" value="5.8"/>

    <rule ref="WordPress"/>

    <rule ref="WordPress.Files.FileName.InvalidClassFileName">
        <exclude-pattern>slug-free-permalinks.php</exclude-pattern>
    </rule>
    <rule ref="WordPress.WP.AlternativeFunctions.file_system_operations_fwrite">
        <exclude-pattern>tests/studio/smoke.php</exclude-pattern>
    </rule>
    <rule ref="WordPress.PHP.DevelopmentFunctions.error_log_var_export">
        <exclude-pattern>tests/studio/smoke.php</exclude-pattern>
    </rule>
</ruleset>
```

The three narrow exclusions preserve the required WordPress plugin bootstrap filename and allow a CLI-only smoke-test harness to write diagnostic output. They do not weaken the distributable runtime checks.

- [ ] **Step 3: Ignore installed dependencies**

Append the following entry to `.gitignore`:

```gitignore
vendor/
```

- [ ] **Step 4: Resolve and lock current WPCS 3.x dependencies**

Run:

```powershell
composer update --with-all-dependencies
```

Expected: `composer.lock` is created and the installed standards include `WordPress`. The planning audit resolved WPCS 3.4.1; accept a newer compatible 3.x patch/minor release if Composer resolves one at execution time.

- [ ] **Step 5: Confirm the installed standard**

Run:

```powershell
vendor\bin\phpcs.bat -i
```

Expected: output lists `WordPress`, `WordPress-Core`, `WordPress-Docs`, and `WordPress-Extra`.

---

### Task 2: Establish a clean WPCS baseline

**Files:**
- Modify: `slug-free-permalinks.php`
- Modify: `uninstall.php`
- Modify: `tests/studio/smoke.php`
- Modify if required by PHPCBF: line endings in the same three files

**Interfaces:**
- Consumes: `composer fix` and `composer check` from Task 1.
- Produces: behavior-preserving source files with zero PHPCS errors and warnings.

The pre-implementation audit found 1,707 errors and 60 warnings: 1,598 messages in `slug-free-permalinks.php`, 9 in `uninstall.php`, and 160 in `tests/studio/smoke.php`. PHPCBF can fix 1,676 automatically. The remaining categories are missing documentation, 28 non-Yoda comparisons, four local-variable names that override WordPress globals, and the three explicitly justified test/bootstrap exclusions in Task 1.

- [ ] **Step 1: Apply deterministic PHPCBF fixes**

Run:

```powershell
composer fix
```

Expected: indentation, spacing, array formatting, brace placement, and line endings are rewritten; the command remains non-zero while non-fixable findings exist.

- [ ] **Step 2: Add required documentation comments**

Add WordPress-style file headers to all three scanned files, a class docblock to `PTID_Plugin`, docblocks to each reported function/method, and short `@var` comments for the four reported variables. Describe the existing behavior and types only; do not claim new behavior.

Example form:

```php
/**
 * Filters a resolved post permalink.
 *
 * @param string  $permalink Resolved permalink.
 * @param WP_Post $post      Post object.
 * @return string Filtered permalink.
 */
```

- [ ] **Step 3: Convert the 28 reported comparisons to Yoda form**

For each `WordPress.PHP.YodaConditions.NotYoda` finding, place constants and literals on the left without changing operators or operands.

```php
if ( 'post' === $post_type ) {
    // Existing body remains unchanged.
}
```

- [ ] **Step 4: Rename the four smoke-test locals that shadow WordPress globals**

Use context-specific names such as `$created_post`, `$created_term`, `$tested_post`, or `$tested_term`, updating every use within `tests/studio/smoke.php`. Do not rename actual WordPress globals.

- [ ] **Step 5: Re-run PHPCBF and PHPCS**

Run:

```powershell
composer fix
composer check
```

Expected: `composer check` exits 0 with zero errors and zero warnings. If any non-fixable finding remains, fix the reported source rather than adding a broad rule exclusion.

- [ ] **Step 6: Verify behavior-sensitive diffs**

Inspect the diff and confirm that executable changes are limited to comparison operand order, local variable renames, comments, and formatting. Pay particular attention to hook registrations, rewrite regexes, query variables, settings normalization, redirects, and output escaping.

---

### Task 3: Enforce the same check in CI and document local use

**Files:**
- Create: `.github/workflows/coding-standards.yml`
- Modify: `CONTRIBUTING.md`

**Interfaces:**
- Consumes: `composer.lock` and `composer check` from Task 1.
- Produces: a blocking pull-request and `main`-push coding-standards check.

- [ ] **Step 1: Create the coding-standards workflow**

```yaml
name: Coding Standards

on:
  push:
    branches:
      - main
  pull_request:

concurrency:
  group: coding-standards-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  phpcs:
    runs-on: ubuntu-latest
    permissions:
      contents: read

    steps:
      - name: Check out repository
        uses: actions/checkout@v5

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
          coverage: none
          tools: composer:v2

      - name: Install development dependencies
        run: composer install --no-interaction --no-progress --prefer-dist

      - name: Run WordPress Coding Standards
        run: composer check
```

- [ ] **Step 2: Document local quality commands in `CONTRIBUTING.md`**

Add a `## Coding Standards` section after `## Local Build`:

```markdown
## Coding Standards

- Install the development tools with `composer install`.
- Check PHP files with `composer check`.
- Apply automatically fixable formatting changes with `composer fix`, then review the diff and run `composer check` again.
- GitHub Actions runs the same check on pull requests and pushes to `main`.
```

- [ ] **Step 3: Validate workflow syntax and references**

Run a repository search confirming that the workflow calls only scripts present in `composer.json` and installs from the committed lock file.

Expected: `composer check` is defined once in `composer.json` and invoked by both contributors and CI.

---

### Task 4: Run the complete verification gate

**Files:**
- Verify all files changed by Tasks 1-3.

**Interfaces:**
- Consumes: the completed toolchain, clean source baseline, and CI workflow.
- Produces: fresh evidence for the final handoff.

- [ ] **Step 1: Reinstall exactly from the lock file**

Run:

```powershell
composer install --no-interaction --no-progress --prefer-dist
```

Expected: exit 0 without dependency changes.

- [ ] **Step 2: Run WPCS**

Run:

```powershell
composer check
```

Expected: exit 0, zero errors, zero warnings.

- [ ] **Step 3: Rebuild the shipping artifact**

Run:

```powershell
node scripts/build-dist.mjs
```

Expected: exit 0 and `dist/slug-free-permalinks` rebuilt from the formatted source.

- [ ] **Step 4: Syntax-check tracked and built PHP**

Run PHP lint over `slug-free-permalinks.php`, `uninstall.php`, `tests/studio/smoke.php`, and every PHP file under `dist/slug-free-permalinks`.

Expected: every file reports no syntax errors.

- [ ] **Step 5: Check patch hygiene and final scope**

Run:

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors; only the design/plan, Composer/WPCS configuration, lock file, CI workflow, contributor documentation, and WPCS-normalized PHP sources are changed.
