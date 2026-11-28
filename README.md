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
mhmuicore_register( '0.27.5', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
```

At `plugins_loaded` priority 0 the highest registered version boots; the rest
stand down. The version string passed must match this package's own version.

**Every plugin that bundles the package must register it**, not just one of a
family. A plugin that vendors the package but never calls `mhmuicore_register()`
does not enter the version arbitration at all: its own copy is inert and it
silently depends on a sibling plugin having booted one. Deactivating the sibling
then takes its React screens with it.

### Do not touch this package at `plugins_loaded` priority 0

The boot runs at priority 0, so **your own code must not**. Anything that
reaches a class from this package — `new SlotRegistry(...)`, a `Capabilities`
lookup, an enqueue helper — belongs at **priority 1 or later**.

At equal priority WordPress calls callbacks in registration order, and
registration order follows *file load* order, which is neither obvious nor
under your control. WordPress sorts `active_plugins` on activation, so
`my-plugin-pro/my-plugin-pro.php` sorts **before** `my-plugin/my-plugin.php` —
`-` is `0x2D`, `/` is `0x2F`. The add-on's file loads first, its
`plugins_loaded` callback registers first, and at priority 0 it runs before the
free plugin has declared anything.

This was measured in a real consumer, not imagined: a Pro add-on filling a seam
slot at priority 0 hit a registry the free plugin had not opened yet and threw
`InvalidArgumentException` — a white screen on a paying customer's site. Had the
add-on guarded with `is_declared()` instead, it would have gone silently dead,
which is worse.

Two consequences worth stating separately:

- **A free plugin should declare its slots lazily**, from the accessor that
  hands out the registry, rather than from a hook. Then whoever asks first gets
  an open registry and hook order stops being part of the contract.
- **An add-on that does not bundle its own copy** of this package only gets the
  classes once the plugin that *does* bundle it has run its `register.php`. At
  priority 0, with the add-on's file loaded first, that has not happened — the
  failure there is `Class not found`, which explains nothing. Fill at priority
  1 or later and it cannot arise.

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

### Can a value go into a shortcode? (0.25.0+)

`MHMUiCore\Layout\ShortcodeMarkup::to_shortcode()` writes any value, but some
values cannot reach the shortcode callback whole on every WordPress version.
`ShortcodeMarkup::can_carry( string $value ): bool` tells you before you write
post content. It returns false for:

- a `]`: the shortcode ends at it on every version. `&#93;` / `&#x5d;` break
  it too, because WordPress 7.2's kses decodes them in post text;
- a double quote AND an apostrophe in the same value: from 7.2 kses decodes
  both, and an attribute has no escape for its own delimiter (one kind alone
  is fine);
- a backslash: the attribute parser unescapes it (`\t` arrives as a tab);
- a NO-BREAK SPACE or ZERO WIDTH SPACE: the parser turns it into a plain
  space, so `10 000 TL` written with a no-break space arrives with a plain one;
- a control character other than tab and line feed, and a carriage return:
  kses drops them, or (7.2) folds `\r\n` into `\n`;
- a reference to DEL or to 128-159 (`&#127;`, `&#128;`): 7.2 drops DEL and
  reads 128-159 as Windows-1252 (`&#128;` arrives as `€`);
- invalid UTF-8, which `esc_attr()` empties.

Each rule counts the character's references too (`&#92;`, `&nbsp;`, `&#13;`
...), because 7.2's kses decodes the references `esc_attr()` keeps; one it
neutralises, such as `&rsqb;`, arrives literally and is fine.

Measured with `do_shortcode()` after `wp_kses_post()` on 7.1 and 7.2-RC2
(`tests/Integration/ShortcodeMarkupRoundTripTest.php`). `to_shortcode()` itself
is unchanged, so what you already write keeps its bytes. Refuse the value, or
store it somewhere other than an attribute. An older copy can still win the
loader, so guard the call:

```php
use MHMUiCore\Layout\ShortcodeMarkup;

if ( method_exists( ShortcodeMarkup::class, 'can_carry' ) && ! ShortcodeMarkup::can_carry( $title ) ) {
	return new WP_Error( 'my_plugin_title_unsafe', __( 'This title cannot be stored in a shortcode.', 'my-plugin' ) );
}
```

This adds a public method, so it is a minor release: `^0.24` does not pick it
up; a consumer opts in with `^0.25`.

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
// Free core: declares the slot by name, then renders.
$seam = mhmuicore_slot_registry( 'myplugin' );
$seam->declare_slot( 'hero_after' );

$html = $seam->apply( 'hero_after', $html );     // fills run, then the
                                                 // myplugin_hero_after filter

