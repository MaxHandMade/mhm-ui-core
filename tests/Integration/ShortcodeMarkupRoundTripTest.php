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
	private function received( string $value ): array {
		$code = ShortcodeMarkup::to_shortcode( 'mhmuicore_probe', array( 'title' => $value ) );
		$post = wp_kses_post( $code );

		self::assertSame( 1, preg_match( '/\[mhmuicore_probe(.*)\]/s', $post, $match ), "kses kept the shortcode: {$post}" );

		$atts = shortcode_parse_atts( trim( $match[1] ) );
		self::assertIsArray( $atts );

		return $atts;
	}

	public function test_a_double_quote_in_a_value_reaches_the_shortcode_intact(): void {
		self::assertSame( array( 'title' => 'kampanya kis "2027"' ), $this->received( 'kampanya kis "2027"' ) );
	}

	public function test_a_plain_value_reaches_the_shortcode_intact(): void {
		self::assertSame( array( 'title' => 'Welcome home' ), $this->received( 'Welcome home' ) );
	}

	public function test_an_encoded_double_quote_in_a_value_reaches_the_shortcode_as_a_quote(): void {
		self::assertSame( array( 'title' => 'p "q" r"' ), $this->received( 'p &quot;q&quot; r&#34;' ) );
	}
}
