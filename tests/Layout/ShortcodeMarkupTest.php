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

	public function test_an_encoded_double_quote_counts_as_a_double_quote(): void {
		// esc_attr() keeps a valid entity as it is, and 7.2 kses decodes
		// &quot; / &#34; / &#x22; to " in post text: an already-encoded quote
		// would close a double-quoted attribute just as a raw one does.
		$this->assertSame(
			"[hero a='p \"q\" r\" s\" t\";u v\"w']",
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a' => 'p &quot;q&QUOT; r&#34; s&#x0022; t&quot;;u v&quotw' ) )
		);
	}

	public function test_an_encoded_apostrophe_beside_a_double_quote_keeps_the_double_quoted_form(): void {
		// 7.2 decodes &#039; to ' too, so inside single quotes it would end
		// the attribute: the value counts as holding both kinds.
		$this->assertStringStartsWith(
			'[hero a="say &quot;hi&quot; ',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a' => 'say "hi" &#039;there' ) )
		);
	}

	public function test_a_reference_to_another_character_is_not_a_quote(): void {
		// &#340; is U+0154 and &#x220; U+0220: the digits after 34 / 22 make
		// them different characters, so the value stays double-quoted.
		$this->assertStringStartsWith(
			'[hero a="',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a' => '&#340; &#x220; &#3a' ) )
		);
	}
}
