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
	 * WordPress restores $wp_filter and friends between tests, but the loader
	 * keeps its registry in a global of its own, and backupGlobals is off. A
	 * test that poisons it would otherwise leak into every test after it.
	 *
	 * @var array<string, string>|null
	 */
	private $registry_backup;

	public function set_up(): void {
		parent::set_up();
		$this->registry_backup = $GLOBALS['mhmuicore_candidates'] ?? null;
	}

	public function tear_down(): void {
		if ( null === $this->registry_backup ) {
			unset( $GLOBALS['mhmuicore_candidates'] );
		} else {
			$GLOBALS['mhmuicore_candidates'] = $this->registry_backup;
		}

		parent::tear_down();
	}

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

	/**
	 * The winner is chosen and loaded because WordPress dispatched
	 * plugins_loaded, not because the test called mhmuicore_boot(). The state
	 * asserted on here was built once during bootstrap; rebuilding it inside the
	 * test would skip the dispatch that is the whole subject.
	 */
	public function test_only_the_highest_registered_copy_boots(): void {
		self::assertSame( array( '9.9.9' ), $GLOBALS['mhmuicore_test_booted'] ?? array() );
	}

	/**
	 * Control for the test above. An empty registry and a hook that never fired
	 * produce the same empty global, so without this the assertion could be
	 * satisfied for a reason that has nothing to do with the loader.
	 *
	 * This one is deliberately vacuous with respect to this package: it would
	 * pass with none of our code loaded. That is its job.
	 */
	public function test_wordpress_dispatched_plugins_loaded_exactly_once(): void {
		self::assertSame( 1, did_action( 'plugins_loaded' ) );
	}
}
