<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\BlueprintValidator;
use MHMUiCore\Layout\ErrorCodes;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;
use PHPUnit\Framework\TestCase;

/**
 * The engine speaks in codes plus data, never in prose (see BlueprintValidator's
 * class docblock). Every failure case here checks get_error_code()/
 * get_error_data() and confirms get_error_message() is empty -- the negative
 * control for the defect this port fixes: before it, all eleven codes were
 * decorative because the consumer discarded them and kept only the message.
 */
final class BlueprintValidatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	private function validator(): BlueprintValidator {
		return new BlueprintValidator(
			new LayoutContract(
				array(
					'error_prefix'  => 'zzz',
					'markup_prefix' => 'fixture',
					'adapters'      => array( 'hero' => new FixtureAdapter() ),
				)
			)
		);
	}

	/**
	 * A manifest that satisfies every check: used as the base for the negative
	 * cases below so that each one fails on exactly the check it targets.
	 *
	 * @return array<string,mixed>
	 */
	private function valid_manifest(): array {
		return array(
			'version'     => '1.0.0',
			'source'      => array(),
			'pages'       => array(
				array(
					'slug'        => 'home',
					'layout'      => 'default',
					'composition' => array(
						array(
							'component_id' => 'hero',
							'instance_id'  => 'hero-1',
						),
					),
				),
			),
			'tokens'      => array(),
			'components'  => array(),
			'constraints' => array(),
		);
	}

	public function test_a_valid_manifest_passes(): void {
		$this->assertTrue( $this->validator()->validate( $this->valid_manifest() ) );
	}

	public function test_a_missing_root_key_is_reported_by_code_not_prose(): void {
		$result = $this->validator()->validate( array() );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_BLUEPRINT, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message(), 'The package must not produce human text.' );
		$this->assertSame( array( 'key' => 'version' ), $result->get_error_data() );
	}

	public function test_an_unsupported_version_is_reported_by_code_not_prose(): void {
		$manifest             = $this->valid_manifest();
		$manifest['version']  = '2.0.0';

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::UNSUPPORTED_VERSION, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'version' => '2.0.0' ), $result->get_error_data() );
	}

	public function test_a_forbidden_pattern_is_reported_by_code_not_prose(): void {
		$manifest           = $this->valid_manifest();
		$manifest['tokens'] = array( 'framework' => 'tailwind' );

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::FORBIDDEN_PATTERN, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'pattern' => 'tailwind' ), $result->get_error_data() );
	}

	public function test_no_pages_is_reported_by_code_not_prose(): void {
		$manifest          = $this->valid_manifest();
		$manifest['pages'] = array();

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::NO_PAGES, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
	}

	public function test_a_page_missing_a_required_key_is_reported_by_code_not_prose(): void {
		$manifest          = $this->valid_manifest();
		$manifest['pages'] = array( array( 'unrelated' => true ) );

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_PAGE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame(
			array(
				'page_index' => 0,
				'key'        => 'slug',
			),
			$result->get_error_data()
		);
	}

	public function test_an_instance_missing_instance_id_is_reported_by_code_not_prose(): void {
		$manifest          = $this->valid_manifest();
		$manifest['pages'] = array(
			array(
				'slug'        => 'home',
				'layout'      => 'default',
				'composition' => array(
					array( 'component_id' => 'hero' ),
				),
			),
		);

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_INSTANCE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame(
			array(
				'instance_index' => 0,
				'page_index'     => 0,
			),
			$result->get_error_data()
		);
	}

	public function test_non_array_components_are_reported(): void {
		$manifest               = $this->valid_manifest();
		$manifest['components'] = 'nope';

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_COMPONENTS, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
	}

	public function test_a_non_array_page_is_reported_by_code_not_prose(): void {
		// Measured: "pages": ["a", "b"] used to TypeError out of
		// validate_page()'s `array $page` parameter instead of returning a
		// WP_Error. This is an ordinary shape a hand-written or generated
		// manifest can produce, not a programmer error.
		$manifest          = $this->valid_manifest();
		$manifest['pages'] = array( 'a', 'b' );

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_PAGE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame( array( 'page_index' => 0 ), $result->get_error_data() );
	}

	public function test_a_non_string_instance_id_is_reported_by_code_not_prose(): void {
		// Measured: "instance_id": 123 used to make validate() return true, and
		// CompositionBuilder::build() would then TypeError out of the adapter's
		// `string $instance_id` render() parameter -- the validator approved
		// what the builder could not render.
		$manifest          = $this->valid_manifest();
		$manifest['pages'] = array(
			array(
				'slug'        => 'home',
				'layout'      => 'default',
				'composition' => array(
					array(
						'component_id' => 'hero',
						'instance_id'  => 123,
					),
				),
			),
		);

		$result = $this->validator()->validate( $manifest );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::INVALID_INSTANCE, $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
		$this->assertSame(
			array(
				'instance_index' => 0,
				'page_index'     => 0,
			),
			$result->get_error_data()
		);
	}
}
