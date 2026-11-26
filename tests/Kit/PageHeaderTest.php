<?php
declare(strict_types=1);

namespace MHMUiCore\Tests\Kit;

use MHMUiCore\Kit\PageHeader;
use PHPUnit\Framework\TestCase;

/**
 * Structure of the PHP twin, under the marking stubs (esc_html(x), esc_url(x),
 * wp_kses_post(x)). Real escaping and kses survival are measured on real
 * WordPress, in tests/Integration/KitEscapingTest.php.
 */
final class PageHeaderTest extends TestCase {

	/** The string PageHeader.test.jsx pins for fixture row 2 (BASE_0152_PAGE_HEADER). */
	const JSX_ROW_2 = '<div class="mhmui-page-header"><a class="mhmui-page-header__back" href="?tab=pending"><span aria-hidden="true">← </span>Pending applications</a><div class="mhmui-page-header__title-row"><h2 class="mhmui-page-header__title">Marmaris Cars</h2><span class="mhmui-status mhmui-status--warning">Pending</span></div><p class="mhmui-page-header__meta">#9292 · 23/09/2026 07:26</p></div>';

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	/** Remove the marking stubs' wrappers so the markup can be compared with JSX. */
	private static function unmark( string $html ): string {
		return (string) preg_replace( '/(?:esc_(?:html|url)|wp_kses_post)\(([^()]*)\)/', '$1', $html );
	}

	private static function render( array $props ): string {
		return self::unmark( PageHeader::render_html( $props ) );
	}

	public function test_markup_is_byte_identical_to_the_jsx_twin(): void {
		$manifest = json_decode( (string) file_get_contents( __DIR__ . '/../../src-react/components.json' ), true );
		self::assertSame( self::JSX_ROW_2, self::render( $manifest['PageHeader']['fixtures'][1] ) );
	}

	/** JSX_ROW_2 is a copy; this ties it to the string the JSX test pins (Fable 0.27.0). */
	public function test_the_pinned_string_is_the_one_the_jsx_test_pins(): void {
		$jsx = (string) file_get_contents( __DIR__ . '/../../src-react/components/PageHeader.test.jsx' );
		self::assertSame( 1, preg_match( "/const BASE_0152_PAGE_HEADER =\\s*'([^']+)';/", $jsx, $m ) );
		self::assertSame( self::JSX_ROW_2, $m[1] );
	}

	public function test_the_public_function_is_the_twin(): void {
		self::assertTrue( function_exists( 'mhmuicore_page_header_html' ) );
		$props = array( 'title' => 'T' );
		self::assertSame( PageHeader::render_html( $props ), mhmuicore_page_header_html( $props ) );
	}

	public function test_badge_and_badges_share_one_wrapper(): void {
		$html = self::render(
			array(
				'title'  => 'T',
				'badge'  => array(
					'text' => 'Pending',
					'tone' => 'warning',
				),
				'badges' => array(
					array(
						'text' => 'Urgent',
						'tone' => 'danger',
					),
					array( 'text' => '' ),
				),
			)
		);
		self::assertStringContainsString( '<div class="mhmui-page-header__badges"><span class="mhmui-status mhmui-status--warning">Pending</span><span class="mhmui-status mhmui-status--danger">Urgent</span></div>', $html );
	}

	public function test_a_badge_without_tone_is_neutral_and_level_falls_back_to_h2(): void {
		$html = self::render(
			array(
				'title' => 'T',
				'badge' => array( 'text' => 'X' ),
				'level' => 7,
			)
		);
		self::assertStringContainsString( '<h2 class="mhmui-page-header__title">T</h2><span class="mhmui-status mhmui-status--neutral">X</span>', $html );
		self::assertStringContainsString( '<h3 ', self::render( array( 'title' => 'T', 'level' => 3 ) ) );
		// A numeric string is not a level, as in the JSX (LEVELS.includes).
		self::assertStringContainsString( '<h2 ', self::render( array( 'title' => 'T', 'level' => '1' ) ) );
	}

	/** @return array<string, array{0: mixed}> */
	public static function nameless_backs(): array {
		return array(
			'missing label' => array( array( 'href' => '?b' ) ),
			'empty label'   => array( array( 'label' => '', 'href' => '?b' ) ),
			'blank label'   => array( array( 'label' => '   ', 'href' => '?b' ) ),
			'boolean label' => array( array( 'label' => true, 'href' => '?b' ) ),
			'empty href'    => array( array( 'label' => 'Back', 'href' => '' ) ),
			'numeric href'  => array( array( 'label' => 'Back', 'href' => 5 ) ),
			'not an array'  => array( 'Back' ),
		);
	}

	/**
	 * @dataProvider nameless_backs
	 * @param mixed $back Back prop.
	 */
	public function test_no_back_link_without_a_label_and_an_href( $back ): void {
		self::assertStringNotContainsString( '__back', self::render( array( 'title' => 'T', 'back' => $back ) ) );
	}

	public function test_a_stdclass_row_reads_like_an_array(): void {
		$props = json_decode( '{"title":"T","back":{"label":"Back","href":"?b"},"badge":{"text":"X","tone":"info"}}' );
		$html  = self::render( (array) $props );
		self::assertStringContainsString( 'href="?b"', $html );
		self::assertStringContainsString( 'mhmui-status--info', $html );
	}

	public function test_actions_go_through_kses_and_meta_only_when_a_string(): void {
		$html = PageHeader::render_html(
			array(
				'title'   => 'T',
				'actions' => '<a href="?e">Edit</a>',
				'meta'    => 5,
			)
		);
		self::assertStringContainsString( '<div class="mhmui-page-header__actions">wp_kses_post(<a href="?e">Edit</a>)</div>', $html );
		self::assertStringNotContainsString( '__meta', $html );
		self::assertStringNotContainsString( '__actions', self::render( array( 'title' => 'T', 'actions' => array( 'x' ) ) ) );
	}

	/** @return array<string, array{0: array<string, mixed>, 1: string}> */
	public static function twin_edges(): array {
		$corpus = json_decode( (string) file_get_contents( __DIR__ . '/../Fixtures/page-header-twin-edges.json' ), true );
		$cases  = array();
		foreach ( $corpus['cases'] as $case ) {
			$cases[ $case['name'] ] = array( $case['props'], $case['html'] );
		}
		return $cases;
	}

	/**
	 * The corpus tests/Gate/page-header-twin-edges.test.js draws with the JSX twin (0.27.2).
	 *
	 * @dataProvider twin_edges
	 * @param array<string, mixed> $props Props.
	 * @param string               $html  The markup both twins draw.
	 */
	public function test_edge_props_draw_what_the_jsx_twin_draws( array $props, string $html ): void {
		self::assertSame( $html, self::render( $props ) );
	}

	public function test_the_corpus_keeps_a_float_level(): void {
		$corpus = json_decode( (string) file_get_contents( __DIR__ . '/../Fixtures/page-header-twin-edges.json' ), true );
		$levels = array_column( array_column( $corpus['cases'], 'props' ), 'level' );
		self::assertContains( 1.0, $levels );
		self::assertIsFloat( $levels[ array_search( 1.0, $levels, false ) ] ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict -- finds the float.
	}

	public function test_badge_text_follows_js_truthiness(): void {
		self::assertStringNotContainsString( 'mhmui-status', self::render( array( 'title' => 'T', 'badge' => array( 'text' => 0 ) ) ) );
		self::assertStringContainsString( '>0</span>', self::render( array( 'title' => 'T', 'badge' => array( 'text' => '0' ) ) ) );
	}
}
