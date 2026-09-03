<?php
/**
 * Minimal WP_CLI so the command classes resolve for PHPStan and the unit suite.
 * Records output instead of printing it. Guarded: a no-op under real WP-CLI.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_CLI', false ) ) {
	/**
	 * Stand-in for the WP-CLI facade.
	 */
	class WP_CLI {

		/**
		 * Everything logged, in order, as [level, message].
		 *
		 * @var list<array{0:string, 1:string}>
		 */
		public static $output = array();

		/**
		 * Register a command.
		 *
		 * @param string               $name     Command name.
		 * @param mixed                $callable Handler.
		 * @param array<string, mixed> $args     Options.
		 * @return bool
		 */
		public static function add_command( string $name, $callable, array $args = array() ): bool {
			self::$output[] = array( 'add_command', $name );
			return true;
		}

		/**
		 * Log.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function log( string $message ): void {
			self::$output[] = array( 'log', $message );
		}

		/**
		 * Success.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function success( string $message ): void {
			self::$output[] = array( 'success', $message );
		}

		/**
		 * Error. Real WP-CLI exits here; the stub records and returns so a test
		 * can assert on the message.
		 *
		 * @param string $message Message.
		 * @return void
		 */
		public static function error( string $message ): void {
			self::$output[] = array( 'error', $message );
		}
	}
}
