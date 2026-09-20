<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Kit;

use PHPUnit\Framework\TestCase;

/**
 * bin/check-icon-concepts.php's actual consumer-facing contract: exit 1 on a
 * raw suffix, with NO --expect-raw involved.
 *
 * composer.json's own "check:icon-concepts" call always passes
 * --expect-raw=2 -- that flag is THIS PACKAGE's own convergence proof (task
 * 4's Adim 7 mutations), not what a downstream consumer's CI runs. A real
 * consumer wires this CLI in without --expect-raw, and its only red/green
 * signal is the ternary at the bottom of bin/check-icon-concepts.php:
 * `exit( array() === $result['raw'] ? 0 : 1 )`. Before this file existed, no
 * CI step and no PHPUnit test ever ran that line with $expect_raw === null --
 * flipping the ternary to `? 1 : 0` would have shipped unnoticed. This file
 * is what exercises the CLI process itself (via exec()), the same way
 * ShippedSurfaceTest shells out to `git archive` rather than re-implementing
 * git in PHP: the CLI's argv parsing and its exit() calls are the contract,
 * not just IconConceptScanner::scan()'s return array.
 */
final class IconConceptScannerCliTest extends TestCase {

	private const ANCHOR_ARGS = array(
		'--anchor=mhmuicore_stat_card_html',
		'--anchor=mhmuicore_stats_grid_html',
		'--anchor=ui-core/src-react/components/Stat',
		'--anchor=AssetManager::stats_grid_html',
	);

	/**
	 * Run the CLI against one path, with no --expect-raw, and capture its exit
	 * code and combined STDOUT+STDERR.
	 *
	 * @return array{code: int, output: array<int, string>}
	 */
	private function run_cli( string $path ): array {
		$root = dirname( __DIR__, 2 );
		$cmd  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/bin/check-icon-concepts.php' )
			. ' ' . implode( ' ', array_map( 'escapeshellarg', self::ANCHOR_ARGS ) )
			. ' ' . escapeshellarg( $path ) . ' 2>&1';

		exec( $cmd, $output, $code );

		return array(
			'code'   => $code,
			'output' => $output,
		);
	}

	public function test_a_raw_suffix_exits_1_with_no_expect_raw_flag(): void {
		$dir = dirname( __DIR__ ) . '/Fixtures/icon-concepts';
		$run = $this->run_cli( $dir );

		self::assertSame(
			1,
			$run['code'],
			'a real raw suffix must fail the CLI even when the caller never asked for --expect-raw'
		);
		self::assertNotEmpty(
			array_filter( $run['output'], static fn( $line ): bool => str_starts_with( $line, 'RAW' ) ),
			'STDERR must still name the raw suffix: ' . implode( "\n", $run['output'] )
		);
	}

	public function test_a_clean_input_exits_0_with_no_expect_raw_flag(): void {
		$file = dirname( __DIR__ ) . '/Fixtures/icon-concepts/kit-caller.php';
		$run  = $this->run_cli( $file );

		self::assertSame(
			0,
			$run['code'],
			'a file with only concept icons and no raw suffix must pass with no --expect-raw: '
				. implode( "\n", $run['output'] )
		);
	}
}
