<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\Normalization;
use PHPUnit\Framework\TestCase;

/**
 * Behavioural coverage for Normalization::normalize().
 *
 * WHY THIS FILE EXISTS
 *
 * NormalizationGoldenHashTest pins the sha256 of ONE fixture. That is a
 * data-format contract, not a behaviour specification, and it is blind to
 * every rule this class implements that the fixture happens not to exercise.
 * Measured against tests/Fixtures/manifests/golden.json: it contains no null
 * value, no numeric string, no float-shaped string and no *_id key holding
 * digits. So normalize() could stop casting '123' to int, or START casting
 * post_id to int -- both silent, breaking data-format changes -- and the
 * golden hash would stay green.
 *
 * These nine assertions came from the consumer, where they were the only
 * thing testing this behaviour: four from
 * tests/Integration/Layout/LayoutNormalizationTest.php and five from
 * tests/Integration/Layout/Versioning/LayoutNormalizationTest.php. The
 * consumer migration plan recorded all six of its layout tests as "copied to
 * the package"; a coverage diff showed that was true of four files and false
 * of these two. The class lives here now, so its behaviour is gated here --
 * a consumer reaching into vendor/ to test the package's internals is the
 * arrangement this package exists to end.
 */
final class NormalizationTest extends TestCase {

	public function test_associative_keys_are_sorted_recursively_and_nulls_are_pruned(): void {
		$normalized = Normalization::normalize(
			array(
				'z_key'    => 'value',
				'a_key'    => array(
					'nested_z' => 2,
					'nested_a' => 1,
				),
				'null_key' => null,
			)
		);

		$this->assertSame(
			array(
				'a_key' => array(
					'nested_a' => 1,
					'nested_z' => 2,
				),
				'z_key' => 'value',
			),
			$normalized
		);
		$this->assertSame( array( 'a_key', 'z_key' ), array_keys( $normalized ) );
	}

	public function test_scalar_strings_are_canonicalised_to_their_native_types(): void {
		$normalized = Normalization::normalize(
			array(
				'is_true'       => 'true',
				'is_false'      => 'false',
				'numeric_int'   => '123',
				'numeric_float' => '123.45',
				'normal_string' => 'hello',
			)
		);

		$this->assertIsBool( $normalized['is_true'] );
		$this->assertTrue( $normalized['is_true'] );
		$this->assertIsBool( $normalized['is_false'] );
		$this->assertFalse( $normalized['is_false'] );
		$this->assertIsInt( $normalized['numeric_int'] );
		$this->assertSame( 123, $normalized['numeric_int'] );
		$this->assertIsFloat( $normalized['numeric_float'] );
		$this->assertSame( 123.45, $normalized['numeric_float'] );
		$this->assertSame( 'hello', $normalized['normal_string'] );
	}

	/**
	 * The rule the golden fixture cannot see at all: identity keys keep their
	 * string type even when every character is a digit. Casting post_id to int
	 * changes the stored manifest's bytes and its hash without changing a
	 * single visible value.
	 */
	public function test_identifier_keys_keep_their_string_type_even_when_numeric(): void {
		$normalized = Normalization::normalize(
			array(
				'id'          => '123',
				'post_id'     => '456',
				'slug'        => 'home-page',
				'parent_slug' => '789',
				'count'       => '789',
			)
		);

		$this->assertIsString( $normalized['id'] );
		$this->assertIsString( $normalized['post_id'] );
		$this->assertIsString( $normalized['parent_slug'] );
		$this->assertSame( 'home-page', $normalized['slug'] );

		// The negative control: an ordinary key carrying the same digits is cast.
		$this->assertIsInt( $normalized['count'] );
	}

	public function test_list_element_order_is_preserved(): void {
		$normalized = Normalization::normalize(
			array(
				'list'    => array( 3, 1, 2 ),
				'complex' => array(
					array(
						'name' => 'B',
						'val'  => 2,
					),
					array(
						'name' => 'A',
						'val'  => 1,
					),
				),
			)
		);

		$this->assertSame( array( 3, 1, 2 ), $normalized['list'] );
		$this->assertSame( 'B', $normalized['complex'][0]['name'] );
	}

