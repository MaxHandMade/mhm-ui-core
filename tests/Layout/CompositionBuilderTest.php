<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\CompositionBuilder;
use MHMUiCore\Layout\ErrorCodes;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;
use PHPUnit\Framework\TestCase;

/**
 * CompositionBuilder assembles a page's composition into rendered markup and
 * applies design tokens to the root wrapper.
 *
 * MESSAGE-FREE BY DESIGN (see the class's own docblock, and BlueprintValidator's
 * for the full reasoning): every failure case here checks get_error_code() /
 * get_error_data() and confirms get_error_message() is empty -- the negative
 * control for the defect this port fixes.
 */
final class CompositionBuilderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	private function builder( string $markup = '<p>{id}</p>', string $markup_prefix = 'fixture' ): CompositionBuilder {
		return new CompositionBuilder(
			new LayoutContract(
				array(
					'error_prefix'  => 'zzz',
					'markup_prefix' => $markup_prefix,
					'adapters'      => array( 'hero' => new FixtureAdapter( $markup ) ),
				)
			)
		);
	}

	/** @return array{0:array<string,mixed>,1:array<string,mixed>} */
	private function manifest(): array {
		return array(
			array(
				'version'    => '1.0.0',
				'components' => array( 'c1' => array( 'type' => 'hero' ) ),
			),
			array( 'composition' => array( array( 'component_id' => 'c1', 'instance_id' => 'i1' ) ) ),
		);
	}

	public function test_a_valid_composition_builds_markup_with_the_rendered_component(): void {
		list( $manifest, $page ) = $this->manifest();

		$result = $this->builder()->build( $manifest, $page );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '<p>i1</p>', $result );
		$this->assertStringContainsString( 'data-component-type="hero"', $result );
		$this->assertStringContainsString( 'data-instance-id="i1"', $result );
	}

	public function test_design_tokens_are_applied_to_the_root_wrapper(): void {
		list( $manifest, $page ) = $this->manifest();
		$manifest['tokens']      = array( 'colors' => array( 'primary' => '#123456' ) );

		$result = $this->builder()->build( $manifest, $page );

		$this->assertIsString( $result );
		$this->assertStringContainsString( '--mhmui-bp-primary: #123456;', $result );
	}

	public function test_an_unknown_component_reference_is_reported_by_code_not_prose(): void {
		list( $manifest, $page ) = $this->manifest();
		$page                    = array( 'composition' => array( array( 'component_id' => 'ghost', 'instance_id' => 'i1' ) ) );

		$result = $this->builder()->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::UNKNOWN_COMPONENT, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'component_id' => 'ghost' ), $result->get_error_data() );
	}

	public function test_a_missing_adapter_is_reported_by_code_not_prose(): void {
		list( $manifest, $page ) = $this->manifest();
		$manifest['components']  = array( 'c1' => array( 'type' => 'unregistered_type' ) );

		$result = $this->builder()->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::MISSING_ADAPTER, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'type' => 'unregistered_type' ), $result->get_error_data() );
	}

	public function test_a_tailwind_leakage_is_reported_by_code_not_prose(): void {
		list( $manifest, $page ) = $this->manifest();

		$result = $this->builder( '<div class="tw-flex"></div>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::TAILWIND_LEAKAGE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'pattern' => 'tw-' ), $result->get_error_data() );
	}

	public function test_a_utility_leakage_is_reported_by_code_not_prose(): void {
		list( $manifest, $page ) = $this->manifest();

		$result = $this->builder( '<div class="bg-red-500"></div>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::UTILITY_LEAKAGE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'fragment' => 'bg-red-500' ), $result->get_error_data() );
	}

	public function test_wrapper_markup_uses_the_contracts_markup_prefix(): void {
		// Behaviour-neutral for the current consumer: markup_prefix "mhm" must
		// reproduce today's hardcoded "mhm-layout-component"/"mhm-layout-root"
		// classes byte-for-byte, so published post_content and existing CSS keep
		// working.
		list( $manifest, $page ) = $this->manifest();

		$result = $this->builder( '<p>{id}</p>', 'mhm' )->build( $manifest, $page );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="mhm-layout-component"', $result );
		$this->assertStringContainsString( 'class="mhm-layout-root"', $result );
	}

	public function test_wrapper_markup_carries_a_second_products_own_prefix(): void {
		list( $manifest, $page ) = $this->manifest();

		$result = $this->builder( '<p>{id}</p>', 'evimora' )->build( $manifest, $page );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'class="evimora-layout-component"', $result );
		$this->assertStringContainsString( 'class="evimora-layout-root"', $result );
		$this->assertStringNotContainsString( 'mhm-layout', $result );
	}

	public function test_a_non_string_instance_id_is_reported_as_invalid_instance(): void {
		// The validator's own guard (BlueprintValidatorTest) rejects this shape
		// too, but build() is independently reachable through the facade and
		// must not fatal when validate() was skipped.
		list( $manifest ) = $this->manifest();
		$page              = array( 'composition' => array( array( 'component_id' => 'c1', 'instance_id' => 123 ) ) );

		$result = $this->builder()->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_INSTANCE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'instance_id' => 123 ), $result->get_error_data() );
	}

	public function test_a_non_string_type_is_reported_as_missing_adapter(): void {
		list( $manifest, $page ) = $this->manifest();
		$manifest['components']  = array( 'c1' => array( 'type' => 42 ) );

		$result = $this->builder()->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::MISSING_ADAPTER, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'type' => 42 ), $result->get_error_data() );
	}
}
