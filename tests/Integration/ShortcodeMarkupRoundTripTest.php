<?php
/**
 * ShortcodeMarkup's output read back by WordPress's own shortcode parser,
 * after the kses pass a post's content goes through. The unit suite compares
 * strings; this measures what the shortcode callback actually receives.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests\Integration;

use MHMUiCore\Layout\ShortcodeMarkup;
use WP_UnitTestCase;

final class ShortcodeMarkupRoundTripTest extends WP_UnitTestCase {

	/**
	 * @return array<string,string>
	 */
	private function received( string $value, string $next = 'tail' ): array {
		$code = ShortcodeMarkup::to_shortcode( 'mhmuicore_probe', array( 'title' => $value, 'next' => $next ) );
		$post = wp_kses_post( $code );

		self::assertSame( 1, preg_match( '/\[mhmuicore_probe(.*)\]/s', $post, $match ), "kses kept the shortcode: {$post}" );

		$atts = shortcode_parse_atts( trim( $match[1] ) );
		self::assertIsArray( $atts );

		return $atts;
	}

	public function test_a_double_quote_in_a_value_reaches_the_shortcode_intact(): void {
		self::assertSame( array( 'title' => 'kampanya kis "2027"', 'next' => 'tail' ), $this->received( 'kampanya kis "2027"' ) );
	}

	public function test_a_plain_value_reaches_the_shortcode_intact(): void {
		self::assertSame( array( 'title' => 'Welcome home', 'next' => 'tail' ), $this->received( 'Welcome home' ) );
	}

	public function test_an_encoded_double_quote_in_a_value_reaches_the_shortcode_as_a_quote(): void {
		self::assertSame( array( 'title' => 'p "q" r"', 'next' => 'tail' ), $this->received( 'p &quot;q&quot; r&#34;' ) );
	}

	public function test_a_trailing_or_lone_double_quote_reaches_the_shortcode_intact(): void {
		self::assertSame( array( 'title' => '5"', 'next' => 'tail' ), $this->received( '5"' ) );
		self::assertSame( array( 'title' => '"', 'next' => 'tail' ), $this->received( '"' ) );
	}

	public function test_an_apostrophe_alone_reaches_the_shortcode_as_one_attribute(): void {
		// Double-quoted as &#039;: before 7.2 the callback gets the entity,
		// from 7.2 kses decodes it. Either way the attribute stays whole.
		$atts = $this->received( "it's" );

		self::assertSame( array( 'title', 'next' ), array_keys( $atts ) );
		self::assertContains( $atts['title'], array( "it's", 'it&#039;s' ) );
		self::assertSame( 'tail', $atts['next'] );
	}

	public function test_a_reference_esc_attr_neutralises_keeps_the_0_23_5_bytes(): void {
		// Fable tur 2 M1: a semicolon-less &quot is not a quote to kses once
		// esc_attr() has turned it into &amp;quot; a URL parameter named
		// quote must not become one.
		self::assertSame(
			'[x url="https://x.test/?a=1&amp;quote=2" b="&amp;QUOT; &#340;"]',
			ShortcodeMarkup::to_shortcode( 'x', array( 'url' => 'https://x.test/?a=1&quote=2', 'b' => '&QUOT; &#340;' ) )
		);

		$atts = $this->received( 'https://x.test/?a=1&quote=2' );
		self::assertContains( $atts['title'], array( 'https://x.test/?a=1&quote=2', 'https://x.test/?a=1&amp;quote=2' ) );
		self::assertSame( 'tail', $atts['next'] );
	}

	public function test_an_encoded_apostrophe_beside_a_double_quote_keeps_the_double_quoted_form(): void {
		// 7.2 decodes &#039; to ' too, so single quotes would end at it.
		self::assertStringStartsWith(
			'[x a="say &quot;hi&quot; &#039;there"',
			ShortcodeMarkup::to_shortcode( 'x', array( 'a' => 'say "hi" &#039;there' ) )
		);
	}

	public function test_only_the_references_esc_attr_keeps_become_quotes(): void {
		// Real esc_attr(): &quot; / &#34; / &#x0022; are kept (and become
		// quotes), &QUOT; and a semicolon-less &quot are neutralised (and stay).
		self::assertSame(
			"[x a='p \"q&amp;QUOT; r\" s\" t\";u v&amp;quotw' b='5\" &#x220;']",
			ShortcodeMarkup::to_shortcode( 'x', array( 'a' => 'p &quot;q&QUOT; r&#34; s&#x0022; t&quot;;u v&quotw', 'b' => '5" &#x220;' ) )
		);
	}

	/**
	 * What a registered shortcode's callback receives for one value, through
	 * kses and WordPress's own shortcode regex (not a regex of this test's).
	 *
	 * @return bool Whether the value arrived whole and the next attribute too.
	 */
	private function arrives_whole( string $value ): bool {
		$received = null;
		add_shortcode(
			'mhmuicore_probe',
			static function ( $atts ) use ( &$received ) {
				$received = $atts;
				return '';
			}
		);
		$rest = do_shortcode( wp_kses_post( ShortcodeMarkup::to_shortcode( 'mhmuicore_probe', array( 'title' => $value, 'next' => 'tail' ) ) ) );
		remove_shortcode( 'mhmuicore_probe' );

		return '' === $rest
			&& is_array( $received )
			&& array( 'title', 'next' ) === array_keys( $received )
			&& 'tail' === $received['next']
			&& html_entity_decode( (string) $received['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	public function test_every_value_can_carry_accepts_arrives_whole_on_this_version(): void {
		$corpus = array(
			'plain', 'say "hi"', "it's", "say \"hi\" it's", 'a]b', 'a[b', '[x]', 'a &#93; b', 'a &#x5d; b',
			'<b>bold</b>', 'a < b', 'a/b', 'end/', 'back\slash', "line\nbreak", 'é ü ğ', "\xC3\x28",
			'x=y', "a\"b'c", '&quot;&#039;', '%22', '/]', 'a/]b', 'https://x.test/?a=1&quote=2', 'kampanya kis "2027"',
		);

		$accepted = 0;
		$broken   = array();
		foreach ( $corpus as $value ) {
			$whole = $this->arrives_whole( $value );
			if ( ShortcodeMarkup::can_carry( $value ) ) {
				++$accepted;
				self::assertTrue( $whole, 'can_carry() accepted a value WordPress ' . get_bloginfo( 'version' ) . ' breaks: ' . wp_json_encode( $value ) );
			}
			if ( ! $whole ) {
				$broken[] = $value;
			}
		}

		// Not vacuous: the predicate accepts most of the corpus, and the probe
		// does see breakage on this version (a raw ] breaks on every version).
		self::assertGreaterThanOrEqual( 12, $accepted );
		self::assertContains( 'a]b', $broken );
	}

	public function test_on_7_2_a_value_holding_both_quote_kinds_breaks_and_can_carry_refuses_it(): void {
		if ( version_compare( get_bloginfo( 'version' ), '7.2-alpha', '<' ) ) {
			self::markTestSkipped( 'The HTML API kses that decodes quote references arrives in 7.2.' );
		}
		foreach ( array( "say \"hi\" it's", 'a &#93; b' ) as $value ) {
			self::assertFalse( $this->arrives_whole( $value ), wp_json_encode( $value ) );
			self::assertFalse( ShortcodeMarkup::can_carry( $value ), wp_json_encode( $value ) );
		}
	}
}
