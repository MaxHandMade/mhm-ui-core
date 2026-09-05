# mhm/ui-core

Shared WordPress UI infrastructure for MHM plugins.

> 🇹🇷 Türkçe özet: **[README-tr.md](README-tr.md)** — ne işe yarar, nerede kullanılır, nasıl
> kullanılır. Çelişki hâlinde bu İngilizce dosya geçerlidir.

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
mhmuicore_register( '0.9.0', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
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
- It is declared in `bootstrap.php`, not `src/`. `src/` classes are loaded by
  the autoloader `bootstrap.php` itself registers, bound to that copy's own
  `__DIR__` — not by Composer's PSR-4 map (measured: no consumer loads
  `vendor/autoload.php`). `mhmuicore_boot()` loads exactly one `bootstrap.php`:
  the *highest registered version*. Binding that copy's own `src/` makes the
  facade and the classes it hands out the same copy by construction, so
  facade/engine version skew cannot happen and needs no runtime check. A
  shared PSR-4 registration would reintroduce "whichever autoloader answers
  first wins" — the same rule the asset locators above follow.

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

## Layout engine

`mhmuicore_layout_engine()` builds a `MHMUiCore\Layout\LayoutEngine` from a
consumer-supplied **contract** — three keys, and nothing else:

```php
if ( function_exists( 'mhmuicore_layout_engine' ) ) {
	$engine = mhmuicore_layout_engine(
		array(
			'error_prefix'  => 'mhmrentiva',              // machine-readable error codes, e.g. "mhmrentiva_missing_adapter"
			'markup_prefix' => 'mhm',                      // consumer's own CSS class prefix, e.g. "mhm-layout-root"
			'adapters'      => array( 'hero' => $adapter ), // component type => LayoutComponentAdapter
		)
	);
}
```

- **`error_prefix`** and **`markup_prefix`** are separate on purpose: one
  cannot produce both `mhmrentiva_*` error codes and `mhm-*` markup classes.
- **`adapters`** is the component vocabulary — a `type => LayoutComponentAdapter`
  map. There is no separate allow-list to drift from it.
- House rules (the Tailwind ban, the design-token map) are package
  **defaults**, not injected values: a second product's answer for them would
  be identical, so they are not part of the contract.

🔴 **Guard the call with `function_exists()`**, same as `mhmuicore_enqueue_react_page()`
above — an older ui-core copy may still be the arbitration winner, where this
function does not exist.

**The engine never throws for a domain error, and it never produces human
text.** `LayoutEngine::validate()` and `::build()` return either the expected
value or a `WP_Error` whose `get_error_message()` is always the empty string
— the package owns no consumer text domain, so it cannot emit a string
WordPress's `.pot` extractor would ever collect. Every fact the error carries
lives in `get_error_code()` (one of the eleven suffixes in
`MHMUiCore\Layout\ErrorCodes::ALL`, prefixed with the contract's own
`error_prefix`) and `get_error_data()` (a structured array, e.g.
`array( 'type' => 'unregistered_type' )` for `missing_adapter`). **The
consumer renders the human sentence**, in its own text domain, from the code
and that data:

```php
$result = $engine->build( $manifest, $page );

if ( is_wp_error( $result ) ) {
	switch ( $result->get_error_code() ) {
		case 'mhmrentiva_missing_adapter':
			$data = $result->get_error_data();
			return sprintf(
				/* translators: %s: component type */
				__( 'No adapter is registered for component type "%s".', 'my-plugin' ),
				$data['type']
			);
		// ...
	}
}
```

A malformed **contract** (a missing/malformed `error_prefix`, `markup_prefix`
or `adapters`) is the one exception: `mhmuicore_layout_engine()` lets
`InvalidArgumentException` propagate uncaught, because that is a programmer
error, not a domain error — no runtime path can recover from it.

Design: the six-phase design document lives in MHM's internal development repo, not here.

## Component factory

One contract, four surfaces. Every part of a design is FIXED (lives in the
renderer template), DATA (the renderer queries it) or a SETTING (a user
choice); only the settings are declared, and the shortcode attribute allowlist,
`block.json` attributes, Elementor controls and Layout adapter are derived
from them. The renderer is the only hand-written file.

