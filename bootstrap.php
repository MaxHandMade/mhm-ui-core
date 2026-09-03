<?php
/**
 * Bootstrap for the winning copy of ui-core.
 *
 * Loaded exactly once, by mhmuicore_boot(), from the highest registered version.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'MHMUICORE_VERSION' ) ) {
	return;
}

define( 'MHMUICORE_VERSION', '0.7.1' );
define( 'MHMUICORE_DIR', __DIR__ );

/*
 * ─── Class loading ───────────────────────────────────────────────────────────
 *
 * WHY THIS EXISTS AT ALL
 *
 * composer.json declares a PSR-4 map, and this file used to claim src/ was
 * "PSR-4 autoloaded out of each consumer's own vendor/ directory". Measured
 * 2026-08-30: FALSE. Neither consumer requires vendor/autoload.php; each
 * registers an autoloader for its OWN namespace only. Nothing could load a
 * MHMUiCore class in production -- VersionSelector included.
 *
 * WHY IT BINDS __DIR__ AND NOT A PSR-4 LOOKUP
 *
 * mhmuicore_boot() loads exactly one bootstrap.php: the highest registered
 * version. Binding that copy's own src/ makes the facade and the classes it
 * hands out the SAME copy by construction, so facade/engine version skew
 * cannot happen and needs no runtime check. A shared PSR-4 registration would
 * reintroduce "whichever autoloader answers first wins".
 *
 * WHY $prepend IS true, NOT JUST "no consumer loads Composer's autoloader"
 *
 * spl_autoload_register()'s third parameter defaults to false, which APPENDS
 * to the autoload queue. "Skew is impossible by construction" would otherwise
 * rest on a MEASUREMENT (no consumer happens to load vendor/autoload.php
 * today) rather than a mechanism -- a future consumer that does load Composer's
 * autoloader for some unrelated reason could have its map answer for
 * MHMUiCore\ first, resolving classes from a DIFFERENT copy than the one this
 * bootstrap bound. Prepending costs nothing here: the closure below returns
 * silently for every foreign namespace, so it is a no-op for any class this
 * copy does not own, checked first or last.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'MHMUiCore\\' ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( 'MHMUiCore\\' ) );
		$path     = MHMUICORE_DIR . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	},
	true,
	true
);

/*
 * ─── Asset locators ──────────────────────────────────────────────────────────
 *
 * WHY THESE LIVE HERE AND NOT IN register.php
 *
 * The two files are selected by DIFFERENT rules and confusing them ships a bug:
 *
 *   register.php  → guarded by function_exists(); the FIRST plugin to load wins.
 *   bootstrap.php → guarded by defined( MHMUICORE_VERSION ); the HIGHEST version wins.
 *
 * An asset helper defined in register.php would therefore come from whichever
 * plugin happened to load first — possibly an older copy that has no assets/
 * directory at all. Defined here, the helper always comes from the same copy
 * whose files it points at, because MHMUICORE_DIR is that copy's own __DIR__.
 *
 * This is the fix for a real gap: VersionSelector resolves PHP class loading,
 * it does NOT resolve asset URLs. Before this, a consumer wanting a shared
 * stylesheet had to hardcode another plugin's URL constant.
 *
 * Callers MUST guard with function_exists(): a site may still be running an
 * older ui-core (0.2.x) as the winner, in which case these do not exist.
 *
 *     if ( function_exists( 'mhmuicore_asset_url' ) ) {
 *         wp_enqueue_style( 'x', mhmuicore_asset_url( 'react/admin.css' ), array(), mhmuicore_version() );
 *     }
 */

if ( ! function_exists( 'mhmuicore_version' ) ) {
	/**
	 * Version of the ui-core copy that actually booted.
	 *
	 * Use as the asset version argument so cache busting follows the winner,
	 * not the consuming plugin's own version.
	 *
	 * @return string Semantic version string.
	 */
	function mhmuicore_version(): string {
		return MHMUICORE_VERSION;
	}
}

if ( ! function_exists( 'mhmuicore_asset_path' ) ) {
	/**
	 * Absolute filesystem path to an asset inside the winning ui-core copy.
	 *
	 * @param string $relative Path relative to the package's assets/ directory.
	 * @return string Absolute path. Not checked for existence — callers that
	 *                care should file_exists() it.
	 */
	function mhmuicore_asset_path( string $relative ): string {
		return MHMUICORE_DIR . '/assets/' . ltrim( $relative, '/\\' );
	}
}

if ( ! function_exists( 'mhmuicore_asset_url' ) ) {
	/**
	 * Public URL of an asset inside the winning ui-core copy.
	 *
	 * Built with plugins_url() deliberately: that function resolves the plugin
	 * folder via plugin_basename() and switches base URL for must-use plugins,
	 * so a package vendored under wp-content/plugins/<plugin>/vendor/mhm/ui-core/
	 * and one under mu-plugins both resolve correctly.
	 *
	 * @param string $relative Path relative to the package's assets/ directory.
	 * @return string Absolute URL.
	 */
	function mhmuicore_asset_url( string $relative ): string {
		return plugins_url(
			'assets/' . ltrim( $relative, '/\\' ),
			MHMUICORE_DIR . '/bootstrap.php'
		);
	}
}

