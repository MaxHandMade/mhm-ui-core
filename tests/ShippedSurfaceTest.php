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

		// 59/51/3 — src/Kit/StatCard.php and src/Kit/StatsGrid.php (the PHP kit
		// renderers, gate 6's PHP half) shipped in an earlier commit on this
		// branch (a794305) without this pin being moved; caught while running
		// `composer test` ahead of the gate-6 commit (Task 5). src-react/kit-
		// classes.json (gate 6's committed PHP/JSX class snapshot) does NOT
		// move this count: nothing at runtime reads it, only bin/dump-kit-
		// classes.php (writer) and the two gate tests (readers), so it is
		// export-ignored like the rest of that tooling. src/Kit/Icons.php
		// (the icon vocabulary's PHP half, Task 1) moved the pin from 56/48/3
		// to 57/49/3. src-react/icons.js (the icon vocabulary's JSX twin,
		// Task 2) moved it from 57/49/3 to 58/50/3, caught the same way.
		// src/Kit/IconConceptScanner.php (the icon-concept convergence gate's
		// engine, Task 4) moved it from 58/50/3 to 59/51/3 -- it ships because
		// a consumer's own CI must be able to `require` it directly (see its
		// class docblock); bin/check-icon-concepts.php (the CLI wrapper) and
		// tests/Fixtures/icon-concepts/ do NOT move this count, both are under
		// export-ignored paths (/bin/, /tests/). This pin is a tripwire, not a
		// target: it moves only with a commit that deliberately changes what
		// ships, and the commit says which file.
		// 0.15.0 src-react/components/Tabs.jsx moved it from 59/51/3 to 60/52/3
		// (one P1 file); src/Kit/Tabs.php (its PHP twin) to 61/53/3;
		// PageHeader.jsx to 62/54/3; DetailList.jsx to 63/55/3;
		// DetailLayout.jsx to 64/56/3.
		self::assertCount( 64, $files, 'shipped file count changed' );
		self::assertCount( 56, $p1, 'P1 file set changed' );
		self::assertCount( 3, $p2, 'P2 file set changed' );
	}
}