```php
$factory = mhmuicore_component_factory( array(
    'prefix'          => 'myplugin',   // [myplugin_hero]
    'block_namespace' => 'my-plugin',  // my-plugin/hero
    'text_domain'     => 'my-plugin',
    // Where the scaffolder wrote <kebab>/block.json. Pass it: WordPress opens
    // block.json only when register_block_type() is handed a PATH, so without
    // this the file the scaffolder wrote is never read and the block that
    // registers is not the block the file describes.
    'blocks_dir'      => __DIR__ . '/' . \MHMUiCore\Component\ComponentFactory::BLOCKS_DIRNAME,
) );

$hero = $factory->register(
    new \MHMUiCore\Component\ComponentContract( require __DIR__ . '/contracts/hero.php' ),
    new HeroRenderer() // implements \MHMUiCore\Component\ComponentRenderer
);
// $hero->shortcode_tag(), $hero->block_name(), $hero->layout_adapter()
```

Settings arrive typed whatever the surface: `'1'`/`'0'`, `true`/`false` and
`'yes'`/`''` all become the same `bool`. Undeclared attributes never reach the
renderer. `wp mhm-ui make:component <slug> --prefix= --block-namespace=
--text-domain= --php-namespace=` scaffolds the contract, renderer, `block.json`
and a test; it refuses to overwrite.

Pass `blocks_dir` and the file becomes the block: registration goes through
`block.json` and the only argument left is the render callback, because core
merges `array_merge( $settings, $args )` -- every argument REPLACES the file's
answer, so passing the contract's title and supports beside it would leave two
descriptions with the argument winning. Editing `block.json` to switch wide
alignment on therefore works, and the metadata must name the same block the
factory does or registration refuses out loud rather than registering something
else. Without `blocks_dir` the block still registers -- title, supports,
attributes, asset handles and renderer, all from the contract -- but it has no
metadata file, so it has no `apiVersion` to declare.

Proof: Rentiva's shipped `featured-vehicles/block.json` is regenerated byte for
byte from a contract in `tests/Component/FeaturedVehiclesReproductionTest.php`,
and in `tests/Integration/PilotSeamTest.php` real WordPress is asked what it
registered: `api_version` 3, straight out of the file.

## Tier seam (free core / Pro add-on)

```php
$seam = mhmuicore_slot_registry( 'myplugin' );
$seam->declare_slot( 'hero_after' );             // free core declares, by name
$caps = mhmuicore_capabilities( 'myplugin' );

$html = $seam->apply( 'hero_after', $html );     // fills run, then the
                                                 // myplugin_hero_after filter
if ( $caps->has( 'pro_badge' ) ) { /* do MORE, never less */ }

$seam->fill( 'hero_after', fn( $h ) => $h . '…' ); // Pro add-on
$caps->grant( 'pro_badge' );
```

Filling an undeclared slot throws. `wp mhm-ui check:purity <dir>` reads a free
core's PHP and JavaScript -- including JavaScript a PHP file hands to the browser
in `wp_add_inline_script`, a heredoc or a `<script>` block -- and ends on one of
**three** answers: clean, findings, or **places it could not decide**. The third
is not a pass. Neither is a tree it found no readable file in, nor a file it
could not open (`\MHMUiCore\Seam\PurityScanner` ships in `src/`, so a consumer's
CI can call it directly). Its self-test runs fixtures in both languages before
every scan and fails if either half did not run.

**PHP.** An outbound call is one of `wp_remote_get/post/request/head`, their
`wp_safe_` forms, `curl_init`, `curl_exec`, `fsockopen` or `stream_socket_client`
-- written plainly or fully qualified. A second list speaks only on evidence:
`wp_enqueue_script/style`, `wp_register_script/style`, `download_url`,
`wp_remote_fopen`, `file_get_contents`, `fopen`, `get_headers` and
`simplexml_load_file` are reported when an absolute URL is handed to them, and
never on its absence -- pulling a font from a CDN is the shape WP.org rejects,
while reading a local file is what these functions are for. Licence and
artificial-limit vocabulary is matched in identifiers, variables and string
literals, interpolated ones included, in `snake_case` and `camelCase` alike.

**JavaScript.** A call is decided inside **its own target argument** -- never the
block around it, and never the rest of its own argument list: `fetch`,
`sendBeacon`, `XMLHttpRequest.open`, `axios`, `jQuery.ajax/get/post/getJSON`,
`WebSocket`, `EventSource`, `importScripts`, `import()`, `window.open`, plus URLs
assigned to `location`, `src` or `action` and handed to `setAttribute`. When the
target argument is an option object, its `url`/`path`/`src` property is the
target and the rest of the payload is somebody else's business. A target that
resolves to an absolute URL -- directly, or through a name the file binds exactly
once -- is a finding; one that resolves to this site is clean, as are `ajaxurl`
and `wpApiSettings`, the two globals WordPress itself fills. Anything else is
**undecided**: said out loud, never swallowed.