// Pro add-on: fills the slot, and says what it added.
$seam->fill( 'hero_after', fn( $h ) => $h . '…' );
mhmuicore_capabilities( 'myplugin' )->grant( 'pro_badge' );
```

🔴 **The free core never asks.** `Capabilities` belongs to the paid layer
and to the extensions that coordinate with it -- `grant()` in a Pro add-on,
`has()` in code that is not the free core. A `mhmuicore_capabilities()` call
inside a free core is `Mode::isPro()` in a new shirt: it is grep-able, and a
WP.org reviewer reads an edition check regardless of what the branch does with
it. This house has already had a submission turn on exactly that shape. So the
rule is not "a capability is a may, not a must-not" -- it is that a free core
has nothing to ask. It declares slots and renders; the paid layer fills them.
The distinction still holds for the paid side: `if ( $caps->has('x') ) { do MORE }`
is a seam, `if ( ! $caps->has('x') ) { refuse what the core can already do }` is
crippleware wherever it is written.

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
| `src/Cli/` · `src/Component/ComponentScaffolder.php` | `wp mhm-ui` commands are development tooling, and the scaffolder is the engine of `make:component`, reached from nowhere else. Registration is guarded on the command class existing, so leaving them out costs the commands and nothing else. Take the two together: the scaffolder alone leaves a registered command that dies when run. |
| `src/Seam/PurityScanner.php` | The free-core purity gate. Its vocabulary IS the list a reviewer greps for — `license_key`, `activate_license`, `upgrade_to_pro`, `pro_only` — so it reads as the thing it exists to prevent. CI calls it from `vendor/`, where it stays; no runtime path touches it. |
| `src/Kit/IconConceptScanner.php` | The icon-concept convergence gate's engine (see "Icon concepts" below). Development tooling with no runtime path — a consumer's own CI `require`s it directly from `vendor/mhm/ui-core`, so it must ship there, but a WordPress.org reviewer greps a shipped tree and its class name and docblock read as gate machinery, the same shape `PurityScanner.php` reads as. |
| `src-react/` (the whole directory) | The package's JSX and token SOURCE. It is build-time input, and nothing on a user's site reads it -- WordPress does not execute JSX, and what loads is the bundle your build produced. It also holds the tier-lock component, whose class a reviewer reads as a paid-feature lock. |
| `assets/react/pro.css` | The stylesheet half of the same thing. A free core enqueues `react/admin.css` only. |
| `README.md` · `README-tr.md` · `package.json` | Developer documentation and build metadata. |

🔴 **Take `src-react/` as a unit, and take yours with it.** Excluding only the lock
component was tried on a real consumer and was wrong twice over: `src-react/index.js`
re-exports it, so the ZIP kept the name in the reviewer's grep AND gained a dangling
import. Then, with the directory gone, that consumer's own shipped sources still
imported from it -- importers without their modules. If your plugin ships its React
sources, they belong out of the ZIP for the same reason this package's do: keep the
stylesheets your PHP enqueues, drop the `.js` and `.jsx` your webpack merely reads.
Measured on that consumer, both mistakes cost a release round each.

The rest ships, and it is PHP: `bootstrap.php`, `register.php`,
`src/VersionSelector.php`, `src/Layout/`, `src/Component/`,
`src/Seam/SlotRegistry.php`, `src/Seam/Capabilities.php`. Those are runtime code
and the arbitration above is why they travel together.

The React modules are NOT in that list, and an earlier version of this page said
they were -- "the React modules a product actually imports are runtime code".
They are not. A product imports them at BUILD time; what reaches a browser is the
bundle its webpack emitted, which lives under the product's own `assets/`. That
one wrong sentence is what sent a consumer's ZIP out carrying JSX it could not
build from.

**Wire the loader through the registry, never by requiring the bootstrap:**

```php
require_once __DIR__ . '/vendor/mhm/ui-core/register.php';
mhmuicore_register( '0.27.5', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
```

Requiring `bootstrap.php` directly defines `MHMUICORE_VERSION` immediately, which
makes every other copy's bootstrap a no-op: the first plugin loaded wins instead
of the highest version. The literal must match this package's own version --
pin it with a check in your own gates, as the existing consumer does.

## The legacy `.mhm-*` block, and when it goes

`assets/react/admin.css` carries two class vocabularies. `.mhmui-*` is this
package's. `.mhm-*` is a consumer's, and it is there because this file did not
grow a legacy half — it **began** as one: 188 of those lines arrived in
`ba0e8ea` (2026-08-26), whose commit message says *"admin.css -> copied, not
moved"*, and the `.mhmui-*` kit was added beside it eight days later in
v0.8.0. Nobody has taken the first half out since.

It cannot simply be taken out now, and **scoping it under `.mhmui-admin` is
the same act**: a released consumer emits those classes on page roots that
carry no such ancestor, so both deletion and scoping leave real screens
unstyled. That is the permanent rule already written for CSS class names — an
alias's life is tied to the life of the old bundles in the field, not to
semver.

**What was missing until 0.14.1 is the exit condition.** The note inside
`admin.css` says the product keeps its `.mhm-*` rules until it migrates; that
sentence is about the *product's* copy and never said when *this package's*
copy goes. It goes when both of these hold:

1. **No released consumer emits those classes.** Measure it against the
   released tag, never a working tree — the tree can be migrated while the
   artifact in the field is not:
   `git -C <consumer> grep -l 'mhm-stats-grid\|mhm-stat-card\|mhm-widget' <released-tag>`
2. **The support window for the last release that did emit them has closed**,
   so the loader can no longer be asked to serve one.

Measured 2026-09-21: Rentiva's `main` emits none of them; its released v6.1.5
emits `.mhm-stats-grid` in 6 files, `.mhm-stat-card` in 7 and `.mhm-widget` in
14. Condition 1 is therefore **not** met, and the block stays.

The block is fenced with `stylelint-disable selector-class-pattern` so the rest
of the file is gated: a new `.mhm-*` rule written *outside* the fence fails CI
(`npm run check:css-namespace`). Do not widen the fence — that is how a debt
stops being visible.

## Admin React kit

`src-react/index.js` exports `StatCard`, `StatsGrid`, `StatusBadge`,
`Pagination`, `ProLock`, `Notice`, `Widget`, `Tabs`, `PageHeader`, `DetailList`,
`DetailLayout`, `ConfirmButton`, `ErrorBoundary`, `createApiClient`,
`useApi`, `createFormatter` and `tokens`. Every string is a prop: this package
has no text domain. `src-react/tokens.json` is the single token source;
`npm run tokens:build` regenerates the `--mhmui-*` block in
`assets/react/admin.css`, and `npm run tokens:check` fails CI on drift.

### PHP kit (0.11.0+)

Two functions render the key-figure card server-side, with the same DOM and
class set as `StatCard` / `StatsGrid` (gate 6 compares them):

```php
if ( function_exists( 'mhmuicore_stats_grid_html' ) ) {
	echo mhmuicore_stats_grid_html(
		array(
			array( 'label' => __( 'Pending', 'my-plugin' ), 'value' => number_format_i18n( $pending ), 'tone' => 'warning', 'icon' => 'clock' ),
			array( 'label' => __( 'Balance', 'my-plugin' ), 'value' => $balance_formatted, 'emphasis' => true, 'data' => array( 'stat' => 'balance' ) ),
		),
		4
	);
}
```

Props: `label`, `value` (already formatted), `icon` (Dashicons suffix — admin
only), `tone` (`success|warning|danger|info|neutral`, anything else is dropped),
`sub`, `delta` (`{direction: up|down|flat, text, label?}`), `emphasis` (bool),
`data` (`key => value` → `data-key`, keys `^[a-z0-9]+(?:-[a-z0-9]+)*$`, at most 32 characters). The second
argument is a column **ceiling**; the grid wraps in CSS. Avoid `direction` as
a key in your own `data` map — the kit already emits `data-direction` on the
delta line for every recognised direction; no collision is possible (different
elements), but it would leave a `[data-direction]` query matching both.

**Since 0.22.0** the column ceiling parses with PHP's `is_numeric()` semantics,
the same in both StatsGrid twins (DetailList has only the JSX twin and uses the
same parse): a number, or a numeric string (`'3'`, `' 3 '`, `'1e1'`, `'.5'`;
surrounding whitespace is PHP's ASCII set, so a no-break space does not count),
becomes `max( 1, trunc( n ) )`; a numeric value that is not finite (`NaN`,
`Infinity`, `'1e400'`) is 1; anything else (`'3cats'`, `'0x10'`, `''`, `true`,
`null`) falls back to the member's default (4 for StatsGrid, 2 for DetailList).
Before 0.22.0 the JSX twin read `'3cats'` as 3 while PHP fell back. One edge is
not mirrored: a finite value past PHP's integer range (`'1e20'`), which PHP
clamps and JS keeps.

**Since 0.21.0: "4 or 2, never 3."** When the cards fill whole rows of a
ceiling of 4 or 6 (4, 8, 12… cards under 4; 6, 12… under 6), both twins add
`mhmui-stats-grid--pairs` and the grid draws N or N/2 columns, never N−1 with
one card orphaned below; narrower than N/2 fits, the card-min floor takes over
(2 or 1 columns, which cannot orphan). Four cards of four, 16px root: 740px and
up → 4 · 364–739px → 2+2 (was 3+1 from 552px) · narrower → 1. The threshold
derives from `--mhmui-card-min`, `--mhmui-gap` and the ceiling, so it follows a
larger root font (20px root: 4 columns from 916px, derived; measured 2+2 at 740px, 4 at 920px) or your own card-min. It does not
enter for other counts or ceilings: 3 cards under 4 keep today's layout (the
rule would draw 2+1), 6 under 4 keep theirs (3+3, then 4+2), and a ceiling of 8
or more is left alone (once half the row stops fitting the grid falls back to
in-between counts). No prop: the count decides. A consumer that hand-wrote the
same rule for four cards (Rentiva Pro's vendor panel, K9) gets the same layout,
but the kit's front-end rule (0,3,0) now outranks its (0,2,0) rule, so that
copy no longer applies and can be removed.

**Since 0.12.0** the kit itself renders the direction cue for `delta` — an
`aria-hidden` mark before the text — because colour alone must never be the
only way a trend is conveyed (WCAG 1.4.1), and only the kit knows the
direction vocabulary. `delta.text` must therefore be **plain**: no arrow, no
sign, unchanged since. **Breaking change from <=0.11.x:** back then
`delta.text` was expected to carry its own arrow or sign (the kit added
none); a consumer still doing that after upgrading will show two marks.

**`flat` gets its own line too, not just `up`/`down` (0.13.0):** the mark is
↑ for `up`, ↓ for `down`, → for `flat` — "no data" (the `sub` line) and "no
change" (a `flat` delta) are different facts, and a zero trend must not
silently fall through to `sub` and lose its number. `flat` carries no colour
of its own in either stylesheet (the base delta colour is already neutral);
the → mark is its whole cue. **Behaviour change from <=0.12.x:** a `flat`
delta used to print no delta line at all, falling through to `sub` when one
was given. **A card that passes a `flat` delta together with `sub` now
silently loses its `sub` line** — the delta line always wins over `sub` once
`delta.direction` is recognised, the same priority `up`/`down` already had.
If you relied on `sub` showing through a `flat` delta, stop passing that
`delta` (or move the same text into `sub` alone).

**Since 0.13.0**, `delta.label` is how a consumer gives that direction cue an
accessible name: an optional string, already translated by the CONSUMER (e.g.
`"artış"` / `"azalış"` / `"rose 5% this month"`) — this package has no text
domain and cannot invent one. When present, both renderers put it in a
visually-hidden `<span class="mhmui-stat-card__delta-sr">`, right after the
`aria-hidden` mark, so a screen reader reads label + text together (e.g.
"artış 3 this month"). **Without `delta.label`, up and down still sound
identical to a screen reader** — the 0.12.0 mark is `aria-hidden` and
`data-direction` is not an accessible name either — behaviour is exactly
0.12.0, and the kit does not invent a fallback string. Escaped like every
other text prop (`esc_html` in PHP; React escapes text children).

**Fixed in 0.13.1** — the front-end `StatCard`'s value/label hierarchy (the
number dominating its label, the thing that makes a KPI card a KPI card) was
losing to a real theme's own CSS reset. It lived inside `assets/react/front.css`'s
zero-specificity `:where(...)` skin block, and a typical theme reset
(`p, … { font-size: 100%; font-weight: inherit }`, specificity 0-0-1 — not
even deliberate theme typography) beat it, rendering the value the same size
and weight as its label. The hierarchy declarations (`font-size`,
`font-weight`, `line-height`, `font-variant-numeric` on the value;
`font-size`, `letter-spacing`, `text-transform` on the label; `font-size` on
`sub`/`delta`) now sit in an unwrapped rule at 0-2-0 — the same specificity
this stylesheet's structure rules already use — so they beat any single-element
reset. Colour stays inside `:where(...)`: that part is still zero specificity
on purpose, so a theme can restyle it by outweighing 0-0-0 with any real
**unlayered** selector (see *Cascade layers* below). Nothing changed on the admin side (`admin.css` never wrapped this
in `:where()`). **If your theme intentionally restyled the front-end value/label
typography by outweighing the old 0-0-0 rule**, your selector now has to
match or beat the kit's 0-2-0. Either works:

- **the same 0-2-0 selector, loaded after the kit's `front.css`** — e.g.
  `.mhmui-front .mhmui-stat-card__value { … }` in a stylesheet that depends on
  the kit's handle, so an equal-specificity tie goes to your later rule; or
- **a higher-specificity selector**, which wins regardless of load order —
  e.g. `.your-theme .mhmui-front .mhmui-stat-card__value` (0-3-0) or
  `.mhmui-front p.mhmui-stat-card__value` (0-2-1).

A bare `.mhmui-stat-card__value` or `.mhmui-stat-card__label` is only 0-1-0
and **cannot** override the kit's rule, however late it loads.

**Cascade layers and `!important`.** Everything above is about **normal**
declarations, and assumes your override is **unlayered**. The kit's
stylesheets are deliberately unlayered and declare nothing `!important` (a
gate in `tests/Gate/layout.test.js` keeps it that way). The CSS cascade then
gives you exactly two ways to win:

1. **A normal declaration, unlayered**, that matches or beats 0-2-0 — the two
   routes above. A normal declaration inside `@layer` **always loses** to the
   kit, even when it is more specific and loaded later, and even against the
   zero-specificity colour rules: for normal declarations, unlayered beats
   layered before specificity is consulted.
2. **Any `!important` declaration** — layered or not, at any specificity. The
   cascade reverses layer precedence for `!important`, and every `!important`
   beats every normal declaration; since the kit has none, yours wins. This is
   the escape hatch for design systems that deliberately keep their overrides
   inside a layer.

Measured on a live page with 0.13.1 served, each override setting 40px / red:

| Override | Result |
|---|---|
| normal, unlayered, same 0-2-0 selector loaded after the kit | wins |
| normal, unlayered, higher-specificity selector | wins |
| normal, **inside `@layer`**, even a far more specific selector | **loses** |
| normal, inside `@layer`, label colour against the 0-0-0 skin | **loses** |
| **`!important` inside `@layer`**, value size and label colour | wins |
| **`!important` unlayered**, even a bare 0-1-0 class | wins |

The kit cannot remove the layer trap by moving into a layer itself: a
theme's unlayered CSS reset would then beat the kit's hierarchy again — the
exact defect 0.13.1 fixed.

These 0.11.0 components need the 0.11.0 stylesheet — enqueue through
`mhmuicore_enqueue_kit()` so the loader serves the winning copy; under an
older stylesheet the grid has no columns.

Wrap the strip in the surface scope and enqueue its stylesheet:
`<div class="mhmui-admin">…</div>` + `mhmuicore_enqueue_kit( 'admin', $your_vendor_ui_core_root )`
(front end: `mhmui-front` / `'front'`).

Escape at the echo — the wrappers return escaped HTML, but no ruleset of
yours can tell the WordPress.org reviewer so:

```php
if ( function_exists( 'mhmuicore_stats_grid_html' ) ) { // an older ui-core copy may have won the loader
	echo wp_kses_post( mhmuicore_stats_grid_html( $cards, 4 ) );
}
```

**If your plugin goes through WordPress.org (or runs Plugin Check), do not
list the wrappers under `customEscapingFunctions` in your `phpcs.xml`.**
That silences your local WPCS run and nothing else: Plugin Check runs its
own `WordPress` standard and never reads your ruleset, so the same echo is
still `EscapeOutput.OutputNotEscaped` to it. The gap is invisible until the
acceptance gate runs (measured: six errors in a consumer's CI, 2026-09-19).
A plugin that never meets Plugin Check could use the ruleset entry, but
escaping at the echo keeps one bar for every tree you ship.

What `wp_kses_post()` keeps: with core's default allowlists, every branch
the kit renders (dashicon span, tone/emphasis classes, `aria-hidden`,
`data-*` hooks, the delta mark and accessible name, the grid's
`style="--mhmui-columns:N"`) passes byte for byte on **WordPress 6.6+**.
Custom-property assignment in inline styles is allowed by
`safecss_filter_attr()` since 6.1.0. `data` keys are hyphen-separated runs of
`[a-z0-9]` (0.22.0 tightened this: a leading, trailing or doubled hyphen was
stripped by kses on WordPress ≤ 6.5, so the kit no longer emits it). A plugin
filtering `wp_kses_allowed_html` or `safe_style_css` can still strip them.
The integration suite pins this on every WordPress version CI runs (6.6, 7.1 and the 7.2 candidate, since 0.26.0):
`tests/Integration/KitEscapingTest.php`, so a kit change that kses would
strip fails there first.

### Page layout (0.11.0+)

| Surface | Markup | Rule |
|---|---|---|
| admin | `<div class="mhmui-admin mhmui-admin-page">` | flows full width; put `mhmui-measure` on a **form column**, never on the page |
| front | `<div class="mhmui-front mhmui-front-page">` | centred, capped at `--mhmui-page-max`; named query container `mhmui-page` |

**Both shell classes require the element they're on to carry NO horizontal
margin from anywhere else** — no theme wrapper margin, no page-builder
section margin, no inline `style="margin:…"`. Both compute their box as
`width: 100%` of the container; a margin this repo doesn't know about
(WP core's own `.wrap` on the admin side, a theme/page-builder wrapper on
the front end) makes the box overflow its container by exactly that margin.
Measured on the admin side (2026-09-18): `.mhmui-admin-page` sits on the
same element as WP core's `.wrap` (`margin: 10px 20px 0 2px`), and forcing
`width: 100%` on top of that fixed margin clipped the page's fourth KPI
card and grew a horizontal scrollbar. `.mhmui-front-page` is exactly as
exposed — put it on an element with a margin from your theme or a
page-builder section and the same overflow reproduces there.

Front-end layouts query the container, not the viewport:
`@container mhmui-page (width < 40rem) { … }`. Thresholds are fixed numbers
(custom properties are not allowed in a container condition): **narrow < 40rem ≤
medium < 64rem ≤ wide**. Keep `position: fixed` overlays outside the shell.

### Icon concepts (0.14.0+)

`icon` takes a CONCEPT; an unknown value is still printed as a raw Dashicon
suffix, so every 0.13 call site keeps working. No concept name is itself a
Dashicon name -- that rule is gated (tests/Integration/IconVocabularyTest.php),
because a concept that shadowed a real icon would silently change what an
existing call site renders.

| Concept | Dashicon | Concept | Dashicon |
|---|---|---|---|
| `revenue` | `money-alt` | `pending` | `clock` |
| `total` | `chart-bar` | `active` | `yes-alt` |
| `count` | `list-view` | `new` | `plus-alt` |
| `rate` | `chart-line` | `returning` | `update` |
| `customers` | `admin-users` | `time` | `calendar-alt` |
| `items` | `products` | `place` | `location-alt` |

**Where icons work:** the admin. `mhmuicore_enqueue_kit('front')` declares no
`dashicons` dependency and `front.css` ships no icon source, so on the front end
the span renders and no glyph is drawn. Deliberate: a free core's every page
should not carry core's icon font for one card. Front-end icons arrive with the
inline-SVG layer.

**Registering your own concepts** -- at `plugins_loaded` priority >= 1 (the
package boots at 0), and **in each bundle**:

```php
\MHMUiCore\Kit\Icons::register( array( 'vehicles' => 'car', 'bookings' => 'calendar' ) );
```

```js
import { registerIcons } from '@mhm/ui-core';
registerIcons( { vehicles: 'car', bookings: 'calendar' } );
```

🔴 **Your own concept name must not be a Dashicon name either.** The rule
stated above the table -- no concept name is a Dashicon name -- is enforced by
`IconVocabularyTest` walking `Icons::map()`, but in this package's own CI
`$registered` is empty: the gate measures the seed table, not yours. Register
`array( 'calendar' => 'calendar-alt' )` and `.dashicons-calendar` is a real
Dashicon suffix -- from that call onward every raw-suffix call site still
writing `'icon' => 'calendar'` silently draws a different icon
(`calendar-alt`), because `resolveIcon()` checks your registration before
falling through to a raw suffix. This is the same class of break two
independent reviews flagged as blocking for `location`. **How to measure it in
your own tree:** load your own `register()` call (from your bootstrap, or a CI
fixture) before running `IconVocabularyTest` -- it walks `Icons::map()`, which
includes whatever is currently registered, not just the seed.

The PHP registry belongs to the winning ui-core copy and is shared by every
plugin on the site. The JS one is NOT: it lives in the bundle that imports it,
so a second plugin's bundle needs its own `registerIcons` call.

**The convergence gate** -- the scanner ships, `bin/` does not:

```php
<?php // bin/check-icon-concepts.php in YOUR repo
require 'vendor/mhm/ui-core/src/Kit/Icons.php';
require 'vendor/mhm/ui-core/src/Kit/IconConceptScanner.php';
\MHMUiCore\Kit\Icons::register( require 'config/icon-concepts.php' );

// Anchor on YOUR wrapper names: this package's own function names find zero
// call sites in a product that wraps the kit (measured in Rentiva, 2026-09-20).
$r = ( new \MHMUiCore\Kit\IconConceptScanner( array( 'YourPlugin\\Kit::grid', 'ui-core/src-react/components/Stat' ) ) )
	->scan( array( 'src', 'src-react' ) );

if ( 0 === $r['files'] || 0 === $r['concepts'] + count( $r['raw'] ) + count( $r['unknown'] ) ) {
	fwrite( STDERR, "EMPTY-SET: the gate measured nothing\n" );
	exit( 2 );
}
exit( array() === $r['raw'] ? 0 : 1 );
```

**If you publish to WordPress.org:** exclude the scanner from the final ZIP --
it is development tooling and a reviewer greps a shipped tree:

```
/vendor/mhm/ui-core/src/Kit/IconConceptScanner.php
```

Blind spots (also in the scanner's docblock): an icon name in a variable, one
built with `sprintf()`, a dynamic JSX prop, a multi-line object literal, a
template literal, a quoted key, and any file that mentions no anchor.

### Vertical rhythm (0.14.0+)

`.mhmui-stats-grid` no longer carries `margin-top`. The page shell
(`.mhmui-admin-page` / `.mhmui-front-page`) spaces its kit-member children with
`--mhmui-space-3`. Core's spacing of headings, paragraphs and notices is
untouched. A surface that does not use the shell gets no rhythm, and a grid
nested in a wrapper is spaced by its wrapper.

Rhythm members: `.mhmui-stats-grid`, `.mhmui-widget`, `.mhmui-pagination`, `.mhmui-notice`, `.mhmui-tabs`, `.mhmui-page-header`, `.mhmui-detail-layout`, `.mhmui-toolbar`, `.mhmui-table-card`, `.mhmui-tabs-bar`, `.mhmui-section`

**0.24.0 default change:** a DataTable (`.mhmui-table-card`) that is a direct
child of the shell, after a sibling, now gets 12px above it (before: 0). The
loader serves the newest copy site-wide, so this shows without a rebuild. A
screen that already spaces it itself -- for example a shell that is also a
flex column with its own `gap` -- should drop its own spacing, or the two add up.

Only a DIRECT child of the shell that carries one of these classes is spaced. A
block of your own (a filter bar, a button row in your own class) gets no
spacing from the shell -- only whatever margin it carries itself, which for a
plain `div` is none. Put it on a member class or inside one -- for a block
of your own, that class is `.mhmui-section` (0.27.0+, below).

**0.24.0: `.mhmui-toolbar`** is the row of actions between two blocks (the
buttons under a KPI band, above a table): a wrapping flex row with an 8px gap
(`--mhmui-space-2`), no outer margin of its own, in both kits (on the front end
the wrapper is `mhmui-front mhmui-front-page`, and core's `.button` classes are
admin-only -- use your theme's buttons there). Markup only, no component:

```html
<div class="mhmui-admin mhmui-admin-page">
	<!-- StatsGrid -->
	<div class="mhmui-toolbar">
		<a class="button button-primary" href="…">Add new</a>
		<a class="button" href="…">Export</a>
	</div>
	<!-- DataTable (.mhmui-table-card) -->
</div>
```

**Sub-pages carry a back link.** A screen reached from a list -- detail, edit,
add -- starts with a `PageHeader` whose `back` points at that list, as the
shell's first child. A top-level screen (the list itself, a settings page) has
no back link: the admin menu is its way back.

```jsx
<div className="mhmui-admin mhmui-admin-page">
	<PageHeader
		back={ { label: __( 'All bookings', 'my-plugin' ), href: listUrl } }
		title={ booking.title }
		level={ 1 }
	/>
	{ /* … */ }
</div>
```

`PageHeader` draws no link when `back.label` or `back.href` is empty, so a
sub-page that passes a missing URL loses its way back silently -- test that
your sub-pages render `.mhmui-page-header__back`. The package can only check
its own half: the rhythm list is the same in both kits and in this README, and
`PageHeader` draws the link from `back` (`tests/Gate/layout.test.js`,
`PageHeader.test.jsx`). Which of your screens are sub-pages only you know.

### Detail views (0.15.0+)

0.15.1: ConfirmButton's rules outrank WordPress core's `.wp-core-ui .button`
(0,2,0) and its `:hover/:focus/:active` (0,3,0) -- in 0.15.0 they lost in
wp-admin (40px targets, a focused primary confirm drawn as an outline).
Visible change: a locked or busy primary confirm is drawn on the surface
colour with core's grey text instead of a faded accent fill, and nothing is
faded any more.

0.15.2: the 44px targets hold inside a list table's `.tablenav` and inside a
`.button-group.button-small` too; a locked danger confirm also drops its red
border. `PageHeader` draws no back link when `back.label` is missing, a
boolean, or an empty or blank string; `Tabs` items without an `id` no longer
share a React key; `DetailList` falls back to 2 columns when `columns` has no
leading number and shows `emptyText` for a boolean value -- format booleans
yourself.

Five members for a detail screen: `Tabs` (page sections as real links,
`aria-current="page"`, a badge whose digit is `aria-hidden` and whose meaning
is `badgeLabel`), `PageHeader` (back link, an `h2` by default -- the page's
`h1` is WordPress's), `DetailList` (a `<dl>`; `''`/`null`/`undefined` show
`emptyText`, `0` is a value; a tone adds a dot, never colours the text),
`DetailLayout` (main column + sticky aside that drops below on a narrow
container) and `ConfirmButton` (a two-step confirmation in the page, in place of
`window.confirm`; the consumer catches errors inside `onConfirm`, a rejection is
not swallowed). The shell's rhythm now spaces `.mhmui-tabs`,
`.mhmui-page-header` and `.mhmui-detail-layout` too.

`Tabs` has a PHP twin, `mhmuicore_tabs_html( array $props )`: guard the call
with `function_exists()` while an older copy can still win the loader, and echo
it through `wp_kses_post()`. As with 0.11.0, the new JSX needs the new
stylesheet -- load it through `mhmuicore_enqueue_kit()`.

### Artboard variants (0.16.0+)

Everything below is opt-in except one thing: the warning badge ink. Moving a
consumer from `^0.15` to `^0.16` is a minor bump; a call that passes none of the
new props renders the 0.15.2 markup byte for byte.

**The one default change.** `.mhmui-status--warning` text now reads
`--mhmui-warning-ink` (`#8a6100`, 4.5:1 on the soft warning fill) instead of
`#b08000`, which sat below 4.5:1. Warning badges get darker everywhere; a page
that overrode the old colour can drop its override.

**New tokens** (additive, admin and front scopes): `--mhmui-warning-ink`,
`--mhmui-border-divider` (`#dcdcde`), `--mhmui-text-secondary` (`#50575e`),
`--mhmui-danger-ink` (`#b32d2e`), `--mhmui-border-strong` (`#8c8f94`).

**New component `Button`** (`variant` primary | secondary | danger | plain |
neutral; `size` md 36px | sm 32px; both 44px at 782px and below). The label is
`children`, an optional decorative `icon` sits before it, `href` renders an
`<a>`. `disabled` is `aria-disabled`, so the element keeps focus and a disabled
link loses its `href`. **Since 0.23.0** a link also takes `target` and `rel`
(e.g. `target="_blank" rel="noopener noreferrer"`); a button ignores both.

**Opt-in props on existing members:**

- `Tabs`: `variant="underline"`, `showZero` (draw a chip for a count of 0) and a
  per-item `badgeTone` (success | warning | danger | info | neutral). The PHP
  twin `mhmuicore_tabs_html()` takes the same three. A CSS-only
  `.mhmui-tabs-bar` wraps tabs plus a trailing control.
- `Pagination`: `variant="footer"` (table-card footer), `summary` (range text on
  the left, footer only) and `labels.page`.
- `Widget`: `level={2}` and `variant="plain"` (no header band; the card pads
  itself).
- `ConfirmButton`: `size="compact"` (36px, 44px at 782px and below).
- `PageHeader`: `badges[]` (`{ text, tone? }`, more badges after `badge`) and a
  page-level style for `level={1}` (`h1.mhmui-page-header__title`); the default
  `h2`/`h3` keep their 0.15.x weight.

As with 0.11.0, the new JSX and CSS go together: load the stylesheet through
`mhmuicore_enqueue_kit()`.

### Collapsible Widget (0.17.0+)

Moving a consumer from `^0.16` to `^0.17` is a minor bump. Everything below is
opt-in except two default-look changes; a `Widget` call that passes none of the
new props renders the 0.16.0 markup byte for byte.

**Two default-look changes.**

1. A page-level `h1.mhmui-page-header__title` no longer inherits WordPress
   core's `.wrap h1` padding (`9px 0 4px`). A page that compensated for that
   padding can drop its override.
2. A `Tabs variant="underline"` strip now scrolls inside its bar on narrow
   containers instead of overflowing, and its focus ring is drawn inset.

**`Widget` collapsible** (opt-in): `collapsible`, `defaultOpen` (default true),
`storageKey`, `open` (controlled) and `onToggle( next )`. The heading holds a
disclosure button with `aria-expanded` / `aria-controls`; the body stays mounted
(`hidden`), so typed input survives; `actions` render only while open; the whole
header row is clickable; when a card closes with focus inside its body or its
header actions, focus returns to the toggle. The toggle is 44px at 782px and below and the chevron does
not animate under `prefers-reduced-motion`. New classes: `mhmui-widget--collapsible`,
`mhmui-widget--collapsed`. The body id comes from React `useId()` and contains
`:`, so a CSS selector or `querySelector` lookup by that id must escape it
(`CSS.escape()`).

**New named exports:** `usePersistentOpen( storageKey, defaultOpen )` (remembers
`'1'` / `'0'` in `localStorage`, falls back silently when storage is unavailable)
and `useContainerWidth( threshold = 600 )` → `[ setRef, layout, measured ]`; `measured` stays false until the first non-zero width (true at once without ResizeObserver), so a layout-keyed state can wait for it. `measured` stays false for as long as the container is never laid out (for example inside `display: none`); render a fallback in that case. The width compared with the threshold is the container's content box (padding excluded).

**Fixes:**

- `Button`: a caller's `aria-disabled` can no longer override the computed
  disabled state.
- `PageHeader`: the badges wrapper is dropped when neither `badge` nor any
  `badges` item has text.

0.17.1: a collapsible `Widget` no longer pulls focus back from the page. When
focus had left the card's body or header actions for empty page space, a close
after that (a controlled `open` change, including one made by that same click,
or a `storageKey` change) moved focus to the toggle and scrolled the page there;
it now stays where the user left it. Closing with focus still inside the body or
on an action, closing while the browser window is in the background, and the
toggle click in browsers that do not focus a clicked button (Safari), still hand
focus to the toggle; a toggle press released elsewhere or made with another
mouse button does not count as one. The design-system Tabs card now shows
`variant="underline"` inside a `.mhmui-tabs-bar`, including a narrow container
where the strip scrolls. No new props or classes; `^0.17` picks it up.

### ConfirmButton extra props (0.18.0+)

Any prop `ConfirmButton` does not name itself (`data-*`, `aria-*`, `id`, ...) now
lands on the **trigger** button. It never reaches the `.mhmui-confirm` root or
the two prompt buttons, so a key or an id is never duplicated. While the prompt
is open the trigger is not in the DOM; a consumer that tracks focus across that
state should look up the `.mhmui-confirm` root. The kit's own `type`,
`className`, `aria-disabled`, `aria-describedby` (pass `describedBy`),
`onClick` and `ref` win over a passed value (a `ref` is not forwarded). An `aria-label` must contain the visible
`label` (WCAG 2.5.3).

`Tabs` and `PageHeader` now share one internal plain-left-click guard
(`src-react/plain-left-click.js`, not re-exported from the index); their click
behaviour is unchanged. No new classes, no CSS change. This adds a referenceable
surface, so it is a minor: `^0.17` does not pick it up; a consumer opts in with
`^0.18`.

### Tables (0.19.0+)

Four pieces and one optional hook for an accessible admin table. The rows and
cells stay yours; the kit owns the card, the caption, the sortable header and
the select boxes.

```jsx
import { DataTable, SortHeader, SelectAll, RowSelect, useTableSelection, nextSort } from '@mhm/ui-core';

const selection = useTableSelection( rows.map( ( r ) => r.id ) );

<DataTable caption={ __( 'Customers', 'my-plugin' ) } captionHidden className="my-table" layout="stack" footer={ <Pagination … /> }>
	<colgroup><col /><col /><col style={ { width: '140px' } } /></colgroup>
	<thead>
		<tr>
			<th scope="col" className="mhmui-table__select">
				<SelectAll state={ selection.state } onChange={ selection.setAll } label={ __( 'Select all', 'my-plugin' ) } disabled={ rows.length === 0 } />
			</th>
			<SortHeader label={ __( 'Customer', 'my-plugin' ) } sortKey="name" sort={ sort } onSort={ ( key ) => { setSort( nextSort( sort, key, 'desc' ) ); setPage( 1 ); } } />
			<SortHeader label={ __( 'Total spent', 'my-plugin' ) } sortKey="spent" sort={ sort } onSort={ … } align="end" />
		</tr>
	</thead>
	<tbody>
		{ rows.map( ( r ) => (
			<tr key={ r.id }>
				<td className="mhmui-table__select"><RowSelect checked={ selection.isSelected( r.id ) } onChange={ () => selection.toggle( r.id ) } label={ … } /></td>
				<th scope="row" className="mhmui-table__primary">{ r.name }</th>
				<td className="mhmui-table__num" data-label={ __( 'Total spent', 'my-plugin' ) }>{ r.total }</td>
			</tr>
		) ) }
	</tbody>
</DataTable>
```

- `caption` names the table and is required. An empty caption is drawn empty
  and **no kit gate catches it**; assert its text in your own test.
  `captionHidden` hides it visually when the page title already names the
  table; it is never removed.
- `SortHeader` puts `aria-sort` on the **sorted column only**; the other
  headers carry none. `onSort( sortKey )` only reports the click: the
  direction, the data and the page reset are yours (`nextSort` turns the
  direction on the same column and starts another at `firstDirection`).
- `SelectAll` / `RowSelect` take plain props. `SelectAll`'s `label` becomes
  visible in the stacked layout, so keep it short ("Select all"). A checked row
  is tinted by the kit; add no class for it. Do not give the select cell WP's
  `check-column` class: core list-tables.css stretches a `.check-column label`
  over the cell (`position: absolute`), which breaks the kit's hit area.
- `useTableSelection( ids )` is page-scoped: when the ids change, the ones that
  left are dropped and do not come back. Ids are compared with `===`, so keep
  their type consistent (a REST payload that sends `7` once and `"7"` the next
  selects nothing). `toggle`, `setAll` and `clear` keep their identity;
  `isSelected` is per render. Locking during a bulk request is yours.
- `layout="scroll"` (default) scrolls sideways in a narrow container.
  `layout="stack"` turns rows into cards below a 40rem container: give every
  cell a `data-label` (its column name, spoken and shown), mark the title cell
  `mhmui-table__primary` and an actions cell `mhmui-table__actions`. Use
  `<th scope="row">` for the title cell if you want screen readers to announce
  each row by it. Use `scope="col"` on your own headers. In this layout the kit
  writes explicit table roles where you set none.
  A stacked value cell is a flex row (label at the start, value at the end): put **one**
  child in it. Wrap several pieces (an amount and a badge, two spans) in a
  single element, or the row spreads them apart.
- The table is written in logical directions (0.20.2): under `dir="rtl"` it
  mirrors with no RTL stylesheet. A left-to-right value inside it (a phone
  number, an IBAN, a code) still needs an inline wrapper (`<span dir="ltr">`
  or `<bdi>`; not `dir` on the cell itself, which would flip that cell's
  alignment), or the browser reorders its digit groups (`+90 532 000 11 22`
  reads `22 11 000 532 90+`). That is the value's own direction, not the kit's.
- Column widths go in a `<colgroup>`. `className` on `DataTable` and
  `SortHeader` adds your own class to the table and the header.

This adds referenceable surface (new files under `src-react/`), so it is a
minor: `^0.18` does not pick it up; a consumer opts in with `^0.19`. No
existing class, token or prop changed.

### Server lists (0.20.0+)

`useServerList` holds the state of a server-paged list: the query, the page,
the last accepted answer and a ready `pagination` for `Pagination`. It draws
nothing; the table, the filter and the words stay yours.

```jsx
import { useServerList, useDebouncedValue, Pagination, Tabs } from '@mhm/ui-core';

const list = useServerList( ( params ) => api.getVendors( params ), {
	initialQuery: { search: '', status: 'all' },
	perPage: 20,
	// readTotals: ( res ) => ( { total: res.total, pages: res.total_pages } ),
} );

// Search: the box keeps its own state so typing stays instant.
const [ search, setSearch ] = useState( '' );
const debounced = useDebouncedValue( search ); // 300 ms
const { setQuery } = list; // stable, so the effect lists it without refiring
useEffect( () => setQuery( { search: debounced } ), [ setQuery, debounced ] );

// Status filter: a <select> ...
<select value={ list.query.status } onChange={ ( e ) => list.setQuery( { status: e.target.value } ) }>…</select>
// ... or counted Tabs, with the counts your endpoint returns (`list.data?.counts`).
<Tabs
	label={ __( 'Filter by status', 'my-plugin' ) }
	current={ list.query.status }
	items={ STATUSES.map( ( s ) => ( { id: s.id, label: s.label, href: `${ pageUrl }&status=${ s.id }`, badge: list.data?.counts?.[ s.id ] } ) ) }
	onSelect={ ( id ) => list.setQuery( { status: id } ) } // Tabs prevents the navigation on a plain left click
/>

{ list.totalPages > 1 && <Pagination { ...list.pagination } labels={ … } /> }

// "41–48 of 48" -- the words are your translation. `range` is null until the
// first answer is accepted (and always without `perPage`), so guard it.
const summary = list.range
	? sprintf( _n( '%1$d–%2$d of %3$d vendor', '%1$d–%2$d of %3$d vendors', list.range.total, 'my-plugin' ), list.range.from, list.range.to, list.range.total )
	: '';
```

- `setQuery( patch )` merges the patch and goes back to page 1 in **one**
  update, so a filter, search or sort change sends exactly one request.
  A patch whose values all equal the current ones changes nothing, which is
  why the mount-time `setQuery( { search: '' } )` above sends no second request.
- Query values must be **primitive**. An array is a new reference on every
  render and would reset the page each time; send several values as a
  comma-separated string (`status: 'failed,retry'`) and split it on the server.
- `''`, `null` and `undefined` are left out of the request. Nothing else is:
  `'all'`, `0` and `false` are sent, because what they mean is your server's.
  `page` and `per_page` keys in a query are ignored; the page changes only
  through `setPage`.
- Only the newest request is accepted. An older answer, success or failure,
  is dropped, and unmounting drops everything in flight. The request itself is
  not cancelled.
- If an answer reports fewer pages than the current page (the last row on the
  last page was deleted, then `refetch()`), the page is pulled back to the
  last one with one more request. `totalPages` is never below 1, so an empty
  list that reports `pages: 0` still shows "1 / 1".
- While a request is in flight `data` keeps the last accepted answer and
  `loading` is `true`; keep the table on screen instead of hiding it. `range`
  belongs to the rows on screen, not to the page being requested, and is
  `null` until the first answer is accepted or when there is no `perPage`.
- If a pull-back request fails, `page` already shows the last page while
  `data` and `range` still hold the rows that were on screen, and `error` is
  set. Show the error; `refetch()` retries that last page.
- `error` is the raw rejection (an `Error` or a `{ code, message }` object):
  turn it into words before you render it. Notices, selection and bulk-action
  errors stay yours, e.g. `useEffect( () => { if ( list.error ) notify( … ); }, [ list.error ] )`.
- `readTotals` defaults to `( res ) => ( { total: res.total, pages: res.pages } )`.
  Values that are not whole non-negative numbers read as 0; a `readTotals`
  that throws counts as a rejection.
- `fetchPage` and the options are read from the last committed render; you do
  not need `useCallback`, and changing them alone sends no request.
- React StrictMode (development) runs the mount effect twice, so two mount
  requests go out and only the second is accepted.
- `useApi` (0.20.0): an older request's answer is now dropped too, when its
  deps change or on `refetch`.

This adds referenceable surface (new files under `src-react/hooks/`), so it is
a minor: `^0.19` does not pick it up; a consumer opts in with `^0.20`. No
existing class, token or prop changed.

### Twin parity and small fixes (0.26.0+)

- **PHP reads a `json_decode()` row like an array.** `mhmuicore_stats_grid_html()`
  and `mhmuicore_tabs_html()` used to skip a card or tab that was a `stdClass`
  while the JSX twins drew it; a card's `delta` and `data` are read the same
  way. Other objects (a Closure, an exception, an `ArrayObject`) and anything
  that is neither an array nor a `stdClass` are still skipped silently: no
  placeholder, no notice.
- **`Tabs` reads `href`, `id` and `current` as the PHP twin does.** An integer
  is its decimal string, so `href: 5` draws and `id: 2` is current for
  `current: '2'`; `true` is `'1'`; `null`, `false`, objects and arrays are
  empty, and an item with an empty `href` is skipped. Floats can still differ
  in spelling between the twins (`1e21`, `0.1 + 0.2`), so pass strings. `onSelect`
  still receives `id` as given.
- **`Pagination` warns once.** A click on a page button when `onChange` is not
  a function logs one `console.warn` per mounted component (a misspelt prop
  name was silent before). Nothing is logged on render. A consumer test that
  clicks a Pagination without `onChange` under `@wordpress/jest-console` must
  now expect it: `expect( console ).toHaveWarned()`.
- **Server rendering is quiet.** DataTable, SelectAll and Widget use a layout
  effect only where there is a `window`, so `renderToString` in Node no
  longer logs React 18's "useLayoutEffect does nothing on the server" (under
  jsdom, where `window` exists, it still does).
