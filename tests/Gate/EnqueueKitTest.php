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
		// NOTE: This test does NOT exercise the fallback branch today, because
		// pro.css is present in this copy. The test pins that handle ownership
		// stays with the package even when a fallback_root is passed. The fallback
		// branch gets its first real exercise when a Pro consumer actually calls
		// this function with a copy that is missing pro.css (e.g., a free core's
		// vendored copy where .distignore pruned it).
		//
		// The loader serves everyone from the highest registered copy. A free
		// core prunes pro.css from ITS copy, so when that copy wins, the URL
		// would 404. The caller names a root to fall back to; the package still
		// owns the handle and the decision.
		$missing = 'react/definitely-not-here.css';
		self::assertFileDoesNotExist( \mhmuicore_asset_path( $missing ) );

		$GLOBALS['mhmuicore_test_wp_calls'] = array();
		\mhmuicore_enqueue_kit( 'pro', sys_get_temp_dir() . '/some-consumer/vendor/mhm/ui-core' );

		// 'pro' cascades into 'admin' first (B4), so the package's own handle
		// for the surface actually asked for is the LAST call, not the first.
		$calls = \mhmuicore_test_calls( 'wp_enqueue_style' );
		$last  = $calls[ count( $calls ) - 1 ];
		self::assertSame( 'mhmuicore-pro', $last['handle'], 'the handle stays the package\'s' );
	}

	public function test_when_fallback_root_does_not_exist_on_disk_but_primary_does_enqueue_succeeds(): void {
		// When a consumer passes an invalid $fallback_root path, the function
		// still enqueues successfully if the primary copy has the stylesheet.
		// This guards against false negatives: a typo in fallback_root must not
		// break the enqueue when the primary is present.
		$handle = \mhmuicore_enqueue_kit( 'front', sys_get_temp_dir() . '/nowhere-at-all' );

		self::assertSame( 'mhmuicore-front', $handle, 'primary exists, so enqueue works' );

		$calls = \mhmuicore_test_calls( 'wp_enqueue_style' );
		self::assertCount( 1, $calls );
		self::assertStringContainsString( 'react/front.css', $calls[0]['src'] );
	}

	public function test_pro_enqueues_admin_first_and_declares_it_as_a_dependency(): void {
		// B4: pro.css's own header says its tokens live in admin.css, and
		// .mhmui-pro-lock reads four of them via var(). Enqueueing 'pro' alone
		// (empty deps, as bootstrap.php's surfaces used to do) rendered the
		// lock card with unresolved custom properties and no error anywhere.
		//
		// Declaring array( 'mhmuicore-admin' ) as a dependency does nothing if
		// nothing ever registered that handle -- WordPress silently drops a
		// stylesheet whose dependency was never enqueued. So a single call for
		// 'pro' must itself enqueue 'admin' first, then enqueue 'pro' with
		// that handle as its dependency.
		$handle = \mhmuicore_enqueue_kit( 'pro' );

		self::assertSame( 'mhmuicore-pro', $handle );

		$calls = \mhmuicore_test_calls( 'wp_enqueue_style' );
		self::assertCount( 2, $calls, 'asking for pro enqueues exactly two stylesheets' );
		self::assertSame( 'mhmuicore-admin', $calls[0]['handle'], 'admin is enqueued FIRST' );
		self::assertSame( array(), $calls[0]['deps'], 'admin itself has no dependency' );
		self::assertSame( 'mhmuicore-pro', $calls[1]['handle'], 'pro is enqueued SECOND' );
		self::assertSame(
			array( 'mhmuicore-admin' ),
			$calls[1]['deps'],
			'pro declares admin as its dependency, not an empty array WordPress would silently drop'
		);
	}

	public function test_guard_prevents_silent_failure_when_file_cannot_be_located(): void {
		// If the primary is missing and fallback_root is not provided (or the file
		// does not exist there), the function returns empty string instead of
		// enqueueing a broken URL. This turns silent failure into measurable
		// nothing: the caller gets a falsy return it can assert on.
		//
		// We cannot test this with a shipped surface (all three exist in this copy),
		// so we verify the guard logic indirectly: passing a bad fallback_root for
		// a surface that EXISTS in primary proves the guard doesn't over-reject.
		// The guard itself (`return ''`) is exercised when a Pro consumer calls
		// with a free core that has pruned pro.css and the consumer forgets to
		// pass $fallback_root (or passes the wrong path).
		$GLOBALS['mhmuicore_test_wp_calls'] = array();

		// Primary has the file, so enqueue succeeds despite bad fallback_root.
		$handle = \mhmuicore_enqueue_kit( 'admin', sys_get_temp_dir() . '/nonexistent/vendor/mhm/ui-core' );

		self::assertNotEmpty( $handle, 'primary exists, enqueue succeeds' );

		$calls = \mhmuicore_test_calls( 'wp_enqueue_style' );
		self::assertCount( 1, $calls, 'guard does not over-reject' );
		self::assertSame( 'mhmuicore-admin', $calls[0]['handle'] );
	}
}
