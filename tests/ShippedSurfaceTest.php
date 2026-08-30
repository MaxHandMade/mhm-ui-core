<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The shipped surface is a contract, not an accident.
 *
 * This package installs into consumers' vendor/mhm/ui-core. What `git archive`
 * produces is an upper bound on what ships, not the shipped set itself.
 * Measured 2026-08-30 against v0.4.1: the consumer's tree holds 16 files, of
 * which 12 appear in the canonical `build-release.py --list-shipped` output.
 * The consumer-side build filters out .gitignore, README.md, assets/README.md,
 * and package.json.
 *
 * Keeping development tooling out of the archive is still the right guarantee —
 * it is the only half this package controls. The consumer-side half is measured
 * against the staged ZIP tree in the consumer's own plan.
 */
final class ShippedSurfaceTest extends TestCase {

	/** @return list<string> */
	private function shipped_files(): array {
		$root = dirname( __DIR__ );
		exec( 'git -C ' . escapeshellarg( $root ) . ' archive HEAD | tar -t', $out, $code );
		self::assertSame( 0, $code, 'git archive failed; the surface could not be measured' );

		return array_values( array_filter( $out, static fn( $p ) => ! str_ends_with( $p, '/' ) ) );
	}

	public function test_the_gate_scripts_do_not_ship(): void {
		foreach ( $this->shipped_files() as $path ) {
			self::assertStringStartsNotWith( 'bin/', $path, "bin/ must not ship: {$path}" );
			self::assertStringStartsNotWith( '.stylelintrc', $path, "stylelint config must not ship: {$path}" );
		}
	}

	public function test_the_surface_has_the_expected_shape(): void {
		$files = $this->shipped_files();

		$p1 = array_filter( $files, static fn( $p ) => (bool) preg_match( '/\.(css|js|jsx|php)$/', $p ) );
		$p2 = array_filter( $files, static fn( $p ) => str_ends_with( $p, '.css' ) );

		self::assertCount( 29, $files, 'shipped file count changed' );
		self::assertCount( 22, $p1, 'P1 file set changed' );
		self::assertCount( 1, $p2, 'P2 file set changed' );
	}
}
