<?php
declare(strict_types=1);

namespace MHMUiCore\Tests\Kit;

use MHMUiCore\Kit\StatCard;
use PHPUnit\Framework\TestCase;

final class StatCardTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	private static function classes( string $html ): array {
		preg_match_all( '/class="([^"]*)"/', $html, $m );
		$all = array();
		foreach ( $m[1] as $attr ) {
			foreach ( preg_split( '/\s+/', trim( $attr ) ) as $c ) {
				if ( '' !== $c ) {
					$all[] = $c;
				}
			}
		}
		sort( $all );
		return array_values( array_unique( $all ) );
	}

	public function test_quiet_card_has_label_value_and_no_line(): void {
		$html = StatCard::render_html( array( 'label' => 'Bookings', 'value' => '42' ) );
		self::assertSame(
			array( 'mhmui-stat-card', 'mhmui-stat-card__body', 'mhmui-stat-card__label', 'mhmui-stat-card__value' ),
			self::classes( $html )
		);
	}

	public function test_text_goes_through_esc_html(): void {
		// The stub marks instead of escaping (wp-function-stubs.php): the test proves
		// the call happened; tests/Integration/KitEscapingTest.php proves it escapes.
		$html = StatCard::render_html(
			array(
				'label' => '<script>l</script>',
				'value' => '<b>v</b>',
				'delta' => array( 'direction' => 'up', 'text' => '<i>d</i>' ),
			)
		);
		self::assertStringContainsString( 'esc_html(<script>l</script>)', $html );
		self::assertStringContainsString( 'esc_html(<b>v</b>)', $html );
		self::assertStringContainsString( 'esc_html(<i>d</i>)', $html );
	}

	public function test_tone_outside_the_vocabulary_prints_no_class(): void {
		self::assertSame( 'mhmui-stat-card', self::root_class( array( 'label' => 'L', 'value' => '1', 'tone' => 'purple' ) ) );
		self::assertSame( 'mhmui-stat-card mhmui-stat-card--danger', self::root_class( array( 'label' => 'L', 'value' => '1', 'tone' => 'danger' ) ) );
	}

	public function test_invalid_or_flat_direction_falls_back_to_sub(): void {
		foreach ( array( 'sideways', 'flat' ) as $direction ) {
			$html = StatCard::render_html(
				array( 'label' => 'L', 'value' => '1', 'sub' => 'fallback', 'delta' => array( 'direction' => $direction, 'text' => 'x' ) )
			);
			self::assertStringContainsString( 'mhmui-stat-card__sub', $html );
			self::assertStringNotContainsString( '__delta', $html );
		}
	}

	public function test_non_array_delta_and_missing_label_do_not_throw(): void {
		$html = StatCard::render_html( array( 'value' => '1', 'delta' => 'up', 'label' => array( 'x' ) ) );
		self::assertStringContainsString( '<p class="mhmui-stat-card__label">esc_html()</p>', $html );
	}

	public function test_icon_is_sanitised_and_empty_icon_prints_no_span(): void {
		self::assertStringContainsString(
			'<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>',
			StatCard::render_html( array( 'label' => 'L', 'value' => '1', 'icon' => 'calendar-alt' ) )
		);
		self::assertStringNotContainsString( '<script', StatCard::render_html( array( 'label' => 'L', 'value' => '1', 'icon' => 'x"><script>' ) ) );
		self::assertStringNotContainsString( 'dashicons', StatCard::render_html( array( 'label' => 'L', 'value' => '1', 'icon' => '%%' ) ) );
	}

	public function test_emphasis_only_for_literal_true(): void {
		self::assertSame( 'mhmui-stat-card mhmui-stat-card--emphasis', self::root_class( array( 'label' => 'L', 'value' => '1', 'emphasis' => true ) ) );
		self::assertSame( 'mhmui-stat-card', self::root_class( array( 'label' => 'L', 'value' => '1', 'emphasis' => 'yes' ) ) );
	}

	public function test_data_keys_are_allowlisted_and_values_escaped(): void {
		$html = StatCard::render_html(
			array( 'label' => 'L', 'value' => '1', 'data' => array( 'stat' => 'a"b', 'Bad Key' => 'x', 'on-click' => 'y', 'n' => array() ) )
		);
		self::assertStringContainsString( 'data-stat="a&quot;b"', $html );
		self::assertStringContainsString( 'data-on-click="y"', $html );
		self::assertStringNotContainsString( 'Bad Key', $html );
		self::assertStringNotContainsString( 'data-n=', $html );
	}

	public function test_wrapper_function_is_the_public_api(): void {
		self::assertSame(
			StatCard::render_html( array( 'label' => 'L', 'value' => '1' ) ),
			\mhmuicore_stat_card_html( array( 'label' => 'L', 'value' => '1' ) )
		);
	}

	private static function root_class( array $props ): string {
		preg_match( '/^<div class="([^"]*)"/', StatCard::render_html( $props ), $m );
		return $m[1] ?? '';
	}
}
