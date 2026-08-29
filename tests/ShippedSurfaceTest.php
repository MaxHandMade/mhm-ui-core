<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The shipped surface is a contract, not an accident.
 *
 * This package installs into consumers' vendor/mhm/ui-core and their .distignore
 * forwards the whole directory into a WordPress.org ZIP. Anything that lands in
 * `git archive` therefore ships to end users. Development tooling must not.
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

		self::assertCount( 23, $files, 'shipped file count changed' );
		self::assertCount( 16, $p1, 'P1 file set changed' );
		self::assertCount( 1, $p2, 'P2 file set changed' );
	}
}
