<?php
/**
 * The kit renderer escapes for real. The unit suite's esc_html stub only MARKS
 * its input (tests/Fixtures/wp-function-stubs.php); this runs the same calls
 * against WordPress's own escaping functions.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests\Integration;

use WP_UnitTestCase;

final class KitEscapingTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		self::assertTrue( function_exists( 'mhmuicore_stat_card_html' ), 'an older ui-core copy booted first; this test cannot measure 0.11.0' );
	}

	public function test_hostile_props_come_out_inert(): void {
		$html = mhmuicore_stat_card_html(
			array(
				'label' => '<script>alert(1)</script>',
				'value' => '"><img src=x onerror=alert(2)>',
				'sub'   => '<b>s</b>',
				'icon'  => 'x" onmouseover="alert(3)',
				'data'  => array( 'stat' => '"><script>alert(4)</script>' ),
			)
		);

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( '<img', $html );
		self::assertStringNotContainsString( 'onmouseover=', $html );
		self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		self::assertStringContainsString( 'class="dashicons dashicons-xonmouseoveralert3"', $html );
		self::assertStringContainsString( '<p class="mhmui-stat-card__value">&quot;&gt;&lt;img src=x onerror=alert(2)&gt;</p>', $html );
		self::assertStringContainsString( '<p class="mhmui-stat-card__sub">&lt;b&gt;s&lt;/b&gt;</p>', $html );
		self::assertStringContainsString( 'data-stat="&quot;&gt;&lt;script&gt;alert(4)&lt;/script&gt;"', $html );
	}

	/**
	 * test_hostile_props_come_out_inert() above never passes a `delta` prop, so
	 * the whole delta branch -- text, the 0.13.0 label, the mark -- had no real-
	 * WordPress escaping coverage (only the unit suite's marking stub touched
	 * it). This closes that gap with a hostile delta.text AND delta.label.
	 */
	public function test_hostile_delta_comes_out_inert(): void {
		$html = mhmuicore_stat_card_html(
			array(
				'label' => 'L',
				'value' => '1',
				'delta' => array(
					'direction' => 'up',
					'text'      => '<script>alert(5)</script>',
					'label'     => '"><img src=x onerror=alert(6)>',
				),
			)
		);

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( '<img', $html );
		self::assertStringContainsString( 'data-direction="up"', $html );
		self::assertStringContainsString(
			'<span class="mhmui-stat-card__delta-mark" aria-hidden="true">' . "\u{2191}" . '</span>',
			$html
		);
		// Trailing space lives INSIDE the sr span's own text (not a bare text
		// node after it), so real esc_html() output carries it too.
		self::assertStringContainsString(
			'<span class="mhmui-stat-card__delta-sr">&quot;&gt;&lt;img src=x onerror=alert(6)&gt; </span>',
			$html
		);
		self::assertStringContainsString( '&lt;script&gt;alert(5)&lt;/script&gt;', $html );
	}

	public function test_grid_style_attribute_is_an_integer_only(): void {
		self::assertStringContainsString( 'style="--mhmui-columns:4"', mhmuicore_stats_grid_html( array(), '4;background:url(x)' ) );
	}

	/**
	 * The contract the README gives consumers: `echo wp_kses_post( mhmuicore_*_html() )`.
	 *
	 * Consumers must escape at the echo -- WP.org's Plugin Check never reads their
	 * phpcs.xml, so declaring the wrappers customEscapingFunctions there leaves the
	 * echo unescaped to the reviewer (measured: six errors in Rentiva's PR #54,
	 * 2026-09-19). That advice is only safe while wp_kses_post() keeps every byte
	 * the kit emits, and this pins it for every branch: dashicon span, tone and
	 * emphasis classes, aria-hidden, data-* hooks, each delta direction with its
	 * mark and accessible name, the sub line, and the grid's
	 * `style="--mhmui-columns:N"`.
	 *
	 * It measures the WordPress this suite runs on (CI: 7.1 and the 7.2 release
	 * candidate), with core's default allowlists. The comparison is on the
	 * markup's meaning, not its spelling: 7.2's HTML API kses re-serialises
	 * every tag with its attributes in name order (same DOM), so both sides go
	 * through same_markup() first. A stripped element or attribute, or a
	 * changed value or text, still fails it. One version edge, read from core source: custom-property
	 * assignment passes safecss_filter_attr() since 6.1.0 (its own @since list).
	 * DATA_KEY accepts only hyphen-separated runs of [a-z0-9], the shape kses has
	 * kept on every version (<= 6.5 `^data(?:-[a-z0-9_]+)+$`, 6.6+
	 * `^data-[a-z0-9_-]+$`); a leading, trailing or doubled hyphen was the one
	 * shape <= 6.5 stripped, so the kit no longer emits it. A kit
	 * change -- or a core change -- that kses would strip turns this red instead
	 * of silently breaking every consumer's strip.
	 */
	public function test_every_kit_branch_survives_wp_kses_post_byte_for_byte(): void {
		$cards = array(
			array(
				'label'    => 'Bookings',
				'value'    => '1,204',
				'icon'     => 'calendar-alt',
				'tone'     => 'success',
				'emphasis' => true,
				'data'     => array(
					'stat'      => 'total-bookings',
					'multi-key' => 'inner hyphen',
				),
				'delta'    => array(
					'direction' => 'up',
					'text'      => '12% this month',
					'label'     => 'rose',
				),
			),
			array(
				'label' => 'Refunds',
				'value' => '3',
				'tone'  => 'danger',
				'delta' => array(
					'direction' => 'down',
					'text'      => '2',
				),
			),
			array(
				'label' => 'Pending',
				'value' => '0',
				'tone'  => 'warning',
				'delta' => array(
					'direction' => 'flat',
					'text'      => '0',
					'label'     => 'no change',
				),
			),
			array(
				'label' => 'Vehicles',
				'value' => '42',
				'tone'  => 'neutral',
				'sub'   => 'No data yet',
			),
		);

		$html = mhmuicore_stats_grid_html( $cards, 4 );

		// The branches are really in the markup, so the equality below is not vacuous.
		foreach ( array( 'style="--mhmui-columns:4"', 'dashicons-calendar-alt', 'aria-hidden="true"', 'mhmui-stat-card--success', 'mhmui-stat-card--emphasis', 'data-stat="total-bookings"', 'data-multi-key="inner hyphen"', 'data-direction="up"', 'data-direction="down"', 'data-direction="flat"', 'mhmui-stat-card__delta-sr', 'mhmui-stat-card__sub' ) as $needle ) {
			self::assertStringContainsString( $needle, $html );
			// Each needle is one attribute or class, so kses' attribute order
			// cannot move it: a second proof that does not rest on same_markup().
			self::assertStringContainsString( $needle, wp_kses_post( $html ) );
		}
		self::assertSame( self::same_markup( $html ), self::same_markup( wp_kses_post( $html ) ) );
	}

	public function test_tabs_hostile_props_come_out_inert(): void {
		self::assertTrue( function_exists( 'mhmuicore_tabs_html' ), 'an older ui-core copy booted first; this test cannot measure 0.15.0' );
		$html = mhmuicore_tabs_html(
			array(
				'label'   => '"><script>alert(1)</script>',
				'current' => 'a',
				'items'   => array(
					array(
						'id'         => 'a',
						'label'      => '<script>alert(2)</script>',
						'href'       => '?tab=a',
						'badge'      => 1,
						'badgeLabel' => '<b>1</b>',
					),
					array(
						'id'    => 'x',
						'label' => 'Hostile',
						'href'  => 'javascript:alert(3)',
					),
				),
			)
		);

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( '<b>', $html );
		self::assertStringContainsString( '&lt;script&gt;alert(2)&lt;/script&gt;', $html );
		// esc_url() empties javascript: -- the item is skipped, not drawn as href="".
		self::assertStringNotContainsString( 'Hostile', $html );
		self::assertStringNotContainsString( 'href=""', $html );
		self::assertStringNotContainsString( 'javascript', $html );
	}

	public function test_tabs_survive_wp_kses_post_byte_for_byte(): void {
		$html = mhmuicore_tabs_html(
			array(
				'label'   => 'Sections',
				'current' => 'a',
				'items'   => array(
					array(
						'id'         => 'a',
						'label'      => 'Pending',
						'href'       => 'admin.php?page=x&tab=a',
						'badge'      => 2,
						'badgeLabel' => '2 pending',
					),
					array(
						'id'    => 'b',
						'label' => 'Vendors',
						'href'  => 'admin.php?page=x&tab=b',
						'badge' => 3,
					),
				),
			)
		);
		foreach ( array( '<nav class="mhmui-tabs" aria-label="Sections">', 'aria-current="page"', 'aria-hidden="true"', 'mhmui-tabs__badge-sr', 'mhmui-tabs__tab--current' ) as $needle ) {
			self::assertStringContainsString( $needle, $html );
		}
		foreach ( array( 'aria-label="Sections"', 'aria-current="page"', 'aria-hidden="true"', 'mhmui-tabs__badge-sr', 'mhmui-tabs__tab--current' ) as $needle ) {
			self::assertStringContainsString( $needle, wp_kses_post( $html ) );
		}
		// Measured 2026-09-23 on WP 7.1: kses normalises the entity esc_url()
		// writes for '&' (&#038;) to &amp; -- the same character, spelled
		// differently; 7.2 also reorders attributes. Nothing is stripped, and
		// same_markup() forgets exactly those spellings (an attribute or
		// element kses dropped would still fail it).
		self::assertStringContainsString( '&#038;tab=', $html );
		self::assertSame( self::same_markup( $html ), self::same_markup( wp_kses_post( $html ) ) );
	}

	public function test_page_header_hostile_props_come_out_inert(): void {
		self::assertTrue( function_exists( 'mhmuicore_page_header_html' ), 'an older ui-core copy booted first; this test cannot measure 0.27.0' );
		$html = mhmuicore_page_header_html(
			array(
				'title'   => '<script>alert(1)</script>',
				'back'    => array(
					'label' => 'Hostile',
					'href'  => 'javascript:alert(2)',
				),
				'badge'   => array(
					'text' => '<b>x</b>',
					'tone' => '"><i',
				),
				'meta'    => '<img src=x onerror=alert(3)>',
				'actions' => '<a class="button" href="?e" onclick="alert(4)">Edit</a><script>alert(5)</script><input name="q">',
			)
		);

		self::assertStringNotContainsString( '<script', $html );
		self::assertStringNotContainsString( '<b>', $html );
		self::assertStringNotContainsString( '<img', $html );
		self::assertStringNotContainsString( 'onclick', $html );
		self::assertStringNotContainsString( '<input', $html );
		self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		self::assertStringContainsString( 'mhmui-status mhmui-status--i"', $html );
		// esc_url() empties javascript: -- no back link, not href="".
		self::assertStringNotContainsString( 'Hostile', $html );
		self::assertStringNotContainsString( 'href=""', $html );
		self::assertStringNotContainsString( 'javascript', $html );
		self::assertStringContainsString( '>Edit</a>', $html );
	}

	/** Where esc_url() makes the PHP twin differ from the JSX (README 0.27.0; Fable 0.27.0). */
	public function test_page_header_href_and_actions_edges(): void {
		$header = static function ( string $href, string $actions = '' ): string {
			return mhmuicore_page_header_html(
				array(
					'title'   => 'T',
					'back'    => array(
						'label' => 'Back',
						'href'  => $href,
					),
					'actions' => $actions,
				)
			);
		};
		// A relative path without /, ?, # or a leading *.php gets http:// -- the JSX draws it as given.
		self::assertStringContainsString( 'href="http://customers/list"', $header( 'customers/list' ) );
		// Paths that start with /, ? or #, or with a *.php file, are kept.
		self::assertStringContainsString( 'href="/wp-admin/x"', $header( '/wp-admin/x' ) );
		self::assertStringContainsString( 'href="admin.php?page=x"', $header( 'admin.php?page=x' ) );
		// A blank href is emptied by esc_url(): no link (the JSX draws href=" ").
		self::assertStringNotContainsString( '__back', $header( '   ' ) );
		// Actions that kses strips to nothing draw no empty wrapper.
		self::assertStringNotContainsString( '__actions', $header( '?b', '<input name="q">' ) );
		self::assertStringContainsString( '__actions', $header( '?b', '<a href="?e">Edit</a>' ) );
	}

	/**
	 * The twin-edge corpus under real esc_html()/esc_url()/wp_kses_post(): the
	 * unit suite draws it under marking stubs, so this is where a real escaper
	 * turning a no-break space into an entity, or kses keeping a blank, would
	 * show (0.27.2). Every case's markup is plain enough that real escaping
	 * leaves it byte for byte.
	 */
	public function test_page_header_twin_edges_hold_under_real_escaping(): void {
		$corpus = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/Fixtures/page-header-twin-edges.json' ), true );
		self::assertGreaterThan( 10, count( $corpus['cases'] ) );
		foreach ( $corpus['cases'] as $case ) {
			self::assertSame( $case['html'], mhmuicore_page_header_html( $case['props'] ), $case['name'] );
		}
	}

	public function test_page_header_survives_wp_kses_post_byte_for_byte(): void {
		$html = mhmuicore_page_header_html(
			array(
				'title'   => 'Marmaris Cars',
				'back'    => array(
					'label' => 'All customers',
					'href'  => 'admin.php?page=x&tab=a',
				),
				'badge'   => array(
					'text' => 'Pending',
					'tone' => 'warning',
				),
				'badges'  => array(
					array(
						'text' => 'Urgent',
						'tone' => 'danger',
					),
				),
				'meta'    => '#9292',
				'actions' => '<a class="button" href="?edit=1">Edit</a>',
				'level'   => 1,
			)
		);
		foreach ( array( 'mhmui-page-header__back', 'aria-hidden="true"', '<h1 class="mhmui-page-header__title">', 'mhmui-page-header__badges', 'mhmui-status--danger', 'mhmui-page-header__actions', 'mhmui-page-header__meta', '&#038;tab=' ) as $needle ) {
			self::assertStringContainsString( $needle, $html );
		}
		self::assertSame( self::same_markup( $html ), self::same_markup( wp_kses_post( $html ) ) );
	}

	public function test_POSITIVE_CONTROL_same_markup_ignores_only_spelling(): void {
		// What it may forget: attribute order and quoting, entity spelling, name
		// case and a void tag's slash -- all the same DOM.
		self::assertSame( self::same_markup( '<a class="x" href="?a=1&#038;b">t<br/></a>' ), self::same_markup( "<A HREF='?a=1&amp;b'  class=x>t<br></a>" ) );
		// Everything kses could take away it must keep.
		$base = self::same_markup( '<p class="x" data-y="1"><span aria-hidden="true">t</span></p>' );
		foreach ( array(
			'<p class="x"><span aria-hidden="true">t</span></p>',
			'<p class="x" data-y="2"><span aria-hidden="true">t</span></p>',
			'<p class="x" data-y="1"><span>t</span></p>',
			'<p class="x" data-y="1">t</p>',
			'<p class="x" data-y="1"><span aria-hidden="true">u</span></p>',
			'<p class="x" data-y="1"><span aria-hidden="true"> t</span></p>',
			'<p class="X" data-y="1"><span aria-hidden="true">t</span></p>',
			'<p class="x" data-y="1">&lt;span aria-hidden="true"&gt;t&lt;/span&gt;</p>',
		) as $stripped ) {
			self::assertNotSame( $base, self::same_markup( $stripped ), $stripped );
		}
		// A boolean attribute, a valued one and none are three DOMs.
		self::assertNotSame( self::same_markup( '<p hidden>t</p>' ), self::same_markup( '<p hidden="x">t</p>' ) );
		self::assertNotSame( self::same_markup( '<p hidden>t</p>' ), self::same_markup( '<p>t</p>' ) );
		// An atomic element's content is one token with the tag; it still counts.
		self::assertNotSame( self::same_markup( '<textarea>a</textarea>' ), self::same_markup( '<textarea>b</textarea>' ) );
	}

	/**
	 * The markup with every spelling choice removed: each tag's attributes in
	 * name order, values and text decoded and re-encoded one way. An element,
	 * an attribute, a value or a text run -- an atomic element's (textarea,
	 * title, script, style) included -- stays in the string, so equality
	 * still means kses lost nothing. Comments, doctypes and other token kinds
	 * fail the walk instead of being forgotten. It parses with the same HTML
	 * API 7.2 kses uses, so a parse defect common to both would not show.
	 *
	 * @param string $html Markup.
	 */
	private static function same_markup( string $html ): string {
		$out       = '';
		$processor = new \WP_HTML_Tag_Processor( $html );

		while ( $processor->next_token() ) {
			$type = $processor->get_token_type();

			if ( '#text' === $type ) {
				$out .= self::encode( $processor->get_modifiable_text() );
				continue;
			}

			self::assertSame( '#tag', $type, "unexpected {$type} token" );

			$name = strtolower( (string) $processor->get_tag() );

			if ( $processor->is_tag_closer() ) {
				$out .= "</{$name}>";
				continue;
			}

			$attributes = (array) $processor->get_attribute_names_with_prefix( '' );
			sort( $attributes );

			$out .= "<{$name}";
			foreach ( $attributes as $attribute ) {
				$value = $processor->get_attribute( $attribute );
				$out  .= true === $value ? " {$attribute}" : sprintf( ' %s="%s"', $attribute, self::encode( (string) $value ) );
			}
			$out .= '>' . self::encode( $processor->get_modifiable_text() );
		}

		self::assertFalse( $processor->paused_at_incomplete_token(), 'the tag processor stopped on an incomplete token' );

		return $out;
	}

	/**
	 * One spelling for text: ENT_SUBSTITUTE, so a value that is not valid UTF-8
	 * still differs from another instead of both collapsing to ''.
	 *
	 * @param string $text Decoded text.
	 */
	private static function encode( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
