<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Component;

use MHMUiCore\Component\ComponentContract;
use MHMUiCore\Component\Surfaces\BlockSurface;
use MHMUiCore\Component\Surfaces\ElementorSurface;
use MHMUiCore\Component\Surfaces\LayoutAdapterSurface;
use MHMUiCore\Component\Surfaces\ShortcodeSurface;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\RecordingRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * One contract, four surfaces, one renderer: every surface must hand the
 * renderer the SAME typed settings for equivalent input, tagged with its own
 * surface name. That equivalence is the whole point of the factory.
 */
final class SurfacesTest extends TestCase {

	/** @var ComponentContract */
	private $contract;

	/** @var RecordingRenderer */
	private $renderer;

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../Fixtures/elementor-stubs.php';
	}

	protected function setUp(): void {
		$this->contract = new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );
		$this->renderer = new RecordingRenderer();
		$GLOBALS['mhmuicore_test_shortcodes'] = array();
		$GLOBALS['mhmuicore_test_blocks']     = array();
	}

	// ── Shortcode ────────────────────────────────────────────────────────────

	public function test_shortcode_tag_is_prefix_underscore_slug(): void {
		self::assertSame( 'pilot_hero', ShortcodeSurface::tag( $this->contract, 'pilot' ) );
	}

	public function test_shortcode_registers_with_wordpress_and_renders_through_the_contract(): void {
		$tag = ShortcodeSurface::register( $this->contract, $this->renderer, 'pilot' );
		self::assertSame( 'pilot_hero', $tag );
		self::assertArrayHasKey( 'pilot_hero', $GLOBALS['mhmuicore_test_shortcodes'] );

		// WordPress lowercases attribute names; the contract declared camelCase.
		$out = $GLOBALS['mhmuicore_test_shortcodes']['pilot_hero']( array( 'showbutton' => '0', 'columns' => '2', 'junk' => 'x' ), 'inner' );

		self::assertSame( array( 'title' => '', 'showButton' => false, 'columns' => 2, 'layout' => 'grid' ), $this->renderer->last_settings() );
		self::assertSame( 'shortcode', $this->renderer->last_context()['surface'] );
		self::assertSame( 'inner', $this->renderer->last_context()['content'] );
		self::assertStringContainsString( 'data-surface="shortcode"', $out );
	}

	// ── Block ────────────────────────────────────────────────────────────────

	public function test_block_json_is_derived_from_the_contract(): void {
		$json = BlockSurface::block_json( $this->contract, 'pilot', 'pilot-td' );

		self::assertSame( 'pilot/hero', $json['name'] );
		self::assertSame( 3, $json['apiVersion'] );
		self::assertSame( 'pilot-td', $json['textdomain'] );
		self::assertSame( 'star-filled', $json['icon'] );
		self::assertSame(
			array(
				'title'      => array( 'type' => 'string', 'default' => '' ),
				'showButton' => array( 'type' => 'boolean', 'default' => true ),
				'columns'    => array( 'type' => 'integer', 'default' => 3 ),
				'layout'     => array( 'type' => 'string', 'default' => 'grid', 'enum' => array( 'grid', 'slider' ) ),
			),
			$json['attributes']
		);
		self::assertFalse( $json['supports']['html'] );
	}

	public function test_block_registers_server_rendered_and_renders_through_the_contract(): void {
		$name = BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td' );
		self::assertSame( 'pilot/hero', $name );

		$args = $GLOBALS['mhmuicore_test_blocks']['pilot/hero'];
		self::assertSame( 'Hero', $args['title'] );
		self::assertIsCallable( $args['render_callback'] );

		$out = $args['render_callback']( array( 'showButton' => false, 'columns' => 2, 'layout' => 'slider' ), '' );

		self::assertSame( array( 'title' => '', 'showButton' => false, 'columns' => 2, 'layout' => 'slider' ), $this->renderer->last_settings() );
		self::assertSame( 'block', $this->renderer->last_context()['surface'] );
		self::assertStringContainsString( 'data-surface="block"', $out );
	}

	// ── Elementor ────────────────────────────────────────────────────────────

	public function test_elementor_controls_are_derived_from_the_contract(): void {
		$controls = ElementorSurface::controls( $this->contract );

		self::assertSame( array( 'title', 'showButton', 'columns', 'layout' ), array_keys( $controls ) );
		self::assertSame( 'text', $controls['title']['type'] );
		self::assertSame( 'switcher', $controls['showButton']['type'] );
		self::assertSame( 'yes', $controls['showButton']['default'] );
		self::assertSame( 'number', $controls['columns']['type'] );
		self::assertSame( 'select', $controls['layout']['type'] );
		self::assertSame( array( 'grid' => 'grid', 'slider' => 'slider' ), $controls['layout']['options'] );
		self::assertSame( 'Show button', $controls['showButton']['label'] );
	}

	public function test_elementor_settings_are_translated_back_through_the_contract(): void {
		$out = ElementorSurface::settings_from_widget(
			$this->contract,
			array( 'showButton' => '', 'columns' => '5', '_elementor_internal' => 'x' )
		);
		self::assertSame( array( 'title' => '', 'showButton' => false, 'columns' => 5, 'layout' => 'grid' ), $out );
	}

	public function test_elementor_widget_registers_one_control_per_setting_and_renders(): void {
		$widget = ElementorSurface::widget( $this->contract, $this->renderer, 'pilot' );
		self::assertNotNull( $widget );
		self::assertSame( 'pilot_hero', $widget->get_name() );
		self::assertSame( 'Hero', $widget->get_title() );
		self::assertSame( array( 'pilot' ), $widget->get_categories() );

		$register = new ReflectionMethod( $widget, 'register_controls' );
		$register->setAccessible( true );
		$register->invoke( $widget );
		self::assertSame( array( 'title', 'showButton', 'columns', 'layout' ), array_keys( $widget->controls ) );

		$widget->settings = array( 'showButton' => 'yes', 'columns' => '7', 'layout' => 'slider' );
		$render           = new ReflectionMethod( $widget, 'render' );
		$render->setAccessible( true );
		ob_start();
		$render->invoke( $widget );
		$out = (string) ob_get_clean();

		self::assertSame( array( 'title' => '', 'showButton' => true, 'columns' => 7, 'layout' => 'slider' ), $this->renderer->last_settings() );
		self::assertSame( 'elementor', $this->renderer->last_context()['surface'] );
		self::assertStringContainsString( 'data-surface="elementor"', $out );
	}

	// ── Layout adapter ───────────────────────────────────────────────────────

	public function test_layout_adapter_renders_through_the_contract_and_fits_a_layout_contract(): void {
		$adapter = LayoutAdapterSurface::adapter( $this->contract, $this->renderer );

		$out = $adapter->render( array( 'columns' => '1', 'showButton' => 'false' ), 'inst-9' );
		self::assertSame( array( 'title' => '', 'showButton' => false, 'columns' => 1, 'layout' => 'grid' ), $this->renderer->last_settings() );
		self::assertSame( 'layout', $this->renderer->last_context()['surface'] );
		self::assertSame( 'inst-9', $this->renderer->last_context()['instance_id'] );
		self::assertStringContainsString( 'data-id="inst-9"', $out );

		$layout = new LayoutContract(
			array(
				'error_prefix'  => 'pilot',
				'markup_prefix' => 'pilot',
				'adapters'      => array( 'hero' => $adapter ),
			)
		);
		self::assertSame( $adapter, $layout->adapter( 'hero' ) );
	}

	// ── The equivalence that justifies the factory ───────────────────────────

	public function test_all_four_surfaces_hand_the_renderer_identical_settings(): void {
		ShortcodeSurface::handle( $this->contract, $this->renderer, 'pilot_hero', array( 'showbutton' => '0', 'columns' => '2' ) );
		$from_shortcode = $this->renderer->last_settings();

		BlockSurface::render( $this->contract, $this->renderer, 'pilot/hero', array( 'showButton' => false, 'columns' => 2 ) );
		$from_block = $this->renderer->last_settings();

		$from_elementor = ElementorSurface::settings_from_widget( $this->contract, array( 'showButton' => '', 'columns' => '2' ) );

		LayoutAdapterSurface::adapter( $this->contract, $this->renderer )->render( array( 'showButton' => '0', 'columns' => '2' ), 'i' );
		$from_layout = $this->renderer->last_settings();

		self::assertSame( $from_shortcode, $from_block );
		self::assertSame( $from_shortcode, $from_elementor );
		self::assertSame( $from_shortcode, $from_layout );
	}
}