- CI runs the integration suite on WordPress 6.6 too, the version the kses
  sentence above promises.

Which half reaches whom: the JSX changes (`Tabs`, `Pagination`, the layout
effect) ship in each consumer's own bundle, so a consumer gets them when it
moves to `^0.26` and rebuilds. The PHP change is served by the loader: once
any plugin on a site ships 0.26, every consumer there gets it, `^0.25` ones
included. It changes output only for `stdClass` input: a `stdClass` card or
tab used to be skipped and now draws, and in `mhmuicore_stat_card_html()` too
a `stdClass` `delta` now draws the delta line where 0.25 fell back to `sub`,
and a `stdClass` `data` adds its `data-*` attributes.

### Section rhythm and the PHP page header (0.27.0+)

**Two rhythm members join.** `.mhmui-tabs-bar` (tabs plus a trailing control)
and `.mhmui-section` are spaced by the shell like the rest: a direct child of
`.mhmui-admin-page` / `.mhmui-front-page`, after a sibling, gets
`--mhmui-space-3` (12px) above it.

`.mhmui-section` is a hook with no rule of its own -- no margin, padding,
border or display. Put it on a block of your own (a KPI row, a two-column
layout, a filter bar, a card, a footer) instead of giving that block a local
margin:

```html
<div class="mhmui-admin mhmui-admin-page">
	<!-- PageHeader -->
	<div class="mhmui-section my-plugin-filters">…</div>
	<div class="mhmui-section my-plugin-two-columns">…</div>
</div>
```

