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
mhmuicore_register( '0.4.0', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
```

At `plugins_loaded` priority 0 the highest registered version boots; the rest
stand down. The version string passed must match this package's own version.

**Every plugin that bundles the package must register it**, not just one of a
family. A plugin that vendors the package but never calls `mhmuicore_register()`
does not enter the version arbitration at all: its own copy is inert and it
silently depends on a sibling plugin having booted one. Deactivating the sibling
then takes its React screens with it.

## React admin pages

`mhmuicore_enqueue_react_page()` performs the four steps every MHM React admin
screen needs, in order: the `wp-api-fetch` REST nonce middleware (once per
request, however many pages enqueue), the `wp-components` stylesheet, the bundle
with the dependency list and version `@wordpress/scripts` generated, and its
JSON translation catalogues.

```php
if ( function_exists( 'mhmuicore_enqueue_react_page' ) ) {
	mhmuicore_enqueue_react_page(
		array(
			'page'          => 'dashboard',           // build/admin/dashboard.js
			'base_dir'      => MY_PLUGIN_DIR,          // trailing slash
			'base_url'      => MY_PLUGIN_URL,          // trailing slash
			'handle_prefix' => 'my-plugin-react-',     // handle = prefix . page
			'version'       => MY_PLUGIN_VERSION,      // fallback only
			'text_domain'   => 'my-plugin',
		)
	);
}
```

Optional keys: `deps` (extra script handles, merged **after** the generated
ones), `languages_dir` (default `base_dir . 'languages/'`), `build_dir`
(default `build/admin/`).

Three things this deliberately does **not** do:

- It has no defaults for the six required keys. The package has no text domain
  and no plugin constants of its own; anything product-shaped is caller input,
  and an empty string is rejected the same as a missing key, because that is the
  shape an undefined plugin constant collapses to.
- The caller's `version` never overrides the generated manifest — that value is
  a content hash, and letting a plugin version win would ship new bytes under an
  old cache key.
- It is declared in `bootstrap.php`, not `src/`. `src/` is PSR-4 autoloaded from
  each consumer's own `vendor/`, so the *first* autoloader wins; `bootstrap.php`
  is loaded from the *highest registered version*. A loader must follow the same
  rule as the assets it points at.

🔴 **Guard the call with `function_exists()`.** A site may still be running an
older ui-core as the arbitration winner, where this function does not exist.

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
