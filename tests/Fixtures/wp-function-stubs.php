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
		// Recorded when the recorder exists (it is declared later in this file,
		// so it does at every call time except register.php's include-time one).
		if ( function_exists( 'mhmuicore_test_record' ) ) {
			mhmuicore_test_record( 'add_action', array( $hook, $priority ) );
		}
		unset( $callback );
	}
}

if ( ! defined( 'MHMUICORE_TEST_PLUGIN_URL' ) ) {
	define( 'MHMUICORE_TEST_PLUGIN_URL', 'https://example.test/wp-content/plugins' );
}

/*
 * ─── Enqueue stubs ───────────────────────────────────────────────────────────
 *
 * mhmuicore_enqueue_react_page() exists to make a precise sequence of WordPress
 * calls with precise arguments; the arguments ARE the behaviour. These stubs
 * therefore record every call instead of returning a value, and the test
 * asserts on the recording.
 *
 * Recorded under one global so a test can reset the whole surface in setUp()
 * with a single assignment and cannot leave half of it dirty for the next test.
 */

if ( ! function_exists( 'mhmuicore_test_record' ) ) {
	/**
	 * Append one recorded call.
	 *
	 * @param string              $function Stubbed function name.
	 * @param array<string, mixed> $args    Arguments as received.
	 */
	function mhmuicore_test_record( string $function, array $args ): void {
		if ( ! isset( $GLOBALS['mhmuicore_test_wp_calls'] ) || ! is_array( $GLOBALS['mhmuicore_test_wp_calls'] ) ) {
			$GLOBALS['mhmuicore_test_wp_calls'] = array();
		}
		$GLOBALS['mhmuicore_test_wp_calls'][ $function ][] = $args;
	}

	/**
	 * Calls recorded for one stubbed function, in order.
	 *
	 * @param string $function Stubbed function name.
	 * @return array<int, array<string, mixed>>
	 */
	function mhmuicore_test_calls( string $function ): array {
		if ( ! isset( $GLOBALS['mhmuicore_test_wp_calls'][ $function ] ) ) {
			return array();
		}

		return $GLOBALS['mhmuicore_test_wp_calls'][ $function ];
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Recording stub.
	 *
	 * @param string   $handle Handle.
	 * @param string   $src    Source URL.
	 * @param string[] $deps   Dependencies.
	 * @param mixed    $ver    Version.
	 * @param string   $media  Media.
	 */
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ): void {
		mhmuicore_test_record(
			'wp_enqueue_style',
			compact( 'handle', 'src', 'deps', 'ver', 'media' )
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Recording stub.
	 *
	 * @param string   $handle Handle.
	 * @param string   $src    Source URL.
	 * @param string[] $deps   Dependencies.
	 * @param mixed    $ver    Version.
	 * @param mixed    $args   Footer flag or args array.
	 */
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = false, $args = false ): void {
		mhmuicore_test_record(
			'wp_enqueue_script',
			compact( 'handle', 'src', 'deps', 'ver', 'args' )
		);
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	/**
	 * Recording stub.
	 *
	 * @param string $handle   Handle.
	 * @param string $data     Script body.
	 * @param string $position before|after.
	 */
	function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): void {
		mhmuicore_test_record(
			'wp_add_inline_script',
			compact( 'handle', 'data', 'position' )
		);
	}
}

if ( ! function_exists( 'wp_set_script_translations' ) ) {
	/**
	 * Recording stub.
	 *
	 * @param string $handle Handle.
	 * @param string $domain Text domain.
	 * @param string $path   Catalogue directory.
	 */
	function wp_set_script_translations( string $handle, string $domain = 'default', string $path = '' ): void {
		mhmuicore_test_record(
			'wp_set_script_translations',
			compact( 'handle', 'domain', 'path' )
		);
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Deterministic stub — the value only has to travel intact.
	 *
	 * @param string $action Nonce action.
	 * @return string
	 */
	function wp_create_nonce( string $action = '-1' ): string {
		return 'nonce-for-' . $action;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	/**
	 * Marking stub.
	 *
	 * Deliberately NOT a faithful copy of core's escaping: the test asserts that
	 * our code routes the nonce THROUGH esc_js at all, which a pass-through
	 * identity function could not distinguish from skipping the call entirely.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function esc_js( string $text ): string {
		return 'esc_js(' . $text . ')';
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Marking stub, for the same reason as esc_js above.
	 *
	 * An uncaught exception's message is printed, so WordPress treats a throw as
	 * an output site. Marking rather than passing through lets a test prove the
	 * message went through escaping instead of merely containing the right words.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return 'esc_html(' . $text . ')';
	}
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

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error replica.
	 *
	 * Core's constructor keeps an empty message when a code is given
	 * ("the message parameter is used even if empty"), which is precisely the
	 * semantic the message-free engine depends on. Do not "improve" this by
	 * defaulting the message to the code.
	 */
	class WP_Error {

		/** @var string */
		private $code;

		/** @var string */
		private $message;

		/** @var mixed */
		private $data;

		/**
		 * @param string $code    Machine code.
		 * @param string $message Human text -- empty for engine-produced errors.
		 * @param mixed  $data    Structured payload. Core drops an empty payload
		 *                        rather than storing it (`if ( ! empty( $data ) )`),
		 *                        so get_error_data() returns null, not `array()`,
		 *                        for a code constructed with `array()` -- matched
		 *                        here so this stub cannot disagree with production
		 *                        about what NO_PAGES/INVALID_COMPONENTS return.
		 */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = empty( $data ) ? null : $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		/** @return mixed */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to test.
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Value to encode.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

/*
 * ─── Component factory + seam stubs (2026-09-03) ───────────────────────────
 * The four surfaces and the seam call these. Each records its call so a test
 * can assert what was registered, exactly like the enqueue stubs above.
 */

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Records the tag and keeps the callback so a test can invoke it.
	 *
	 * @param string   $tag      Tag.
	 * @param callable $callback Callback.
	 * @return void
	 */
	function add_shortcode( string $tag, callable $callback ): void {
		$GLOBALS['mhmuicore_test_shortcodes'][ $tag ] = $callback;
		mhmuicore_test_record( 'add_shortcode', array( $tag ) );
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	/**
	 * Records the block name and args so a test can invoke render_callback.
	 *
	 * @param string               $name Block name.
	 * @param array<string, mixed> $args Args.
	 * @return bool
	 */
	function register_block_type( string $name, array $args = array() ): bool {
		$GLOBALS['mhmuicore_test_blocks'][ $name ] = $args;
		mhmuicore_test_record( 'register_block_type', array( $name ) );
		return true;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Enough of core's sanitizer for the contract's string path.
	 *
	 * @param string $str Raw.
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return trim( (string) preg_replace( '/<[^>]*>/', '', $str ) );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Pass-through that records the hook name.
	 *
	 * @param string $hook_name Hook.
	 * @param mixed  $value     Value.
	 * @param mixed  ...$args   Extra args.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		mhmuicore_test_record( 'apply_filters', array( $hook_name ) );
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Records the hook name.
	 *
	 * @param string $hook_name Hook.
	 * @param mixed  ...$args   Args.
	 * @return void
	 */
	function do_action( string $hook_name, ...$args ): void {
		mhmuicore_test_record( 'do_action', array( $hook_name ) );
	}
}
