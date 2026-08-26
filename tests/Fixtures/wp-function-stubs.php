<?php
/**
 * Minimal WordPress function stubs for tests that exercise register.php
 * outside of a real WordPress runtime.
 *
 * Deliberately NOT namespaced: register.php (like real WordPress plugin
 * code) calls add_action() unqualified from the global namespace, so the
 * stub must live there too, or the call in register.php would never
 * resolve to it.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Minimal add_action stub — register.php calls it at include time.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 */
	function add_action( string $hook, $callback, int $priority = 10 ): void {
		unset( $hook, $callback, $priority );
	}
}

if ( ! defined( 'MHMUICORE_TEST_PLUGIN_URL' ) ) {
	define( 'MHMUICORE_TEST_PLUGIN_URL', 'https://example.test/wp-content/plugins' );
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * Stub mirroring the real plugins_url() shape closely enough to test ours.
	 *
	 * Real WP does: $folder = dirname( plugin_basename( $plugin ) ), then
	 * WP_PLUGIN_URL . '/' . $folder . '/' . $path. We reproduce that by taking
	 * everything after the last "/plugins/" segment. Deliberately NOT a
	 * copy-paste of core: the test asserts OUR argument shape (that we pass the
	 * package's own bootstrap.php), not core's internals.
	 *
	 * @param string $path   Path relative to the plugin folder.
	 * @param string $plugin Absolute path to a file inside the plugin.
	 * @return string URL.
	 */
	function plugins_url( string $path = '', string $plugin = '' ): string {
		if ( ! isset( $GLOBALS['mhmuicore_test_plugins_url_calls'] ) ) {
			$GLOBALS['mhmuicore_test_plugins_url_calls'] = array();
		}
		$GLOBALS['mhmuicore_test_plugins_url_calls'][] = array(
			'path'   => $path,
			'plugin' => $plugin,
		);

		$normalized = str_replace( '\\', '/', $plugin );
		$marker     = '/plugins/';
		$position   = strrpos( $normalized, $marker );
		$folder     = false === $position
			? ''
			: dirname( substr( $normalized, $position + strlen( $marker ) ) );

		$url = MHMUICORE_TEST_PLUGIN_URL;
		if ( '' !== $folder && '.' !== $folder ) {
			$url .= '/' . ltrim( $folder, '/' );
		}
		if ( '' !== $path ) {
			$url .= '/' . ltrim( $path, '/' );
		}

		return $url;
	}
}
