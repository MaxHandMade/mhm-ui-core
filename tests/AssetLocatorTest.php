<?php
/**
 * Locks the asset locators defined by bootstrap.php.
 *
 * These exist because VersionSelector resolves PHP class loading but NOT asset
 * URLs. The invariant under test: an asset URL must come from the SAME copy of
 * ui-core that actually booted — never from whichever plugin happened to load
 * its register.php first.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing Procedural bootstrap functions; covered behaviourally.
 */
final class AssetLocatorTest extends TestCase {

	/**
	 * Boot the package's bootstrap.php exactly once for the whole class.
	 *
	 * bootstrap.php is guarded by defined( MHMUICORE_VERSION ) and returns
	 * early on a second include, so it can only be loaded once per process.
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../bootstrap.php';
	}

	/**
	 * The three locators must exist after boot. If a future refactor moves them
	 * into register.php they would still exist here — so the NEXT test is the
	 * one that actually pins the location.
	 */
	public function test_locators_are_defined_after_boot(): void {
		$this->assertTrue( function_exists( 'mhmuicore_version' ) );
		$this->assertTrue( function_exists( 'mhmuicore_asset_path' ) );
		$this->assertTrue( function_exists( 'mhmuicore_asset_url' ) );
	}

	/**
	 * 🔴 THE POINT OF THIS FILE — and it is a STATIC check on purpose.
	 *
	 * The locators must be declared in bootstrap.php (highest version wins),
	 * never in register.php (first loader wins).
	 *
	 * An earlier version of this test used ReflectionFunction::getFileName().
	 * It was mutation-tested by moving mhmuicore_version() into register.php —
	 * and it stayed GREEN. Reason: whichever file loads first declares the
	 * function, the other is skipped by its own function_exists() guard, so
	 * reflection reported test ORDER, not source location. A probe that passes
	 * for the wrong reason is worse than no probe.
	 *
	 * Scanning the source with the tokenizer is order-independent and cannot be
	 * fooled by a mention inside a comment or string.
	 *
	 * @dataProvider locator_provider
	 *
	 * @param string $function Locator function name.
	 */
	public function test_locators_are_declared_only_in_the_version_selected_file( string $function ): void {
		$this->assertTrue(
			self::declares_function( __DIR__ . '/../bootstrap.php', $function ),
			sprintf( '%s must be declared in bootstrap.php (highest version wins).', $function )
		);
		$this->assertFalse(
			self::declares_function( __DIR__ . '/../register.php', $function ),
			sprintf(
				'%s must NOT be declared in register.php: that file is guarded by '
				. 'function_exists(), so the FIRST plugin to load wins — possibly an '
				. 'older copy with no assets/ directory at all.',
				$function
			)
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function locator_provider(): array {
		return array(
			'version'    => array( 'mhmuicore_version' ),
			'asset_path' => array( 'mhmuicore_asset_path' ),
			'asset_url'  => array( 'mhmuicore_asset_url' ),
		);
	}

	/**
	 * True when $file contains a real declaration of function $name.
	 *
	 * Uses the tokenizer rather than a regex so that a mention in a docblock,
	 * a comment, or a string literal does not count as a declaration.
	 *
	 * @param string $file Absolute path to a PHP file.
	 * @param string $name Function name to look for.
	 * @return bool
	 */
	private static function declares_function( string $file, string $name ): bool {
		$tokens = token_get_all( (string) file_get_contents( $file ) );
		$count  = count( $tokens );

		for ( $index = 0; $index < $count; $index++ ) {
			if ( ! is_array( $tokens[ $index ] ) || T_FUNCTION !== $tokens[ $index ][0] ) {
				continue;
			}
			// Skip whitespace between `function` and the identifier.
			$next = $index + 1;
			while ( $next < $count && is_array( $tokens[ $next ] ) && T_WHITESPACE === $tokens[ $next ][0] ) {
				$next++;
			}
			if ( $next < $count && is_array( $tokens[ $next ] )
				&& T_STRING === $tokens[ $next ][0] && $name === $tokens[ $next ][1] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The missing half: the helper must reach a file that EXISTS.
	 *
	 * Every other assertion in this class checks string construction, and they
	 * all passed while assets/ held nothing but a README -- so the documented
	 * call in bootstrap.php's own docblock,
	 * mhmuicore_asset_url( 'react/admin.css' ), resolved to a 404 for the first
	 * consumer who copied it. A path assertion that never touches the disk
	 * measures concatenation, not reachability.
	 *
	 * This is deliberately not a fixture: it names the file the package's
	 * documentation tells consumers to load, so deleting or relocating that
	 * stylesheet without updating the docs turns this red.
	 */
	public function test_the_documented_asset_actually_exists_on_disk(): void {
		$path = mhmuicore_asset_path( 'react/admin.css' );

		$this->assertFileExists(
			$path,
			"bootstrap.php documents mhmuicore_asset_url( 'react/admin.css' ) as the way to "
			. 'enqueue this package stylesheet. If it is not on disk, every consumer that '
			. 'follows the documented example ships a 404.'
		);
	}

	/**
	 * The path locator anchors on the booting copy's own directory.
	 */
	public function test_asset_path_is_rooted_in_the_booted_copy(): void {
		$this->assertSame(
			MHMUICORE_DIR . '/assets/react/admin.css',
			mhmuicore_asset_path( 'react/admin.css' )
		);
	}

	/**
	 * Leading separators in caller input must not produce a doubled slash —
	 * both POSIX and Windows flavours, since callers may build paths either way.
	 *
	 * @dataProvider leading_separator_provider
	 *
	 * @param string $input Caller-supplied relative path.
	 */
	public function test_leading_separators_are_normalised( string $input ): void {
		$this->assertSame(
			MHMUICORE_DIR . '/assets/react/admin.css',
			mhmuicore_asset_path( $input )
		);
		$this->assertStringNotContainsString( '//assets', mhmuicore_asset_url( $input ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function leading_separator_provider(): array {
		return array(
			'bare'           => array( 'react/admin.css' ),
			'leading slash'  => array( '/react/admin.css' ),
			'windows slash'  => array( '\\react/admin.css' ),
		);
	}

	/**
	 * 🔴 The URL must be anchored on the BOOTED copy's own bootstrap.php.
	 *
	 * Asserting the returned string is not enough: in this repo MHMUICORE_DIR is
	 * the package root, not a vendored path, so any string assertion would pass
	 * for the wrong reason. What actually matters is the argument we hand to
	 * plugins_url() — core derives the plugin folder from it, so passing another
	 * plugin's file (or a bare relative path) is exactly the bug this prevents.
	 */
	public function test_asset_url_is_anchored_on_the_booted_copys_own_file(): void {
		$GLOBALS['mhmuicore_test_plugins_url_calls'] = array();

		$url = mhmuicore_asset_url( 'react/admin.css' );

		$calls = $GLOBALS['mhmuicore_test_plugins_url_calls'];
		$this->assertCount( 1, $calls, 'asset_url must delegate to plugins_url exactly once.' );
		$this->assertSame(
			MHMUICORE_DIR . '/bootstrap.php',
			$calls[0]['plugin'],
			'The URL must be anchored on the booted copy, not on a consumer plugin file.'
		);
		$this->assertSame( 'assets/react/admin.css', $calls[0]['path'] );
		$this->assertStringEndsWith( '/assets/react/admin.css', $url );
	}

	/**
	 * Version must be reported by the booting copy, not hardcoded by a consumer.
	 */
	public function test_version_matches_the_booted_constant(): void {
		$this->assertSame( MHMUICORE_VERSION, mhmuicore_version() );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', mhmuicore_version() );
	}
}
