<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Component;

use InvalidArgumentException;
use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\ComponentFactory;
use MHMUiCore\Layout\LayoutComponentAdapter;
use MHMUiCore\Tests\Fixtures\RecordingRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Drives the facade -- mhmuicore_component_factory() -- the way a product would.
 */
final class ComponentFactoryTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	protected function setUp(): void {
		$GLOBALS['mhmuicore_test_shortcodes'] = array();
		$GLOBALS['mhmuicore_test_blocks']     = array();
	}

	private function factory(): ComponentFactory {
		return mhmuicore_component_factory(
			array(
				'prefix'          => 'pilot',
				'block_namespace' => 'pilot',
				'text_domain'     => 'pilot-td',
			)
		);
	}

	public function test_register_derives_every_surface_from_one_contract(): void {
		$factory  = $this->factory();
		$contract = new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );

		$component = $factory->register( $contract, new RecordingRenderer() );

		self::assertSame( 'pilot_hero', $component->shortcode_tag() );
		self::assertSame( 'pilot/hero', $component->block_name() );
		self::assertInstanceOf( LayoutComponentAdapter::class, $component->layout_adapter() );
		self::assertArrayHasKey( 'pilot_hero', $GLOBALS['mhmuicore_test_shortcodes'] );
		self::assertArrayHasKey( 'pilot/hero', $GLOBALS['mhmuicore_test_blocks'] );
		self::assertSame( array( 'hero' ), array_keys( $factory->layout_adapters() ) );
		self::assertSame( $component, $factory->get( 'hero' ) );

		// Elementor is hooked lazily on its own registration action.
		$hooks = array_column( mhmuicore_test_calls( 'add_action' ), 0 );
		self::assertContains( 'elementor/widgets/register', $hooks );
	}

	public function test_registering_the_same_slug_twice_throws(): void {
		$factory  = $this->factory();
		$contract = new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );
		$factory->register( $contract, new RecordingRenderer() );

		$this->expectException( InvalidArgumentException::class );
		$factory->register( $contract, new RecordingRenderer() );
	}

	/** @dataProvider bad_identities */
	public function test_a_missing_or_malformed_identity_throws( array $config ): void {
		$this->expectException( InvalidArgumentException::class );
		new ComponentFactory( $config );
	}

	public static function bad_identities(): array {
		return array(
			'no prefix'      => array( array( 'block_namespace' => 'p', 'text_domain' => 't' ) ),
			'bad prefix'     => array( array( 'prefix' => 'Bad-One', 'block_namespace' => 'p', 'text_domain' => 't' ) ),
			'bad namespace'  => array( array( 'prefix' => 'p', 'block_namespace' => 'Bad_NS', 'text_domain' => 't' ) ),
			'no text domain' => array( array( 'prefix' => 'p', 'block_namespace' => 'p' ) ),
		);
	}
}