	public function test_key_order_in_the_input_does_not_change_the_output(): void {
		$manifest_a = array(
			'version' => '1.0.0',
			'source'  => array(
				'project' => 'abc',
				'env'     => 'prod',
			),
			'tokens'  => array(
				'z' => 'last',
				'a' => 'first',
			),
			'pages'   => array(
				array(
					'slug'       => 'home',
					'title'      => 'Home',
					'components' => array(
						array(
							'type'       => 'hero',
							'attributes' => array(
								'z' => '2',
								'a' => '1',
							),
						),
					),
				),
			),
		);

		$manifest_b = array(
			'pages'   => array(
				array(
					'components' => array(
						array(
							'attributes' => array(
								'a' => '1',
								'z' => '2',
							),
							'type'       => 'hero',
						),
					),
					'title'      => 'Home',
					'slug'       => 'home',
				),
			),
			'tokens'  => array(
				'a' => 'first',
				'z' => 'last',
			),
			'source'  => array(
				'env'     => 'prod',
				'project' => 'abc',
			),
			'version' => '1.0.0',
		);

		$this->assertSame(
			Normalization::normalize( $manifest_a ),
			Normalization::normalize( $manifest_b )
		);
	}

	public function test_a_null_field_and_an_absent_field_normalise_identically(): void {
		$this->assertSame(
			Normalization::normalize(
				array(
					'version' => '1.0.0',
					'meta'    => array( 'x' => null ),
				)
			),
			Normalization::normalize(
				array(
					'version' => '1.0.0',
					'meta'    => array(),
				)
			)
		);
	}

	public function test_string_typed_and_native_typed_manifests_converge(): void {
		$normalized_string = Normalization::normalize(
			array(
				'enabled' => 'true',
				'count'   => '1',
				'ratio'   => '12.5',
			)
		);

		$normalized_native = Normalization::normalize(
			array(
				'enabled' => true,
				'count'   => 1,
				'ratio'   => 12.5,
			)
		);

		$this->assertSame( $normalized_native, $normalized_string );
		$this->assertIsFloat( $normalized_string['ratio'] );
		$this->assertSame( 12.5, $normalized_string['ratio'] );
	}

	/**
	 * The other half of "deterministic": associative keys are sorted, list
	 * order is not. Two manifests whose components appear in a different
	 * order are different manifests and must not collapse to one hash.
	 */
	public function test_component_order_is_significant_and_survives_normalisation(): void {
		$manifest_a = array(
			'pages' => array(
				array(
					'components' => array(
						array(
							'component_id' => 'hero',
							'order'        => 1,
						),
						array(
							'component_id' => 'reviews',
							'order'        => 2,
						),
					),
				),
			),
		);

		$manifest_b = array(
			'pages' => array(
				array(
					'components' => array(
						array(
							'component_id' => 'reviews',
							'order'        => 2,
						),
						array(
							'component_id' => 'hero',
							'order'        => 1,
						),
					),
				),
			),
		);

		$this->assertNotSame(
			Normalization::normalize( $manifest_a ),
			Normalization::normalize( $manifest_b )
		);
	}

	public function test_sorting_reaches_the_deepest_nested_associative_arrays(): void {
		$manifest_a = array(
			'pages' => array(
				array(
					'components' => array(
						array(
							'attributes' => array(
								'styles' => array(
									'desktop' => array(
										'zIndex' => '9',
										'align'  => 'center',
									),
									'mobile'  => array(
										'padding' => '12',
										'color'   => 'red',
									),
								),
								'flags'  => array(
									'enabled'  => 'false',
									'priority' => '2',
								),
							),
						),
					),
				),
			),
		);

		$manifest_b = array(
			'pages' => array(
				array(
					'components' => array(
						array(
							'attributes' => array(
								'flags'  => array(
									'priority' => '2',
									'enabled'  => 'false',
								),
								'styles' => array(
									'mobile'  => array(
										'color'   => 'red',
										'padding' => '12',
									),
									'desktop' => array(
										'align'  => 'center',
										'zIndex' => '9',
									),
								),
							),
						),
					),
				),
			),
		);

		$normalized_a = Normalization::normalize( $manifest_a );

		$this->assertSame( $normalized_a, Normalization::normalize( $manifest_b ) );
		$this->assertSame(
			array( 'enabled', 'priority' ),
			array_keys( $normalized_a['pages'][0]['components'][0]['attributes']['flags'] )
		);
	}
}