**The bounds are part of the claim.** A PHP call made through a variable function
name, or by a library neither list names, is not seen. A JavaScript target
assembled at run time is undecided, not clean -- and a plugin that hands its own
REST root to the browser through `wp_localize_script` will collect one undecided
call per request, because this gate does not follow a value from PHP into a
localised payload. `xhr.open( method, url )` with a run-time verb is read as a
sink, so it speaks only when a literal URL is present. jQuery's `.attr( 'src', … )`,
`axios.create({ baseURL })` and `$.getScript` are not among the shapes above. A
generated bundle -- any file with a line over 2000 characters -- is undecided
rather than read, because one line of minified code puts every call and every URL
in the same window; point the gate at the sources it was built from. `vendor/`,
`node_modules/`, `tests/` and `.git/` are not read at all, so a licence client
vendored into the shipped ZIP is outside this run. Inside PHP the gate cannot tell
script from prose, so JavaScript found there is reported on evidence only.

Measured against a real 475-file Lite core: 3 findings and 58 undecided calls in
29 files -- 50 of them one `$.ajax({ url: vars.ajax_url })` idiom whose payload
comes from PHP, 4 generated bundles, 4 genuine run-time targets. That is the
honest shape of the answer: a short list to read, not a clean bill.

## What a free core must keep out of its ZIP

This package installs whole into `vendor/mhm/ui-core`, and it must stay whole
there: the loader arbitrates versions, so on a site carrying two plugins that
bundle it, ONE copy serves both. A consumer that deletes PHP classes from its
copy therefore breaks the other plugin when its copy is the one that wins --
measured, not theorised: a Lite plugin at `^0.8` sorts before and outranks a
sibling at `^0.7`, and the sibling's call into a deleted class is a fatal.

**So prune nothing from `vendor/`.** What a WordPress.org free core prunes is its
own **ZIP**, and only these, none of which any runtime path reaches:

| Keep out of the ZIP | Why |
|---|---|
| `src/Cli/` | `wp mhm-ui` commands are development tooling. Registration is guarded on the command class existing, so leaving them out costs the commands and nothing else. |
| `src/Seam/PurityScanner.php` | The free-core purity gate. Its vocabulary IS the list a reviewer greps for — `license_key`, `activate_license`, `upgrade_to_pro`, `pro_only` — so it reads as the thing it exists to prevent. CI calls it from `vendor/`, where it stays; no runtime path touches it. |
| `src-react/components/ProLock.jsx` | Pro-facing: it hides a control unless a paid tier unlocked it. Bundled by a build step, never loaded at runtime. |
| `assets/react/pro.css` | The stylesheet half of the same thing. A free core enqueues `react/admin.css` only. |
| `README.md` · `README-tr.md` · `assets/README.md` · `package.json` | Developer documentation and build metadata. |

The rest ships. `bootstrap.php`, `register.php`, `src/VersionSelector.php`,
`src/Seam/SlotRegistry.php`, `src/Seam/Capabilities.php` and the React modules a
product actually imports are runtime code, and the arbitration above is why they
travel together.

**Wire the loader through the registry, never by requiring the bootstrap:**

```php
require_once __DIR__ . '/vendor/mhm/ui-core/register.php';
mhmuicore_register( '0.9.0', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
```

Requiring `bootstrap.php` directly defines `MHMUICORE_VERSION` immediately, which
makes every other copy's bootstrap a no-op: the first plugin loaded wins instead
of the highest version. The literal must match this package's own version --
pin it with a check in your own gates, as the existing consumer does.

## Admin React kit

`src-react/index.js` exports `StatCard`, `StatsGrid`, `KpiBox`, `StatusBadge`,
`Pagination`, `ProLock`, `Notice`, `Widget`, `ErrorBoundary`, `createApiClient`,
`useApi`, `createFormatter` and `tokens`. Every string is a prop: this package
has no text domain. `src-react/tokens.json` is the single token source;
`npm run tokens:build` regenerates the `--mhmui-*` block in
`assets/react/admin.css`, and `npm run tokens:check` fails CI on drift.
