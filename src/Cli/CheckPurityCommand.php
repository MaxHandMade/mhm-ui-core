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
 * Report outbound HTTP, licence code and artificial limits in a free core.
 *
 * Reads the tree's PHP and JavaScript, including JavaScript a PHP file hands to
 * the browser. Ends on one of three answers: clean, findings, or places that
 * could not be decided by reading -- a target built at run time, a generated
 * bundle, a file it could not open. The third is not a pass.
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

		$coverage = array();
		foreach ( $scanner->self_test_coverage() as $language => $count ) {
			$coverage[] = $language . ' ' . $count;
		}
		WP_CLI::log( 'check:purity: self-test passed (' . implode( ' · ', $coverage ) . ' fixtures).' );

		/*
		 * A directory can exist and still hold nothing this gate reads. Reporting
		 * that as clean is the loudest way a gate can lie, so it is an error --
		 * the same answer the namespace gates give as EMPTY-SET.
		 */
		$scannable = $scanner->scannable_files( $dir );
		if ( array() === $scannable ) {
			WP_CLI::error( 'check:purity: no PHP or JavaScript file found under ' . $dir . ' -- refusing to report a clean scan of nothing.' );
			return;
		}

		$violations = array();
		$undecided  = array();
		foreach ( $scanner->scan( $dir ) as $hit ) {
			WP_CLI::log( sprintf( '%s:%d  %s  (%s)', $hit['file'], $hit['line'], $hit['class'], $hit['name'] ) );

			if ( PurityScanner::CLASS_UNMEASURABLE === $hit['class'] ) {
				$undecided[] = $hit;
				continue;
			}

			$violations[] = $hit;
		}

		/*
		 * The two verdicts are kept apart because they ask different things of the
		 * reader. A violation is a thing to remove; an undecided place is a thing
		 * to look at. Calling the second a violation trains people to ignore both.
		 */
		if ( array() !== $violations ) {
			WP_CLI::error(
				sprintf(
					'check:purity: %d finding(s), %d undecided. A free core ships none of these.',
					count( $violations ),
					count( $undecided )
				)
			);
			return;
		}

		if ( array() !== $undecided ) {
			WP_CLI::error(
				sprintf(
					'check:purity: no forbidden thing found, but %d place(s) could not be decided by reading. '
						. 'A purity claim cannot rest on them -- read each one, or point the gate at the sources '
						. 'a generated file was built from.',
					count( $undecided )
				)
			);
			return;
		}

		WP_CLI::success(
			sprintf(
				'check:purity: %d file(s) read and decided -- no outbound call, no licence vocabulary, '
					. 'no artificial-limit vocabulary, and nothing left undecided.',
				count( $scannable )
			)
		);
	}
}
