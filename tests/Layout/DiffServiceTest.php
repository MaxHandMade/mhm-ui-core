<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\DiffService;
use PHPUnit\Framework\TestCase;

/**
 * DiffService::diff() returns a fixed three-key shape --
 * array{tokens: array{added:list,removed:list,changed:array}, components:
 * array{added:list,removed:list,changed:list}, pages: array{count_changed:
 * bool, current_count:int, prev_count:int}} -- read directly from the ported
 * class rather than assumed. array_filter() on the top-level result is not a
 * meaningful "no difference" check: every branch always returns a populated
 * three-key array (each containing empty sub-arrays, not an empty array
 * itself), so array_filter() never reduces it to array(). Assertions below
 * therefore inspect the shape's own keys.
 */
final class DiffServiceTest extends TestCase {

	public function test_identical_manifests_produce_no_difference(): void {
		$manifest = array(
			'tokens'     => array( 'color-primary' => '#123456' ),
			'components' => array( 'hero' => array( 'type' => 'hero-banner' ) ),
			'pages'      => array( array( 'slug' => 'home' ) ),
		);

		$diff = DiffService::diff( $manifest, $manifest );

		$this->assertSame( array(), $diff['tokens']['added'] );
		$this->assertSame( array(), $diff['tokens']['removed'] );
		$this->assertSame( array(), $diff['tokens']['changed'] );
		$this->assertSame( array(), $diff['components']['added'] );
		$this->assertSame( array(), $diff['components']['removed'] );
		$this->assertSame( array(), $diff['components']['changed'] );
		$this->assertFalse( $diff['pages']['count_changed'] );
	}

	/**
	 * diff_pages() only compares list counts, not per-page content: a page
	 * whose slug changes while the total page count stays the same is invisible
	 * to it (verified by reading the ported class -- there is no per-page
	 * comparison at all). What it actually reports is a page being added or
	 * removed, which this test exercises instead of a same-count content edit.
	 */
	public function test_a_changed_page_count_is_reported(): void {
		$current  = array( 'pages' => array( array( 'slug' => 'home' ), array( 'slug' => 'about' ) ) );
		$previous = array( 'pages' => array( array( 'slug' => 'home' ) ) );

		$diff = DiffService::diff( $current, $previous );

		$this->assertTrue( $diff['pages']['count_changed'] );
		$this->assertSame( 2, $diff['pages']['current_count'] );
		$this->assertSame( 1, $diff['pages']['prev_count'] );
	}

	public function test_a_changed_token_value_is_reported(): void {
		$current  = array( 'tokens' => array( 'color-primary' => '#111111' ) );
		$previous = array( 'tokens' => array( 'color-primary' => '#222222' ) );

		$diff = DiffService::diff( $current, $previous );

		$this->assertSame(
			array( 'from' => '#222222', 'to' => '#111111' ),
			$diff['tokens']['changed']['color-primary']
		);
	}
}
