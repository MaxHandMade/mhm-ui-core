<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The shipped surface is a contract, not an accident.
 *
 * This package installs into consumers' vendor/mhm/ui-core. What `git archive`
 * produces is an upper bound on what ships, not the shipped set itself.
 * Measured 2026-09-05 against v0.8.1: `git archive HEAD` yields 53 files and
 * the consumer's vendor/mhm/ui-core tree holds exactly those 53 -- export-ignore
 * survives a Composer VCS install, so tests/, bin/, docker/ and .github/ never
 * reach a consumer at all. The consumer-side build then filters README.md,
 * assets/README.md and package.json out of the ZIP. A free core targeting
 * WordPress.org keeps more than that out, and README.md lists it by name: this
 * package ships a purity scanner whose vocabulary is the very words a reviewer
 * greps for, and a Pro-facing stylesheet.
 *
 * Keeping development tooling out of the archive is still the right guarantee --
 * it is the only half this package controls. The consumer-side half is measured
 * against the staged ZIP tree in the consumer's own plan.
 *
 * BLIND SPOT, MEASURED 2026-09-03
 *
 * `git archive HEAD` reads HEAD's .gitattributes, NOT the working tree's. An
 * uncommitted export-ignore edit is therefore invisible here: adding
 * "/.gitignore export-ignore" left this test green at 29 while the staged tree
 * already produced 28 (`git add .gitattributes && git archive $(git write-tree)`).
 * A local green before the commit proves nothing about a surface change, so
 * test_the_surface_has_the_expected_shape() refuses to report at all while
 * .gitattributes is dirty. Failing beats skipping: a skip reads as a pass.
 */
final class ShippedSurfaceTest extends TestCase {

	/** @return list<string> */
	private function shipped_files(): array {
		$root = dirname( __DIR__ );
		exec( 'git -C ' . escapeshellarg( $root ) . ' archive HEAD | tar -t', $out, $code );
		self::assertSame( 0, $code, 'git archive failed; the surface could not be measured' );

		return array_values( array_filter( $out, static fn( $p ) => ! str_ends_with( $p, '/' ) ) );
	}

	/**
	 * Whether .gitattributes carries uncommitted edits.
	 *
	 * Any porcelain output for the path means the file differs from HEAD, which
	 * is precisely when this test's measurement stops describing the edit.
	 */
	private function attributes_are_dirty(): bool {
		$root = dirname( __DIR__ );
		exec(
			'git -C ' . escapeshellarg( $root ) . ' status --porcelain -- .gitattributes',
			$out,
			$code
		);

		return 0 === $code && array() !== array_filter( $out, static fn( $l ) => '' !== trim( $l ) );
	}

	public function test_the_gate_scripts_do_not_ship(): void {
		foreach ( $this->shipped_files() as $path ) {
			self::assertStringStartsNotWith( 'bin/', $path, "bin/ must not ship: {$path}" );
			self::assertStringStartsNotWith( '.stylelintrc', $path, "stylelint config must not ship: {$path}" );
		}
	}

	public function test_the_surface_has_the_expected_shape(): void {
		self::assertFalse(
			$this->attributes_are_dirty(),
			'.gitattributes has uncommitted changes: git archive HEAD cannot see them, '
				. 'so this count would report the OLD surface as if it were current. '
				. 'Commit the attributes change, then re-run.'
		);

		$files = $this->shipped_files();

		$p1 = array_filter( $files, static fn( $p ) => (bool) preg_match( '/\.(css|js|jsx|php)$/', $p ) );
		$p2 = array_filter( $files, static fn( $p ) => str_ends_with( $p, '.css' ) );

		self::assertCount( 53, $files, 'shipped file count changed' );
		self::assertCount( 46, $p1, 'P1 file set changed' );
		self::assertCount( 2, $p2, 'P2 file set changed' );
	}
}
