<?php
/**
 * The kit renderer escapes for real. The unit suite's esc_html stub only MARKS
 * its input (tests/Fixtures/wp-function-stubs.php); this runs the same calls
 * against WordPress's own escaping functions.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests\Integration;

use WP_UnitTestCase;

final class KitEscapingTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'mhmuicore_stat_card_html' ) ) {
			require_once dirname( __DIR__, 2 ) . '/bootstrap.php';
		}
		self::assertTrue( function_exists( 'mhmuicore_stat_card_html' ), 'an older ui-core copy booted first; this test cannot measure 0.11.0' );
	}

	public function test_hostile_props_come_out_inert(): void {
		$html = mhmuicore_stat_card_html(
			array(
				'label' => '<script>alert(1)</script>',
				'value' => '"><img src=x onerror=alert(2)>',
				'sub'   => '<b>s</b>',
				'icon'  => 'x" onmouseover="alert(3)',
				'data'  => array( 'stat' => '"><script>alert(4)</script>' ),
			)
		);

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( '<img', $html );
		self::assertStringNotContainsString( 'onmouseover=', $html );
		self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		self::assertStringContainsString( 'class="dashicons dashicons-xonmouseoveralert3"', $html );
	}

	public function test_grid_style_attribute_is_an_integer_only(): void {
		self::assertStringContainsString( 'style="--mhmui-columns:4"', mhmuicore_stats_grid_html( array(), '4;background:url(x)' ) );
	}
}
