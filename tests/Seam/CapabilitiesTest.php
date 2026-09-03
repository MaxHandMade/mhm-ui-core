<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Seam;

use InvalidArgumentException;
use MHMUiCore\Seam\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	public function test_nothing_is_granted_until_the_add_on_grants_it(): void {
		$caps = new Capabilities( 'pilot' );
		self::assertFalse( $caps->has( 'pro_badge' ) );
		self::assertSame( array(), $caps->granted() );

		$caps->grant( 'pro_badge' );
		self::assertTrue( $caps->has( 'pro_badge' ) );
		self::assertSame( array( 'pro_badge' ), $caps->granted() );
	}

	public function test_has_consults_a_wordpress_filter_under_the_product_prefix(): void {
		$caps = new Capabilities( 'pilot' );
		$caps->has( 'pro_badge' );
		self::assertContains( array( 'pilot_capability_pro_badge' ), mhmuicore_test_calls( 'apply_filters' ) );
	}

	public function test_a_bad_name_is_loud(): void {
		$caps = new Capabilities( 'pilot' );
		$this->expectException( InvalidArgumentException::class );
		$caps->grant( 'Pro Badge' );
	}
}
