<?php
/**
 * Phase 5, measured against real WordPress.
 *
 * Two fixture plugins -- a free core and a Pro add-on -- built on this package.
 * The core registers one component through the factory; WordPress itself then
 * renders it as a shortcode and as a block, and the Pro add-on's fill arrives
 * through the seam in both. PurityScanner is run over both trees: the core
 * must be clean, the add-on must trip. That last assertion is what makes the
 * clean result mean something.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests\Integration;

use MHMUiCore\Seam\PurityScanner;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

final class PilotSeamTest extends WP_UnitTestCase {

	private const FIXTURES = __DIR__ . '/fixtures/pilot';

	public function set_up(): void {
		parent::set_up();
		// Guarded on the class, not on a flag: if the first include throws
		// half-way, a flag would already be false-negative on the next test and the
		// second include would fatal on a redeclared class instead of re-raising
		// the real error.
		if ( ! class_exists( 'Pilot_Hero_Renderer', false ) ) {
			require self::FIXTURES . '/free-core/plugin.php';
			require self::FIXTURES . '/pro/plugin.php';
		}
	}

	public function test_wordpress_renders_the_shortcode_through_the_contract_and_the_seam(): void {
		$html = do_shortcode( '[pilot_hero title="Merhaba" showbutton="0" layout="slider" columns="2"]' );

		self::assertStringContainsString( '<h2 class="pilot-hero__title">Merhaba</h2>', $html );
		self::assertStringContainsString( 'pilot-hero--slider', $html );
		self::assertStringContainsString( 'data-columns="2"', $html );
		self::assertStringNotContainsString( 'pilot-hero__button', $html, 'showbutton="0" must switch the button off' );
		self::assertStringContainsString( 'pilot-hero__badge', $html, 'the Pro capability grant must be visible to the core' );
		self::assertStringContainsString( 'pilot-pro-upsell', $html, 'the Pro fill must arrive through the declared slot' );
		self::assertStringContainsString( 'data-layout="slider"', $html, 'the fill receives the typed settings' );
	}

	public function test_wordpress_renders_the_block_server_side_through_the_same_renderer(): void {
		self::assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'pilot/hero' ) );

		$html = do_blocks( '<!-- wp:pilot/hero {"title":"Blok","showButton":false,"layout":"slider","columns":2} /-->' );

		self::assertStringContainsString( '<h2 class="pilot-hero__title">Blok</h2>', $html );
		self::assertStringContainsString( 'pilot-hero--slider', $html );
		self::assertStringNotContainsString( 'pilot-hero__button', $html );
		self::assertStringContainsString( 'pilot-pro-upsell', $html );
	}

	public function test_shortcode_and_block_produce_the_same_markup_for_equivalent_input(): void {
		$from_shortcode = do_shortcode( '[pilot_hero title="Aynı" showbutton="1" layout="grid" columns="3"]' );
		$from_block     = do_blocks( '<!-- wp:pilot/hero {"title":"Aynı","showButton":true,"layout":"grid","columns":3} /-->' );

		self::assertSame( $from_shortcode, $from_block );
	}

	public function test_the_wordpress_hook_bridge_of_the_seam_is_live(): void {
		add_filter(
			'pilot_seam_hero_after',
			static fn( string $html ): string => $html . '<i class="third-party"></i>'
		);

		$html = do_shortcode( '[pilot_hero title="Hook"]' );
		self::assertStringContainsString( '<i class="third-party"></i>', $html );
		self::assertStringContainsString( 'pilot-pro-upsell', $html, 'the add-on fill still runs first' );
	}

	public function test_the_block_wordpress_registered_is_the_block_the_metadata_describes(): void {
		/*
		 * The gap an audit found: `register_block_type()` opens block.json only when
		 * its first argument is an existing path. Handed a block NAME it registers
		 * the PHP arguments and nothing else, so the scaffolded metadata sat on disk
		 * unread and the editor loaded an apiVersion 1 block from an apiVersion 3
		 * file. The unit tests could not see it -- they compared the generated JSON
		 * against itself. This asks WordPress.
		 */
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'pilot/hero' );

		self::assertNotNull( $block );
		self::assertSame( 3, $block->api_version, 'the registered block must carry the metadata api version' );
		self::assertSame( 'Hero', $block->title );
		self::assertArrayHasKey( 'layout', (array) $block->attributes );
		self::assertSame( 'grid', $block->attributes['layout']['default'] );
		self::assertTrue( $block->is_dynamic(), 'the renderer stays the render_callback' );

		/*
		 * Every one of those answers now comes from block.json: with metadata on
		 * disk the only argument passed is the render callback, because core merges
		 * `array_merge( $settings, $args )` and anything else would replace the
		 * file rather than agree with it. ComponentScaffolderTest keeps that file
		 * equal to what the factory generates, so this is not a test about a stale
		 * artefact.
		 */

		/*
		 * `textdomain` is deliberately not asserted: block.json carries it for
		 * translation tooling and WP_Block_Type does not keep it, so a test on it
		 * would pin a guess rather than a contract. That the FILE is what core was
		 * handed is proven one level down, in SurfacesTest.
		 */
	}

	public function test_the_free_core_is_pure_and_the_add_on_is_not(): void {
		$scanner = new PurityScanner();
		self::assertSame( array(), $scanner->self_test(), 'scanner failed its own self-test' );

		self::assertSame( array(), $scanner->scan( self::FIXTURES . '/free-core' ), 'the free core carries a forbidden thing' );

		$pro_hits = array_column( $scanner->scan( self::FIXTURES . '/pro' ), 'class' );
		self::assertContains( PurityScanner::CLASS_HTTP, $pro_hits, 'negative control: the add-on\'s wp_remote_get must be seen' );
		self::assertContains( PurityScanner::CLASS_LICENSE, $pro_hits, 'negative control: the add-on\'s licence function must be seen' );
	}
}
