<?php
/**
 * `wp mhm-ui check:purity`.
 *
 * @package MHMUiCore\Cli
 */

declare(strict_types=1);

namespace MHMUiCore\Cli;

use MHMUiCore\Seam\PurityScanner;
use WP_CLI;

/**
 * Prove a free core carries no outbound HTTP, licence code or artificial limit.
 *
 * ## OPTIONS
 *
 * <dir>
 * : The free core's directory.
 */
final class CheckPurityCommand {

	/**
	 * Run.
	 *
	 * @param array<int, string> $args Positional arguments.
	 * @return void
	 */
	public function __invoke( array $args ): void {
		$dir = $args[0] ?? '';
		if ( '' === $dir || ! is_dir( $dir ) ) {
			WP_CLI::error( 'check:purity: directory not found -- refusing to report a clean scan of nothing.' );
			return;
		}

		$scanner  = new PurityScanner();
		$failures = $scanner->self_test();
		if ( array() !== $failures ) {
			WP_CLI::error( 'check:purity: the scanner failed its own self-test: ' . implode( '; ', $failures ) );
			return;
		}

		$violations = $scanner->scan( $dir );
		foreach ( $violations as $v ) {
			WP_CLI::log( sprintf( '%s:%d  %s  (%s)', $v['file'], $v['line'], $v['class'], $v['name'] ) );
		}

		if ( array() !== $violations ) {
			WP_CLI::error( sprintf( 'check:purity: %d finding(s). A free core ships none of these.', count( $violations ) ) );
			return;
		}

		WP_CLI::success( 'check:purity: clean -- no outbound HTTP, no licence code, no artificial limit.' );
	}
}
