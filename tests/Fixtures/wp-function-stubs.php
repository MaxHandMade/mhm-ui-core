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
 * @package MHM\UiCore
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
