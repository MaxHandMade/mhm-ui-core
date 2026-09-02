<?php
/**
 * The loader's WordPress wiring, measured against real WordPress.
 *
 * The unit suite stubs add_action() with a function that discards its callback,
 * so register.php's only production wiring has never been executed by this
 * package's own tests. It has been executed in a consumer's suite -- Rentiva
 * loads the plugin at muplugins_loaded and one of its tests observes a function
 * that only exists once the winning bootstrap has run -- but that coverage is
 * indirect, single-copy, and blind to the priority.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

namespace MHMUiCore\Tests\Integration;

use WP_UnitTestCase;

final class LoaderWiringTest extends WP_UnitTestCase {

	/**
	 * has_action() returns the int priority when the callback is registered and
	 * false when it is not. Priority 0 is falsy, so assertEquals( 0, ... ) would
	 * also pass against a missing registration -- assertSame is the assertion
	 * here, not a style preference. Mutation M0 in MUTATIONS.md exists to keep
	 * it that way.
	 */
	public function test_boot_is_registered_on_plugins_loaded_at_priority_zero(): void {
		self::assertSame( 0, has_action( 'plugins_loaded', 'mhmuicore_boot' ) );
	}
}
