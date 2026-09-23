<?php
declare(strict_types=1);

namespace MHMUiCore\Tests\Kit;

use MHMUiCore\Kit\Tabs;
use PHPUnit\Framework\TestCase;

/**
 * Structure of the PHP twin, under the marking stubs (esc_html(x), esc_url(x)).
 * Real escaping -- javascript: dropped, <script> inert, kses survival -- is
 * measured only on real WordPress, in tests/Integration/KitEscapingTest.php.
 */
final class TabsTest extends TestCase {

	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	/** Remove the marking stubs' wrappers so the markup can be compared with JSX. */
	private static function unmark( string $html ): string {
		return (string) preg_replace( '/esc_(?:html|url)\(([^()]*)\)/', '$1', $html );
	}

	private static function props(): array {
		return array(
			'label'   => 'Sections',
			'current' => 'pending',
			'items'   => array(
				array(
					'id'         => 'pending',
					'label'      => 'Pending',
					'href'       => '?tab=pending',
					'badge'      => 2,
					'badgeLabel' => '2 pending',
				),
				array(
					'id'    => 'vendors',
					'label' => 'Vendors',
					'href'  => '?tab=vendors',
					'badge' => 3,
				),
				array(
					'id'    => 'iban',
					'label' => 'IBAN',
					'href'  => '?tab=iban',
					'badge' => 0,
				),
			),
		);
	}

	public function test_nav_is_named_and_only_the_current_tab_is_marked(): void {
		$html = self::unmark( Tabs::render_html( self::props() ) );
		self::assertStringStartsWith( '<nav class="mhmui-tabs" aria-label="Sections">', $html );
		self::assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		self::assertStringContainsString( '<a class="mhmui-tabs__tab mhmui-tabs__tab--current" href="?tab=pending" aria-current="page">Pending', $html );
		self::assertStringContainsString( '<a class="mhmui-tabs__tab" href="?tab=vendors">Vendors', $html );
	}

	public function test_badge_markup_is_byte_identical_to_the_jsx_twin(): void {
		$html = self::unmark( Tabs::render_html( self::props() ) );
		// Same string as Tabs.test.jsx pins.
		self::assertStringContainsString(
			'<span class="mhmui-tabs__badge"><span aria-hidden="true">2</span><span class="mhmui-tabs__badge-sr">2 pending</span></span>',
			$html
		);
		self::assertStringContainsString( '<span class="mhmui-tabs__badge">3</span>', $html );
		self::assertSame( 2, substr_count( $html, 'class="mhmui-tabs__badge"' ) );
	}

	public function test_numeric_string_badge_draws_a_chip(): void {
		$html = self::unmark(
			Tabs::render_html(
				array(
					'label' => 'S',
					'items' => array(
						array(
							'id'    => 'a',
							'label' => 'A',
							'href'  => '?a',
							'badge' => '3',
						),
					),
				)
			)
		);
		self::assertStringContainsString( '<span class="mhmui-tabs__badge">3</span>', $html );
	}

	public function test_raw_empty_href_is_skipped_before_escaping(): void {
		$html = self::unmark(
			Tabs::render_html(
				array(
					'label' => 'S',
					'items' => array(
						array(
							'id'    => 'a',
							'label' => 'A',
							'href'  => '',
						),
						array(
							'id'    => 'b',
							'label' => 'B',
							'href'  => '?b',
						),
					),
				)
			)
		);
		self::assertSame( 1, substr_count( $html, '<a ' ) );
		self::assertStringNotContainsString( '>A<', $html );
	}

	public function test_no_current_when_current_is_absent(): void {
		$props = self::props();
		unset( $props['current'] );
		self::assertStringNotContainsString( 'aria-current', Tabs::render_html( $props ) );
	}

	public function test_empty_label_omits_aria_label(): void {
		self::assertStringStartsWith(
			'<nav class="mhmui-tabs">',
			Tabs::render_html(
				array(
					'label' => '',
					'items' => array(),
				)
			)
		);
	}
}
