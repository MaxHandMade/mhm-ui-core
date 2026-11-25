<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\ShortcodeMarkup;
use PHPUnit\Framework\TestCase;

final class ShortcodeMarkupTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	public function test_a_tag_with_no_attributes_has_no_trailing_space(): void {
		$this->assertSame( '[gallery]', ShortcodeMarkup::to_shortcode( 'gallery', array() ) );
	}

	public function test_attributes_are_rendered_as_key_value_pairs_in_order(): void {
		$this->assertSame(
			'[hero title="Welcome" columns="3"]',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'title' => 'Welcome', 'columns' => 3 ) )
		);
	}

	public function test_both_keys_and_values_are_escaped(): void {
		// esc_attr() must run on both the key and the value -- a quote in the
		// key must not be able to close the attribute early and inject a new one.
		$this->assertSame(
			'[hero a&quot;b="&lt;b&gt; &amp; it&#039;s"]',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a"b' => "<b> & it's" ) )
		);
	}

	public function test_a_value_holding_a_double_quote_is_single_quoted_with_the_quote_raw(): void {
		// An encoded &quot; inside a double-quoted attribute reaches the
		// shortcode as the literal entity before WordPress 7.2, and from 7.2
		// kses decodes it in post text, so the attribute ends at it. A raw "
		// inside single quotes is read back intact by every version.
		$this->assertSame(
			"[hero title='A \"quoted\" &lt;b&gt; title']",
			ShortcodeMarkup::to_shortcode( 'hero', array( 'title' => 'A "quoted" <b> title' ) )
		);
	}

	public function test_a_value_holding_both_quote_kinds_keeps_the_old_double_quoted_form(): void {
		// No spelling survives 7.2 kses; the old one still works before 7.2
		// and off the kses path, and refusing would make it a fatal (Fable M1).
		$this->assertSame(
			'[hero title="say &quot;it&#039;s&quot;" b="x &#039;y&#039; &quot;z&quot;"]',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'title' => "say \"it's\"", 'b' => "x 'y' \"z\"" ) )
		);
	}

	public function test_a_single_quoted_value_lets_the_next_attribute_follow(): void {
		$this->assertSame(
			"[hero a='5\"' b='Tom &amp; \"Jerry\"' c=\"x\"]",
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a' => '5"', 'b' => 'Tom & "Jerry"', 'c' => 'x' ) )
		);
	}

	public function test_a_value_that_is_not_utf8_is_emptied_whole_on_both_branches(): void {
		$this->assertSame(
			'[hero a="" b=""]',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a' => "Ko\xE7 \"VIP\"", 'b' => "Ko\xE7" ) )
		);
	}

	/**
	 * @return array<string,array{0:string,1:bool}>
	 */
	public static function carry_cases(): array {
		return array(
			'plain'                    => array( 'Welcome home', true ),
			'empty'                    => array( '', true ),
			'double quotes only'       => array( 'say "hi"', true ),
			'apostrophe only'          => array( "it's", true ),
			'opening bracket'          => array( 'a[b', true ),
			'slash'                    => array( 'a/b', true ),
			'markup'                   => array( '<b>bold</b>', true ),
			'both quote kinds'         => array( "say \"hi\" it's", false ),
			'closing bracket'          => array( 'a]b', false ),
			'a whole shortcode'        => array( '[x]', false ),
			'slash before bracket'     => array( '/]', false ),
			'backslash'                => array( 'back\slash', false ),
			'tab and line feed'        => array( "a\tb\nc", true ),
			'no-break space'           => array( "10\u{00A0}000 TL", false ),
			'zero width space'         => array( "a\u{200B}b", false ),
			'NUL'                      => array( "a\x00b", false ),
			'vertical tab'             => array( "a\x0Bb", false ),
			'carriage return'          => array( "a\r\nb", false ),
		);
	}

	/**
	 * @dataProvider carry_cases
	 */
	public function test_can_carry_answers_whether_every_supported_version_reads_the_value_back( string $value, bool $expected ): void {
		$this->assertSame( $expected, ShortcodeMarkup::can_carry( $value ) );
	}

	public function test_can_carry_refuses_a_value_esc_attr_empties(): void {
		// The stub is htmlspecialchars(), which empties invalid UTF-8 the same
		// way esc_attr() does.
		$this->assertFalse( ShortcodeMarkup::can_carry( "\xC3\x28" ) );
	}

	public function test_can_carry_does_not_change_what_to_shortcode_writes(): void {
		$this->assertSame(
			'[x a="say &quot;hi&quot; it&#039;s" b="a]b"]',
			ShortcodeMarkup::to_shortcode( 'x', array( 'a' => "say \"hi\" it's", 'b' => 'a]b' ) )
		);
	}
}