The shell's rule is (0,2,0), so it overrides a one-class `margin-top` of
yours; a `margin-bottom` you keep still adds. **Default change:** a
`.mhmui-tabs-bar` that is a direct child of the shell now gets 12px above it
from the loader's CSS, site-wide, without a rebuild; a screen that spaced it
itself (with a heavier selector, or a flex `gap` on the shell) should drop
that spacing.

**`mhmuicore_page_header_html( array $props ): string`** -- the PHP twin of
`PageHeader`, for sub-pages drawn in PHP (detail, edit, add). Same markup and
classes as the JSX (gate 6 compares them); guard with `function_exists()`
and echo through `wp_kses_post()`:

```php
if ( function_exists( 'mhmuicore_page_header_html' ) ) {
	echo wp_kses_post(
		mhmuicore_page_header_html(
			array(
				'back'    => array(
					'label' => __( 'All customers', 'my-plugin' ),
					'href'  => admin_url( 'admin.php?page=my-plugin-customers' ),
				),
				'title'   => $customer->name,
				'badge'   => array( 'text' => __( 'Active', 'my-plugin' ), 'tone' => 'success' ),
				'meta'    => sprintf( '#%d', $customer->id ),
				'actions' => '<a class="button" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'my-plugin' ) . '</a>',
				'level'   => 1,
			)
		)
	);
}
```

