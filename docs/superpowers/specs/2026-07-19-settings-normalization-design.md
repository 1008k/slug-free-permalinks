# Settings Normalization Design

## Goal

Keep Serena workspace state out of Git and remove option writes from front-end settings reads.

## Scope

- Ignore the repository-local `.serena/` directory.
- Keep `PTID_Permalink_Plugin::get_settings()` read-only.
- Normalize and persist legacy or malformed plugin settings only during `admin_init`.
- Do not change permalink formats, rewrite rules, redirects, or private-post behavior.
- Do not add a test framework in this change.

## Design

`register_settings()` will continue to run on `admin_init` and will also register a dedicated settings-normalization callback. The callback reads the stored option, normalizes it with the existing `normalize_settings()` method, and calls `update_option()` only when the stored array differs from its normalized form.

`get_settings()` will read the option, normalize it for the current request, prime the in-memory cache, and return it without persisting any change. A public front-end request therefore cannot modify the options table merely by resolving a permalink or applying a permalink filter.

The normalization callback runs after settings registration at the normal default action priority. It preserves the existing one-time migration behavior for administrators and avoids changing settings during public traffic.

## Error Handling

If the stored value is not an array, `get_settings()` uses the existing default normalization path without writing. The administrator-side callback likewise skips persistence unless the stored value is an array and differs from the normalized value, preserving the prior behavior for non-array option values.

## Verification

- `git check-ignore -q .serena/project.local.yml` succeeds.
- `git status --short --ignored` reports `.serena/` as ignored.
- PHP syntax validation passes for every distributable PHP file after rebuilding `dist`.
- `git diff --check` succeeds.
