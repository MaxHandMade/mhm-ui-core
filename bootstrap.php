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

define( 'MHMUICORE_VERSION', '0.3.2' );
define( 'MHMUICORE_DIR', __DIR__ );

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