Props as in the JSX, with these differences: `title`, labels, badge text and
`meta` are text; `actions` is an HTML string, run through `wp_kses_post()`
inside the function (so an `<input>` in it is stripped -- put form
controls after the header, and actions kses strips to nothing draw no
wrapper); `back` has no `onClick`. `back.href` goes through `esc_url()`: an
`href` it empties (`javascript:`, a blank string) draws no back link, and a
relative path that does not start with `/`, `?`, `#` or a `*.php` file gets
`http://` in front (`customers/list` becomes `http://customers/list`) -- pass
`admin_url( … )` or a `?page=…` query. As in the JSX, no link is drawn when
`back.label` is missing, a boolean or blank, or `back.href` is empty; a
`stdClass` row reads like an array. Pass a role name as `tone` and plain
strings as text. Since 0.27.2 the twins agree on these edges too, held by
one corpus both test suites draw (`tests/Fixtures/page-header-twin-edges.json`):
a `null` tone is neutral, a boolean badge text draws no badge, a
whole-number float `level` (`1.0` from `json_decode()`) is that level,
`actions` that are a number, blank or an empty list draw no wrapper, and a
`back.label` of only spaces in JS's sense (no-break and other Unicode
spaces) draws no link. They can still differ on a `tone` that is not a
role name (PHP runs it through `sanitize_html_class()`), a node or a
non-empty list where the PHP twin takes text, or a float of 1e15 or more
as text (spelled differently).