/*
 * ─── React admin page loader ─────────────────────────────────────────────────
 *
 * WHY THIS IS SHARED INFRASTRUCTURE AND NOT ONE PLUGIN'S HELPER
 *
 * Every MHM React admin screen needs the same four things in the same order:
 * the wp-api-fetch REST nonce middleware once per request, the wp-components
 * stylesheet (WordPress ships its components unstyled), the bundle enqueued
 * with the dependency list and version that @wordpress/scripts generated, and
 * its JSON translation catalogues wired up.
 *
 * This used to live in mhm-rentiva's AssetManager while a second plugin called
 * it across the plugin boundary for five of its own screens, passing its own
 * path, URL and text domain as arguments. Three of the five parameters existed
 * solely for that second caller -- shared infrastructure wearing one product's
 * namespace. Deactivating the first plugin took the second one's React screens
 * down with it.
 *
 * WHY IT IS A FUNCTION HERE AND NOT A CLASS IN src/
 *
 * src/ is loaded by the autoloader this same file registers, bound to this
 * copy's own __DIR__. Functions and classes therefore always come from the
 * winning copy together. (Before 0.6.0 this comment claimed PSR-4 loading out
 * of the consumer's vendor/; that was never true here -- no consumer loads
 * vendor/autoload.php -- and a whole version-skew gate was designed against
 * the phantom risk it described.)
 *
 * Callers MUST guard with function_exists(): a site may still be running an
 * older ui-core (0.3.x or earlier) as the winner, where this does not exist.
 */

if ( ! function_exists( 'mhmuicore_enqueue_react_page' ) ) {
	/**
	 * Enqueue a React admin page bundle with its REST nonce middleware.
	 *
	 * Takes an argument array rather than a positional list on purpose: this
	 * package may only ever ADD to its API -- a removed or renamed parameter
	 * breaks whichever consumer is still in the field on an older release -- and
	 * a new array key costs nothing, while a seventh positional parameter would
	 * pin the order forever.
	 *
	 * @param array<string, mixed> $args {
	 *     Loader arguments. Six are required; a caller supplying none of the
	 *     optional ones gets the layout @wordpress/scripts produces by default.
	 *
	 *     @type string   $page          Bundle basename under the build directory, e.g. 'dashboard'.
	 *     @type string   $base_dir      Absolute filesystem path of the plugin that owns the
	 *                                   bundle, with a trailing slash.
	 *     @type string   $base_url      Public URL counterpart of $base_dir, trailing slash.
	 *     @type string   $handle_prefix Script handle prefix; $page is appended verbatim, so the
	 *                                   prefix carries its own trailing separator.
	 *     @type string   $version       Fallback asset version, used ONLY when the generated
	 *                                   .asset.php is absent.
	 *     @type string   $text_domain   Text domain the bundle's __() calls use. Travels with
	 *                                   $base_dir: a domain that does not match the directory
	 *                                   asks WordPress for catalogues that cannot exist.
	 *     @type string[] $deps          Optional extra script dependencies, merged AFTER the
	 *                                   generated ones. Default empty array.
	 *     @type string   $languages_dir Optional absolute path holding the JSON catalogues.
	 *                                   Default $base_dir . 'languages/'.
	 *     @type string   $build_dir     Optional build directory relative to $base_dir.
	 *                                   Default 'build/admin/'.
	 * }
	 *
	 * @throws InvalidArgumentException When a required key is missing, not a string, or empty.
	 *                                  Empty is rejected as well as missing because that is the
	 *                                  shape an undefined plugin constant collapses to, and a
	 *                                  half-built URL enqueues a 404 rather than failing.
	 */
	function mhmuicore_enqueue_react_page( array $args ): void {
		$required = array( 'page', 'base_dir', 'base_url', 'handle_prefix', 'version', 'text_domain' );
		$values   = array();

		foreach ( $required as $key ) {
			if ( ! isset( $args[ $key ] ) || ! is_string( $args[ $key ] ) || '' === $args[ $key ] ) {
				/*
				 * esc_html() on an exception message is not ceremony: an uncaught
				 * exception's message is printed, so WordPress treats a throw as
				 * an output site (WordPress.Security.EscapeOutput.ExceptionNotEscaped).
				 *
				 * This package ships INSIDE consuming plugins, and their gates lint
				 * what they find in vendor/ -- with rulesets wider than this
				 * package's own. This exact line passed here and failed in a
				 * consumer's CI. phpcs.xml now runs WordPress-Extra so the package
				 * catches this class itself instead of exporting it.
				 */
				throw new InvalidArgumentException(
					esc_html(
						sprintf(
							'mhmuicore_enqueue_react_page(): "%s" is required and must be a non-empty string.',
							$key
						)
					)
				);
			}
			$values[ $key ] = $args[ $key ];
		}

		$extra_deps = array();
		if ( isset( $args['deps'] ) && is_array( $args['deps'] ) ) {
			// Empty strings survive is_string() and would be enqueued as a
			// dependency on the handle "", which never resolves. The caller is
			// another plugin, so this is filtered here rather than assumed.
			$extra_deps = array_values(
				array_filter(
					$args['deps'],
					static fn( $dep ): bool => is_string( $dep ) && '' !== $dep
				)
			);
		}

		$build_dir = ( isset( $args['build_dir'] ) && is_string( $args['build_dir'] ) && '' !== $args['build_dir'] )
			? $args['build_dir']
			: 'build/admin/';

		$languages_dir = ( isset( $args['languages_dir'] ) && is_string( $args['languages_dir'] ) && '' !== $args['languages_dir'] )
			? $args['languages_dir']
			: $values['base_dir'] . 'languages/';

		/*
		 * Once per request, not once per page: api-fetch keeps a middleware
		 * stack, so registering the nonce twice runs it twice on every request.
		 *
		 * A global rather than a function static so tests can reset it. A static
		 * would make "installed once" impossible to measure -- the first test to
		 * run would consume it and every later one would agree for the wrong
		 * reason.
		 */
		if ( empty( $GLOBALS['mhmuicore_react_nonce_added'] ) ) {
			wp_add_inline_script(
				'wp-api-fetch',
				sprintf(
					'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( "%s" ) );',
					esc_js( wp_create_nonce( 'wp_rest' ) )
				),
				'after'
			);
			$GLOBALS['mhmuicore_react_nonce_added'] = true;
		}

		wp_enqueue_style( 'wp-components' );

		/*
		 * The generated manifest is the authority on both dependencies and
		 * version: the bundler computed them from what the bundle actually
		 * imports and from its own content hash. The caller's version is a
		 * fallback for the un-built case only -- never an override, or a deploy
		 * would ship new bytes under an old cache key.
		 */
		$dependencies = array();
		$version      = $values['version'];
		$asset_file   = $values['base_dir'] . $build_dir . $values['page'] . '.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = include $asset_file;

			if ( is_array( $asset ) ) {
				if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
					$dependencies = array_values(
						array_filter(
							$asset['dependencies'],
							static fn( $dep ): bool => is_string( $dep ) && '' !== $dep
						)
					);
				}
				if ( isset( $asset['version'] ) && is_string( $asset['version'] ) && '' !== $asset['version'] ) {
					$version = $asset['version'];
				}
			}
		}

		$handle = $values['handle_prefix'] . $values['page'];

		wp_enqueue_script(
			$handle,
			$values['base_url'] . $build_dir . $values['page'] . '.js',
			array_merge( $dependencies, $extra_deps ),
			$version,
			true
		);

		wp_set_script_translations( $handle, $values['text_domain'], $languages_dir );
	}
}

