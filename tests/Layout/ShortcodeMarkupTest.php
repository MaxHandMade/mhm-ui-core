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

	public function test_a_value_holding_both_quote_kinds_is_refused(): void {
		// A shortcode attribute has no escape for its own delimiter, and an
		// encoded quote is decoded by kses from 7.2: no string carries both.
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'title' );

		ShortcodeMarkup::to_shortcode( 'hero', array( 'title' => "say \"it's\"" ) );
	}
}