### Long money values in a narrow StatCard (0.27.5)

- **`fmtMoney` joins symbol and number with a no-break space** (U+00A0) for
  `left_space` and `right_space`, as WooCommerce's own price format does
  (`&nbsp;`, `get_woocommerce_price_format()`). It used a plain space, so a
  narrow card wrapped the symbol onto its own line. A consumer test that
  compares the output with a plain-space string needs ` ` there.
- **A narrow `StatCard` keeps its value inside.** The card wraps: when the
  body cannot keep `8rem` beside the icon, it takes its own line under it,
  and the value scales with the body (`clamp( 16px, 14cqi, 24px )`, 24px from
  a 172px body up). Measured in Chrome: at 390px the two-column grid left the
  body 94px and "152.050,00 ₺" overflowed it by 24px (44px with the no-break
  space alone); now 0 from 320px to 1920px. Wide cards (1280px and up with
  four columns) look as before. Applies to the PHP twin too (same markup).
- Served by the loader, so both show without a rebuild.

### Stacked table cards: long values and the title row (0.27.4)

- **Long unbroken values break inside the card.** Stacked body cells now
  carry `overflow-wrap: anywhere` and `min-width: 0`. An e-mail with no
  hyphen or a long URL used to overflow its cell (118px at 320px) and the
  whole stacked card scrolled sideways. A consumer's own `overflow-wrap`
  patch on stacked cells can go. Labels (`data-label`), number cells
  (`.mhmui-table__num`) and status pills keep `overflow-wrap: normal`, so
  they never split mid-word or between digits; a figure too wide for the
  card overflows instead, so do not put a long code or id in a
  `.mhmui-table__num` cell. The wide (scroll) layout is unchanged.
