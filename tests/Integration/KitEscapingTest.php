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
		self::assertStringContainsString( '<p class="mhmui-stat-card__value">&quot;&gt;&lt;img src=x onerror=alert(2)&gt;</p>', $html );
		self::assertStringContainsString( '<p class="mhmui-stat-card__sub">&lt;b&gt;s&lt;/b&gt;</p>', $html );
		self::assertStringContainsString( 'data-stat="&quot;&gt;&lt;script&gt;alert(4)&lt;/script&gt;"', $html );
	}

	/**
	 * test_hostile_props_come_out_inert() above never passes a `delta` prop, so
	 * the whole delta branch -- text, the 0.13.0 label, the mark -- had no real-
	 * WordPress escaping coverage (only the unit suite's marking stub touched
	 * it). This closes that gap with a hostile delta.text AND delta.label.
	 */
	public function test_hostile_delta_comes_out_inert(): void {
		$html = mhmuicore_stat_card_html(
			array(
				'label' => 'L',
				'value' => '1',
				'delta' => array(
					'direction' => 'up',
					'text'      => '<script>alert(5)</script>',
					'label'     => '"><img src=x onerror=alert(6)>',
				),
			)
		);

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( '<img', $html );
		self::assertStringContainsString( 'data-direction="up"', $html );
		self::assertStringContainsString(
			'<span class="mhmui-stat-card__delta-mark" aria-hidden="true">' . "\u{2191}" . '</span>',
			$html
		);
		// Trailing space lives INSIDE the sr span's own text (not a bare text
		// node after it), so real esc_html() output carries it too.
		self::assertStringContainsString(
			'<span class="mhmui-stat-card__delta-sr">&quot;&gt;&lt;img src=x onerror=alert(6)&gt; </span>',
			$html
		);
		self::assertStringContainsString( '&lt;script&gt;alert(5)&lt;/script&gt;', $html );
	}

	public function test_grid_style_attribute_is_an_integer_only(): void {
		self::assertStringContainsString( 'style="--mhmui-columns:4"', mhmuicore_stats_grid_html( array(), '4;background:url(x)' ) );
	}

	/**
	 * The contract the README gives consumers: `echo wp_kses_post( mhmuicore_*_html() )`.
	 *
	 * Consumers must escape at the echo -- WP.org's Plugin Check never reads their
	 * phpcs.xml, so declaring the wrappers customEscapingFunctions there leaves the
	 * echo unescaped to the reviewer (measured: six errors in Rentiva's PR #54,
	 * 2026-09-19). That advice is only safe while wp_kses_post() keeps every byte
	 * the kit emits, and this pins it for every branch: dashicon span, tone and
	 * emphasis classes, data-* hooks, each delta direction with its mark and
	 * accessible name, the sub line, and the grid's `style="--mhmui-columns:N"`
	 * (custom-property assignment passes safecss_filter_attr() since WP 6.1.0, per
	 * its own @since list). A kit change -- or a core change -- that kses would
	 * strip turns this red instead of silently breaking every consumer's strip.
	 */
	public function test_every_kit_branch_survives_wp_kses_post_byte_for_byte(): void {
		$cards = array(
			array(
				'label'    => 'Bookings',
				'value'    => '1,204',
				'icon'     => 'calendar-alt',
				'tone'     => 'success',
				'emphasis' => true,
				'data'     => array( 'stat' => 'total-bookings' ),
				'delta'    => array(
					'direction' => 'up',
					'text'      => '12% this month',
					'label'     => 'rose',
				),
			),
			array(
				'label' => 'Refunds',
				'value' => '3',
				'tone'  => 'danger',
				'delta' => array(
					'direction' => 'down',
					'text'      => '2',
				),
			),
			array(
				'label' => 'Pending',
				'value' => '0',
				'tone'  => 'warning',
				'delta' => array(
					'direction' => 'flat',
					'text'      => '0',
					'label'     => 'no change',
				),
			),
			array(
				'label' => 'Vehicles',
				'value' => '42',
				'tone'  => 'neutral',
				'sub'   => 'No data yet',
			),
		);

		$html = mhmuicore_stats_grid_html( $cards, 4 );

		// The branches are really in the markup, so the equality below is not vacuous.
		foreach ( array( 'style="--mhmui-columns:4"', 'dashicons-calendar-alt', 'mhmui-stat-card--emphasis', 'data-stat="total-bookings"', 'data-direction="up"', 'data-direction="down"', 'data-direction="flat"', 'mhmui-stat-card__delta-sr', 'mhmui-stat-card__sub' ) as $needle ) {
			self::assertStringContainsString( $needle, $html );
		}
		self::assertSame( $html, wp_kses_post( $html ) );
	}
}
