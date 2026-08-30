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
		// esc_attr() must run on both the key and the value -- a quote in either
		// one must not be able to close the attribute early and inject a new one.
		$this->assertSame(
			'[hero a&quot;b="A &quot;quoted&quot; title"]',
			ShortcodeMarkup::to_shortcode( 'hero', array( 'a"b' => 'A "quoted" title' ) )
		);
	}
}