- **The title cell sits beside the checkbox whatever the cell order.**
  `.mhmui-table__primary` is pinned to the card's first row. Placed by
  order, a labelled cell written before it took row 2 and pushed the title
  to row 3, leaving the checkbox alone on row 1. A consumer's own
  `grid-row` rules still win where they are more specific. **One title cell
  per row:** two `.mhmui-table__primary` cells in a row would now share the
  first row and overlap.
- Served by the loader, so both show without a rebuild.

### Wide tables and the stacked detail aside (0.27.3)

- **`DataTable` scroll box:** `.mhmui-table-wrap` is now `position: relative`,
  so what is absolutely positioned inside it -- WP core's
  `.screen-reader-text` in a header cell -- stays inside the scroll box. A
  table wider than the screen used to scroll the whole page sideways (45px
  measured at 360px). Two consequences for a consumer: an absolutely
  positioned element of its own in a cell (a dropdown with no positioned
  ancestor) now stays inside the box too -- clipped or scrolled with it, not
  drawn over the page -- so give it a portal or a positioned anchor; and the
  box now paints in the positioned layer, above an unlayered sticky or
  relative element that comes before it.
- **`DetailLayout`:** at 782px and below the aside no longer pins
  (`position: static`, no viewport cap). Stacked, an aside placed before
  main stayed sticky and covered what scrolled under it. Above 782px the
  aside still pins and now has `z-index: 1`, so a table under it does not
  paint over it. A consumer's local `position: static` patch can go.
