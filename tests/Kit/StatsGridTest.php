<?php
declare(strict_types=1);

namespace MHMUiCore\Tests\Kit;

use MHMUiCore\Kit\StatsGrid;
use PHPUnit\Framework\TestCase;

final class StatsGridTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	public function test_one_card_per_array_entry_and_non_arrays_skipped(): void {
		$html = StatsGrid::render_html( array( array( 'label' => 'A', 'value' => '1' ), 'junk', array( 'label' => 'B', 'value' => '2' ) ), 2 );
		self::assertSame( 2, substr_count( $html, 'class="mhmui-stat-card"' ) );
		self::assertStringStartsWith( '<div class="mhmui-stats-grid" style="--mhmui-columns:2">', $html );
	}

	public function test_an_object_card_is_drawn_like_the_jsx_twin_draws_it(): void {
		// 0.26.0: a json_decode() row used to be skipped here while StatsGrid.jsx drew it.
		// Fable 0.26.0: the nested delta and data are stdClass too and must
		// draw the same delta line and data-* attribute as the array form.
		$json  = '[{"label":"A","value":"1","tone":"info","delta":{"direction":"up","text":"12%","label":"rise"},"data":{"k":"v"}},{"label":"B","value":"2","sub":"of 9"}]';
		$cards = json_decode( $json );
		self::assertIsArray( $cards );
		$html = StatsGrid::render_html( $cards, 2 );
		self::assertSame( StatsGrid::render_html( json_decode( $json, true ), 2 ), $html );
		self::assertStringContainsString( 'mhmui-stat-card__delta--up', $html );
		self::assertStringContainsString( 'data-k="v"', $html );
		self::assertStringContainsString( 'of 9', $html );
	}

	public function test_objects_that_are_not_rows_are_still_skipped(): void {
		// Only a stdClass row is props; a Closure or an exception would draw an
		// empty card from its (absent) public properties.
		$html = StatsGrid::render_html( array( static fn() => 1, new \RuntimeException( 'x' ), new \ArrayObject( array( 'label' => 'A' ) ) ), 4 );
		self::assertSame( 0, substr_count( $html, 'mhmui-stat-card' ) );
	}

	/** @return array<string, array{0: mixed, 1: int}> */
	public function ceilings(): array {
		return array(
			'zero'       => array( 0, 1 ),
			'negative'   => array( -3, 1 ),
			'large'      => array( 99, 99 ),
			'numeric'    => array( '3', 3 ),
			'non-number' => array( 'wide', 4 ),
			'trailing'   => array( '6abc', 4 ),
			'exponent'   => array( '1e1', 10 ),
			'leading-pt' => array( '.5', 1 ),
			'fraction'   => array( '2.9', 2 ),
			'hex'        => array( '0x10', 4 ),
			'empty'      => array( '', 4 ),
			'bool'       => array( true, 4 ),
			'nbsp'       => array( "\u{a0}3", 4 ),
			'line-sep'   => array( "3\u{2028}", 4 ),
			'ascii-ws'   => array( " \t3\n", 3 ),
			'overflow'   => array( '1e400', 1 ),
			'nan'        => array( NAN, 1 ),
			'inf'        => array( INF, 1 ),
		);
	}

	/**
	 * @dataProvider ceilings
	 * @param mixed $given Column argument.
	 */
	public function test_column_ceiling( $given, int $expected ): void {
		self::assertStringContainsString( '--mhmui-columns:' . $expected . '"', StatsGrid::render_html( array(), $given ) );
	}

	/** @return array<string, array{0: int, 1: mixed, 2: bool}> */
	public function pair_cases(): array {
		return array(
			'4 of 4'          => array( 4, 4, true ),
			'8 of 4'          => array( 8, 4, true ),
			'6 of 6'          => array( 6, 6, true ),
			'4, default 4'    => array( 4, 'wide', true ),
			'3 of 4'          => array( 3, 4, false ),
			'6 of 4'          => array( 6, 4, false ),
			'2 of 2'          => array( 2, 2, false ),
			'3 of 3'          => array( 3, 3, false ),
			'8 of 8'          => array( 8, 8, false ),
			'16 of 8'         => array( 16, 8, false ),
			'12 of 6'         => array( 12, 6, true ),
			'none'            => array( 0, 4, false ),
		);
	}

	/**
	 * Spec 2026-11-10 §2: "4 or 2, never 3" -- the modifier enters only when
	 * the card count is a whole multiple of an even ceiling >= 4.
	 *
	 * @dataProvider pair_cases
	 * @param mixed $columns Column argument.
	 */
	public function test_pairs_modifier( int $count, $columns, bool $pairs ): void {
		$cards = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$cards[] = array( 'label' => 'C' . $i, 'value' => (string) $i );
		}
		$html = StatsGrid::render_html( $cards, $columns );
		$open = substr( $html, 0, (int) strpos( $html, '>' ) );
		self::assertSame( $pairs, str_contains( $open, 'mhmui-stats-grid--pairs' ), $open );
	}

	public function test_pairs_counts_the_cards_it_draws_not_the_entries_it_was_given(): void {
		$card = array( 'label' => 'A', 'value' => '1' );
		self::assertStringStartsWith(
			'<div class="mhmui-stats-grid mhmui-stats-grid--pairs" style="--mhmui-columns:4">',
			StatsGrid::render_html( array( $card, $card, $card, 'junk', $card ), 4 )
		);
		self::assertStringStartsWith(
			'<div class="mhmui-stats-grid" style="--mhmui-columns:4">',
			StatsGrid::render_html( array( $card, $card, $card, 'junk' ), 4 )
		);
	}

	public function test_wrapper_function_matches(): void {
		self::assertSame( StatsGrid::render_html( array(), 3 ), \mhmuicore_stats_grid_html( array(), 3 ) );
	}
}