/*
 * ─── Layout engine facade ────────────────────────────────────────────────────
 *
 * WHY THIS IS HERE AND NOT IN register.php
 *
 * Same rule as the asset locators and the React page loader above, and the
 * stakes are higher here: LayoutEngine wires LayoutContract into
 * BlueprintValidator and CompositionBuilder, both loaded through the
 * autoloader THIS file registers, bound to THIS copy's own __DIR__. Defining
 * the facade in register.php -- first-plugin-to-load-wins -- could hand a
 * consumer an engine built from an OLDER copy than the classes it now hands
 * out elsewhere, a version-skew bug this file's own binding design (see the
 * "Class loading" section above) exists specifically to make impossible.
 * Defined here, the facade and the engine it returns are always the same
 * winning copy by construction.
 *
 * Callers MUST guard with function_exists(): a site may still be running an
 * older ui-core as the winner, where this does not exist.
 *
 *     if ( function_exists( 'mhmuicore_layout_engine' ) ) {
 *         $engine = mhmuicore_layout_engine( array( ... ) );
 *     }
 */

if ( ! function_exists( 'mhmuicore_layout_engine' ) ) {
	/**
	 * Builds a Layout engine from a consumer contract.
	 *
	 * @param array<string,mixed> $config Contract configuration: error_prefix,
	 *                                    markup_prefix, adapters.
	 * @return \MHMUiCore\Layout\LayoutEngine
	 *
	 * @throws \InvalidArgumentException When the contract is malformed. This
	 *                                   propagates out of LayoutContract's own
	 *                                   constructor and is deliberately NOT
	 *                                   caught and converted to a WP_Error: a
	 *                                   malformed contract is a programmer
	 *                                   error, not a domain error, and no
	 *                                   runtime path can recover from it.
	 */
	function mhmuicore_layout_engine( array $config ): \MHMUiCore\Layout\LayoutEngine {
		return new \MHMUiCore\Layout\LayoutEngine( new \MHMUiCore\Layout\LayoutContract( $config ) );
	}
}