- **Known limit:** above 782px the columns also stack when the container is
  narrower than about 800px (a folded menu, an AdminShell sidebar); the
  aside still pins there. `DetailLayout` itself always draws the aside after
  main, so this only bites hand-written markup that puts the aside first.
- Served by the loader, so both show without a rebuild.

### PageHeader twin edges and number head cells (0.27.2)

- **`PageHeader` (JSX):** a `null` badge tone is neutral (it printed
  `mhmui-status--null`); a boolean badge text draws no badge (it drew an
  empty pill); `actions` that are a number, a blank string or an empty list
  draw no wrapper -- `actions={ 0 }` printed a bare `0`.
- **`mhmuicore_page_header_html()` (PHP):** a whole-number float `level` is
  that level (`1.0` drew an h2); a `back.label` or `actions` of only
  no-break or other Unicode spaces counts as blank, as JS's `trim()` sees it
  (a no-break-space label drew a nameless link).
- **`DataTable`:** a plain head cell with `className="mhmui-table__num"` now
  sits at the end, over its figures. A sortable one still aligns through
  `SortHeader align="end"`. Served by the loader.

### Table header height and typed props (0.27.1)

- **Default change:** a plain `th` in a `DataTable` head is now as tall as a
  sortable one (40px, 44px at 782px and below). A table with no `SortHeader`
  used to draw a header as short as its text (16px measured).
  Served by the loader, so it shows without a rebuild. A `layout="stack"`
  table changes only while it is drawn wide; once stacked its header height
  is released as before.
- `src-react/components.json` types `StatsGrid.cards`, `Tabs.items` and
  `DetailList.items` as their item shapes instead of `array`, and
  `StatCard.delta.direction` as `up|down|flat`. Nothing renders differently.
