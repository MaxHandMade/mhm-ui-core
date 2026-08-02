# mhm/ui-core

Shared WordPress UI infrastructure for MHM plugins.

Three responsibilities:

1. **Component factory** — one component contract produces a shortcode, a
   Gutenberg block and an Elementor widget from a single renderer.
2. **React admin kit** — shared enqueue helper, design tokens and JSX components.
3. **Tier seam** — the extension points a WordPress.org-compliant free core
   exposes for a licensed Pro add-on to bind to.

This package contains no business logic, no licensing code and no external
HTTP calls. Public and GPL, like every WordPress plugin it ships inside.

## Loader contract

A consuming plugin `require_once`s `vendor/mhm/ui-core/register.php` from its
main file and registers its own copy:

```php
mhmuicore_register( '0.2.0', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
```

At `plugins_loaded` priority 0 the highest registered version boots; the rest
stand down. The version string passed must match this package's own version.

## 0.2.0 — breaking prefix rename

| 0.1.x | 0.2.0 |
|---|---|
| `mhm_ui_core_register()` | `mhmuicore_register()` |
| `mhm_ui_core_boot()` | `mhmuicore_boot()` |
| `$mhm_ui_core_candidates` | `$mhmuicore_candidates` |
| `MHM_UI_CORE_VERSION` | `MHMUICORE_VERSION` |
| `MHM_UI_CORE_DIR` | `MHMUICORE_DIR` |
| namespace `MHM\UiCore` | namespace `MHMUiCore` |

Why: the 0.1.x names split at their first delimiter (`_` for functions and
constants, `\` for the namespace) into the three-character token `mhm`. That is
below the four-character minimum WordPress Coding Standards enforces for a
declared prefix, and a WordPress.org reviewer rejected exactly that token by
name in `mhm-rentiva`, which vendors this package into its release ZIP.

**No backward-compatibility alias is provided, on purpose.** Declaring the old
function names here would reprint the rejected token inside every release ZIP
that vendors this package — the one outcome the rename exists to prevent.
Nothing outside this repository stores or transmits these names (no options, no
meta keys, no cron hooks, no custom actions/filters, no crypto material — the
package's only hook is core's `plugins_loaded`), so the rename has no migration
step; it is purely a source-level call-site change.

An un-migrated 0.1.x copy in a sibling plugin **cannot fatal** against 0.2.0:
the two loaders share no identifier, and every declaration is behind a
per-name `function_exists()` / `defined()` guard. It boots its own bootstrap in
parallel, which only defines two constants. The cost is that version
arbitration does not span the rename boundary — a 0.1.x copy and a 0.2.0 copy
both boot instead of the highest one winning. Consumers should move.

The Composer package name (`mhm/ui-core`) and its install path
(`vendor/mhm/ui-core/`) are **not** renamed: they are not PHP identifiers, they
are referenced by the consuming plugin's `composer.json`, its `.distignore`,
and this repository's GitHub URL.

Design: `rentiva-dev/docs/superpowers/specs/2026-07-14-mhm-ui-core-design.md`
