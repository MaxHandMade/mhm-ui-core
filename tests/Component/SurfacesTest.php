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

	/** @var list<string> */
	private $temp_dirs = array();

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../Fixtures/elementor-stubs.php';
	}

	protected function setUp(): void {
		$this->contract = new ComponentContract( require __DIR__ . '/../Fixtures/hero-contract.php' );
		$this->renderer = new RecordingRenderer();
		$GLOBALS['mhmuicore_test_shortcodes'] = array();
		$GLOBALS['mhmuicore_test_blocks']     = array();
		$GLOBALS['mhmuicore_test_wp_calls']   = array();
		$this->temp_dirs                      = array();
	}

	protected function tearDown(): void {
		foreach ( $this->temp_dirs as $dir ) {
			foreach ( (array) glob( $dir . '/*/block.json' ) as $file ) {
				unlink( (string) $file );
				rmdir( dirname( (string) $file ) );
			}
			rmdir( $dir );
		}
		parent::tearDown();
	}

	// ── Block metadata reaches the runtime ───────────────────────────────────

	/**
	 * Build a throwaway blocks directory holding one block.json.
	 *
	 * @param string $kebab The component's kebab slug.
	 * @param string $json  The file's contents.
	 * @return string The blocks directory.
	 */
	private function blocks_dir_with( string $kebab, string $json ): string {
		$root = sys_get_temp_dir() . '/uicore-blocks-' . bin2hex( random_bytes( 4 ) );
		mkdir( $root . '/' . $kebab, 0755, true );
		file_put_contents( $root . '/' . $kebab . '/block.json', $json );
		$this->temp_dirs[] = $root;

		// Forward slashes throughout: metadata_dir() normalises separators, so a
		// Windows temp path would otherwise never equal the key it registers under.
		return str_replace( '\\', '/', $root );
	}

	public function test_the_metadata_file_owns_what_it_declares(): void {
		/*
		 * Core merges the other way round: `$settings = array_merge( $settings,
		 * $args )` in wp-includes/blocks.php, so every argument REPLACES the file's
		 * answer for that key. Passing the contract's title, supports and
		 * attributes beside the file therefore left two descriptions with the
		 * argument winning -- a product that opened block.json to switch wide
		 * alignment on would have watched nothing happen. When the file is there,
		 * it is the description; the only argument left is the render callback,
		 * which is a PHP closure no JSON can carry.
		 */
		$dir = $this->blocks_dir_with(
			'hero',
			'{"apiVersion":3,"name":"pilot/hero","title":"From the file",'
				. '"supports":{"align":true},"attributes":{"align":{"type":"string"}}}'
		);

		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td', $dir );

		$args = $GLOBALS['mhmuicore_test_blocks'][ $dir . '/hero' ];

		self::assertSame( array( 'render_callback' ), array_keys( $args ) );
	}

	public function test_a_metadata_name_that_disagrees_with_the_factory_is_refused(): void {
		/*
		 * `name` is the one key the arguments do not carry, so the file wins it. A
		 * stale file therefore registered `other/hero` while register() returned
		 * `pilot/hero`, and the shortcode, the Layout adapter and every
		 * `<!-- wp:pilot/hero -->` pointed at a block that does not exist. Two
		 * answers to "what is this block called" is a product error, and a loud one
		 * beats a silent one at boot.
		 */
		$dir = $this->blocks_dir_with( 'hero', '{"apiVersion":3,"name":"other/hero"}' );

		$this->expectException( \RuntimeException::class );

		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td', $dir );
	}

	public function test_metadata_wordpress_refuses_is_not_reported_as_registered(): void {
		/*
		 * register_block_type() answers false when the metadata cannot be read --
		 * malformed JSON, no name -- and that answer was thrown away. The block
		 * vanished while register() returned its name and RegisteredComponent
		 * looked healthy: worse than before the repair, because without a file the
		 * block at least existed.
		 */
		$dir = $this->blocks_dir_with( 'hero', '{ not json' );

		$this->expectException( \RuntimeException::class );

		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td', $dir );
	}

	public function test_no_metadata_file_registers_the_block_by_name(): void {
		/*
		 * The fallback's own shape: a path handed to WP_Block_Type_Registry instead
		 * of a name fails its name pattern and the block disappears silently, so
		 * "there is no file" must produce a NAME.
		 */
		$dir = $this->blocks_dir_with( 'somebody-else', '{"apiVersion":3,"name":"pilot/somebody-else"}' );

		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td', $dir );

		self::assertArrayHasKey( 'pilot/hero', $GLOBALS['mhmuicore_test_blocks'] );
	}

	public function test_metadata_is_looked_for_under_the_kebab_slug_the_scaffolder_writes(): void {
		/*
		 * `featured_vehicles` is written by the scaffolder as
		 * blocks/featured-vehicles/. Looking under the raw slug would find nothing
		 * and fall back to an apiVersion 1 registration without a word -- and every
		 * multi-word component in this package's own fixtures has that shape.
		 */
		$contract = new ComponentContract(
			array(
				'slug'     => 'featured_vehicles',
				'title'    => 'Featured',
				'settings' => array(),
			)
		);
		$dir = $this->blocks_dir_with( 'featured-vehicles', '{"apiVersion":3,"name":"pilot/featured-vehicles"}' );

		BlockSurface::register( $contract, $this->renderer, 'pilot', 'pilot-td', $dir );

		self::assertArrayHasKey( $dir . '/featured-vehicles', $GLOBALS['mhmuicore_test_blocks'] );
	}

	public function test_a_blocks_directory_written_with_a_trailing_slash_still_resolves(): void {
		$dir = $this->blocks_dir_with( 'hero', '{"apiVersion":3,"name":"pilot/hero"}' );

		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td', $dir . '/' );

		self::assertArrayHasKey( $dir . '/hero', $GLOBALS['mhmuicore_test_blocks'] );
	}


	public function test_a_block_with_metadata_on_disk_is_registered_from_the_file(): void {
		/*
		 * `register_block_type()` reads block.json only when its first argument is
		 * an existing path -- core's own source is `if ( is_string( $block_type )
		 * && file_exists( $block_type ) ) { return register_block_type_from_metadata(
		 * … ); }`. Handing it a block NAME registers the PHP arguments and nothing
		 * else, so apiVersion and every asset handle the scaffolder wrote stayed on
		 * disk while the tests compared the generated JSON against itself.
		 */
		$root = sys_get_temp_dir() . '/uicore-blocks-' . bin2hex( random_bytes( 4 ) );
		mkdir( $root . '/hero', 0755, true );
		file_put_contents( $root . '/hero/block.json', '{"apiVersion":3,"name":"pilot/hero"}' );

		$GLOBALS['mhmuicore_test_wp_calls'] = array();

		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td', $root );

		$registered = array();
		foreach ( mhmuicore_test_calls( 'register_block_type' ) as $call ) {
			$registered[] = $call[0];
		}

		self::assertCount( 1, $registered, 'the block registers exactly once' );
		self::assertFileExists(
			$registered[0] . '/block.json',
			'what WordPress received must be the directory its metadata is in'
		);

		unlink( $root . '/hero/block.json' );
		rmdir( $root . '/hero' );
		rmdir( $root );
	}

	public function test_a_block_without_metadata_on_disk_still_registers_its_own_surface(): void {
		/*
		 * apiVersion is the FILE's answer, and a product without a metadata file has
		 * no file to answer with -- passing a second, differently typed one through
		 * the arguments is how the two halves diverged in the first place. What the
		 * contract itself knows still travels: title, attributes, supports and the
		 * renderer.
		 */
		BlockSurface::register( $this->contract, $this->renderer, 'pilot', 'pilot-td' );

		$args = $GLOBALS['mhmuicore_test_blocks']['pilot/hero'];

		self::assertArrayNotHasKey( 'api_version', $args );
		self::assertSame( 'Hero', $args['title'] );
		self::assertArrayHasKey( 'layout', $args['attributes'] );
		self::assertIsCallable( $args['render_callback'] );
	}

	public function test_asset_handles_a_contract_declares_reach_the_registration(): void {
		$contract = new ComponentContract(
			array(
				'slug'     => 'promo',
				'title'    => 'Promo',
				'settings' => array(),
				'block'    => array(
					'editorScript' => 'pilot-promo-editor',
					'viewScript'   => 'pilot-promo-view',
					'style'        => 'pilot-promo-style',
					'editorStyle'  => 'pilot-promo-editor-style',
				),
			)
		);

		BlockSurface::register( $contract, $this->renderer, 'pilot', 'pilot-td' );

		$args = $GLOBALS['mhmuicore_test_blocks']['pilot/promo'];

		self::assertSame( array( 'pilot-promo-editor' ), $args['editor_script_handles'] );
		self::assertSame( array( 'pilot-promo-view' ), $args['view_script_handles'] );
		self::assertSame( array( 'pilot-promo-style' ), $args['style_handles'] );
		self::assertSame( array( 'pilot-promo-editor-style' ), $args['editor_style_handles'] );
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
