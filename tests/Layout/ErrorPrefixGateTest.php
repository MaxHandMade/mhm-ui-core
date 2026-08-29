<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use PHPUnit\Framework\TestCase;

/**
 * G1 -- the prefix gate is exercised here, not just documented.
 *
 * A gate nobody runs after the day it was written stops being a gate. This
 * keeps bin/check-error-prefix.php honest inside the normal `composer test`
 * run, the same way ShippedSurfaceTest keeps the export-ignore rules honest by
 * shelling out and reading the real result instead of re-implementing the
 * check in PHP.
 */
final class ErrorPrefixGateTest extends TestCase {

	public function test_the_prefix_gate_passes_clean(): void {
		$root = dirname( __DIR__, 2 );

		exec( 'php ' . escapeshellarg( $root . '/bin/check-error-prefix.php' ) . ' 2>&1', $out, $code );

		self::assertSame( 0, $code, "check-error-prefix.php failed:\n" . implode( "\n", $out ) );
		self::assertContains( 'SUMMARY: 0', $out );
	}
}
