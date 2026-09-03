<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Component;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentFactory;
use PHPUnit\Framework\TestCase;

/**
 * Phase 4's acceptance criterion, verbatim from the design document:
 *
 *   "An existing Rentiva component (e.g. FeaturedVehicles) is turned back into
 *    a contract and regenerated from the scaffold; if the result does not match
 *    the existing code, the abstraction is wrong."
 *
 * tests/Fixtures/featured-vehicles.block.json is the product's shipped file,
 * copied byte for byte. featured-vehicles-contract.php is that block written
 * back as a contract. This test regenerates block.json from the contract and
 * demands equality with the product's. Any attribute the contract cannot
 * express -- a type, a default, an enum, a supports key -- fails here.
 */
final class FeaturedVehiclesReproductionTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	public function test_the_shipped_block_json_is_regenerated_from_the_contract(): void {
		$golden = json_decode( (string) file_get_contents( __DIR__ . '/../Fixtures/featured-vehicles.block.json' ), true );
		self::assertIsArray( $golden, 'golden block.json is not readable JSON' );

		$factory = new ComponentFactory(
			array(
				'prefix'          => 'rentiva',
				'block_namespace' => 'mhm-rentiva',
				'text_domain'     => 'mhm-rentiva',
			)
		);
		$contract = new ComponentContract( require __DIR__ . '/../Fixtures/featured-vehicles-contract.php' );

		$generated = $factory->block_json( $contract );

		// Key order is not part of block.json's meaning; values and types are.
		self::assertEquals( $golden, $generated );
		self::assertCount( count( $golden['attributes'] ), $generated['attributes'] );
		self::assertSame( 'mhm-rentiva/featured-vehicles', $generated['name'] );
	}

	public function test_the_shortcode_attribute_set_is_the_same_allowlist(): void {
		$contract = new ComponentContract( require __DIR__ . '/../Fixtures/featured-vehicles-contract.php' );
		$golden   = json_decode( (string) file_get_contents( __DIR__ . '/../Fixtures/featured-vehicles.block.json' ), true );

		self::assertSame( array_keys( $golden['attributes'] ), array_keys( $contract->defaults() ) );

		// The product's shortcode doc says: [rentiva_featured_vehicles limit="6" layout="slider"]
		$typed = $contract->sanitize( array( 'limit' => '6', 'layout' => 'slider', 'sortby' => 'bogus' ) );
		self::assertSame( '6', $typed['limit'] );
		self::assertSame( 'slider', $typed['layout'] );
		self::assertSame( 'date', $typed['sortBy'], 'an unknown sort must fall back, not pass through' );
	}
}
