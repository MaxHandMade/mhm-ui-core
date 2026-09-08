<?php
declare(strict_types=1);

namespace MHMUiCore\Tests\Gate;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EnqueueKitTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	protected function setUp(): void {
		$GLOBALS['mhmuicore_test_wp_calls'] = array();
	}

	public function test_each_surface_maps_to_a_package_constant_handle(): void {
		self::assertSame( 'mhmuicore-admin', \mhmuicore_kit_handle( 'admin' ) );
		self::assertSame( 'mhmuicore-front', \mhmuicore_kit_handle( 'front' ) );
		self::assertSame( 'mhmuicore-pro', \mhmuicore_kit_handle( 'pro' ) );
	}

	public function test_an_unknown_surface_is_refused_not_guessed(): void {
		$this->expectException( InvalidArgumentException::class );
		\mhmuicore_kit_handle( 'kitchen-sink' );
	}

	public function test_enqueue_registers_the_package_handle_not_the_consumers(): void {
		$handle = \mhmuicore_enqueue_kit( 'front' );

		self::assertSame( 'mhmuicore-front', $handle );

		$calls = \mhmuicore_test_calls( 'wp_enqueue_style' );
		self::assertCount( 1, $calls, 'exactly one stylesheet per call' );
		self::assertSame( 'mhmuicore-front', $calls[0]['handle'] );
		self::assertStringContainsString( 'react/front.css', $calls[0]['src'] );
	}

	public function test_the_stylesheet_for_every_surface_exists_in_this_copy(): void {
		foreach ( array( 'admin', 'front', 'pro' ) as $surface ) {
			self::assertFileExists(
				\mhmuicore_asset_path( 'react/' . $surface . '.css' ),
				"surface {$surface} has a handle but no stylesheet"
			);
		}
	}

	public function test_a_pruned_asset_falls_back_to_the_callers_own_copy(): void {
		// The loader serves everyone from the highest registered copy. A free
		// core prunes pro.css from ITS copy, so when that copy wins, the URL
		// would 404. The caller names a root to fall back to; the package still
		// owns the handle and the decision.
		$missing = 'react/definitely-not-here.css';
		self::assertFileDoesNotExist( \mhmuicore_asset_path( $missing ) );

		$GLOBALS['mhmuicore_test_wp_calls'] = array();
		\mhmuicore_enqueue_kit( 'pro', sys_get_temp_dir() . '/some-consumer/vendor/mhm/ui-core' );

		$calls = \mhmuicore_test_calls( 'wp_enqueue_style' );
		self::assertSame( 'mhmuicore-pro', $calls[0]['handle'], 'the handle stays the package\'s' );
	}
}
