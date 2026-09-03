<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Seam;

use InvalidArgumentException;
use MHMUiCore\Seam\SlotRegistry;
use PHPUnit\Framework\TestCase;

final class SlotRegistryTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	public function test_a_declared_slot_can_be_filled_and_applied_in_priority_order(): void {
		$seam = new SlotRegistry( 'pilot' );
		$seam->declare_slot( 'hero_after', 'Markup appended after the hero.' );

		self::assertTrue( $seam->is_declared( 'hero_after' ) );
		self::assertFalse( $seam->has_fills( 'hero_after' ) );

		$seam->fill( 'hero_after', static fn( string $html ): string => $html . '<b>', 20 );
		$seam->fill( 'hero_after', static fn( string $html ): string => $html . '<a>', 5 );

		self::assertTrue( $seam->has_fills( 'hero_after' ) );
		self::assertSame( '<base><a><b>', $seam->apply( 'hero_after', '<base>' ) );
		self::assertSame( array( 'hero_after' => 'Markup appended after the hero.' ), $seam->slots() );
	}

	public function test_run_calls_every_fill_with_the_arguments(): void {
		$seam = new SlotRegistry( 'pilot' );
		$seam->declare_slot( 'booked' );
		$seen = array();
		$seam->fill( 'booked', static function ( int $id ) use ( &$seen ): void { $seen[] = $id; } );

		$seam->run( 'booked', 42 );
		self::assertSame( array( 42 ), $seen );
	}

	public function test_the_seam_bridges_to_wordpress_hooks_under_the_product_prefix(): void {
		$seam = new SlotRegistry( 'pilot' );
		$seam->declare_slot( 'hero_after' );
		$seam->apply( 'hero_after', '' );
		$seam->run( 'hero_after' );

		self::assertSame( 'pilot_seam_hero_after', $seam->hook_name( 'hero_after' ) );
		self::assertContains( array( 'pilot_seam_hero_after' ), mhmuicore_test_calls( 'apply_filters' ) );
		self::assertContains( array( 'pilot_seam_hero_after' ), mhmuicore_test_calls( 'do_action' ) );
	}

	public function test_filling_an_undeclared_slot_is_loud(): void {
		$seam = new SlotRegistry( 'pilot' );
		$this->expectException( InvalidArgumentException::class );
		$seam->fill( 'nobody_declared_this', static fn() => null );
	}

	public function test_applying_an_undeclared_slot_is_loud(): void {
		$seam = new SlotRegistry( 'pilot' );
		$this->expectException( InvalidArgumentException::class );
		$seam->apply( 'nope', 1 );
	}

	public function test_declaring_a_slot_twice_is_loud(): void {
		$seam = new SlotRegistry( 'pilot' );
		$seam->declare_slot( 'x' );
		$this->expectException( InvalidArgumentException::class );
		$seam->declare_slot( 'x' );
	}

	public function test_a_bad_prefix_is_loud(): void {
		$this->expectException( InvalidArgumentException::class );
		new SlotRegistry( 'Bad-Prefix' );
	}
}
