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
}
