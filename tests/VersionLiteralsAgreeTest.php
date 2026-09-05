<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every version literal in this repository says the same thing.
 *
 * MHMUICORE_VERSION is the one the loader arbitrates on, but it is not the only
 * place a version is written down, and the others drift silently. Measured
 * before this gate existed: the consumer-side register() literal had trailed
 * the package across three releases without any gate noticing, because nothing
 * compared them -- the failure mode is not a crash but an OLD copy winning the
 * arbitration on a customer's site.
 *
 * The README examples are held to the same standard on purpose. They are the
 * text a new consumer copies into its own main file, so a stale example does
 * not merely document the past: it MANUFACTURES the exact defect above, one
 * plugin at a time. That is why this asserts documentation, which a test
 * normally has no business doing.
 *
 * package.json carries its own copy because npm requires one; it ships in the
 * consumer's vendor tree (webpack reads it for "sideEffects"), so it is part of
 * the surface and gets pinned too.
 */
final class VersionLiteralsAgreeTest extends TestCase {

	private const ROOT = __DIR__ . '/..';

	/** The version the loader arbitrates on -- the single source of truth. */
	private function canonical_version(): string {
		$src = (string) file_get_contents( self::ROOT . '/bootstrap.php' );

		self::assertSame(
			1,
			preg_match( "/define\(\s*'MHMUICORE_VERSION',\s*'([^']+)'\s*\)/", $src, $m ),
			'MHMUICORE_VERSION could not be read from bootstrap.php'
		);

		return $m[1];
	}

	public function test_the_canonical_version_is_a_semver_triple(): void {
		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $this->canonical_version() );
	}

	public function test_package_json_agrees(): void {
		$json = json_decode( (string) file_get_contents( self::ROOT . '/package.json' ), true );

		self::assertIsArray( $json, 'package.json is not readable JSON' );
		self::assertSame(
			$this->canonical_version(),
			$json['version'] ?? null,
			'package.json version disagrees with MHMUICORE_VERSION'
		);
	}

	public function test_package_lock_root_agrees(): void {
		$json = json_decode( (string) file_get_contents( self::ROOT . '/package-lock.json' ), true );

		self::assertIsArray( $json, 'package-lock.json is not readable JSON' );

		/*
		 * Measured 2026-09-03: the lock's root version still said 0.3.2 while the
		 * package was at 0.7.0. `npm ci` does not fail on that -- it only checks the
		 * dependency tree -- so four releases of drift stayed invisible. Both root
		 * copies (top-level and packages[""]) are pinned.
		 */
		self::assertSame( $this->canonical_version(), $json['version'] ?? null, 'package-lock.json top-level version drifted' );
		self::assertSame( $this->canonical_version(), $json['packages']['']['version'] ?? null, 'package-lock.json packages[""] version drifted' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function readme_provider(): array {
		return array(
			'README.md'    => array( 'README.md' ),
			'README-tr.md' => array( 'README-tr.md' ),
		);
	}

	/**
	 * @dataProvider readme_provider
	 */
	public function test_every_register_example_in_the_docs_agrees( string $file ): void {
		$src = (string) file_get_contents( self::ROOT . '/' . $file );

		$found = preg_match_all( "/mhmuicore_register\(\s*'([^']+)'/", $src, $m );

		/*
		 * At least one, and every one of them current. The earlier shape demanded
		 * EXACTLY one, which made the gate a cap on documentation: adding a second
		 * example -- the free-core shipping section needs its own -- failed a test
		 * whose whole point is that the literals AGREE. Scarcity was never the
		 * property worth pinning.
		 */
		self::assertGreaterThanOrEqual(
			1,
			$found,
			"{$file} must show a new consumer how to register this package"
		);

		foreach ( $m[1] as $literal ) {
			self::assertSame(
				$this->canonical_version(),
				$literal,
				"{$file}'s register() example would make a new consumer write a stale literal"
			);
		}
	}
}
