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

	/** @return array<string, array{0: mixed, 1: int}> */
	public function ceilings(): array {
		return array(
			'zero'       => array( 0, 1 ),
			'negative'   => array( -3, 1 ),
			'large'      => array( 99, 99 ),
			'numeric'    => array( '3', 3 ),
			'non-number' => array( 'wide', 4 ),
		);
	}

	/**
	 * @dataProvider ceilings
	 * @param mixed $given Column argument.
	 */
	public function test_column_ceiling( $given, int $expected ): void {
		self::assertStringContainsString( '--mhmui-columns:' . $expected . '"', StatsGrid::render_html( array(), $given ) );
	}

	public function test_wrapper_function_matches(): void {
		self::assertSame( StatsGrid::render_html( array(), 3 ), \mhmuicore_stats_grid_html( array(), 3 ) );
	}
}
