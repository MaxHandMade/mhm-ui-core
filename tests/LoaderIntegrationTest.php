<?php

declare(strict_types=1);

namespace MHM\UiCore\Tests;

use MHM\UiCore\VersionSelector;
use PHPUnit\Framework\TestCase;

/**
 * Proves that the autoloader-free registry in register.php selects the same
 * winner as the canonical VersionSelector, and that it actually loads it.
 */
final class LoaderIntegrationTest extends TestCase {

	/**
	 * Absolute paths of the fake bootstraps created for this test.
	 *
	 * @var array<int, string>
	 */
	private array $temp_files = array();

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}

		// The add_action() stub must be global (register.php calls it
		// unqualified), so it lives in its own non-namespaced fixture file
		// rather than being declared inline in this namespaced test class.
		require_once __DIR__ . '/Fixtures/wp-function-stubs.php';

		require_once __DIR__ . '/../register.php';

		global $mhm_ui_core_candidates;
		$mhm_ui_core_candidates = array();
	}

	protected function tearDown(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_files = array();

		global $mhm_ui_core_candidates;
		$mhm_ui_core_candidates = array();

		parent::tearDown();
	}

	/**
	 * Create a fake bootstrap file that records that it was loaded.
	 *
	 * @param string $marker Global variable name it sets when included.
	 * @return string Absolute path.
	 */
	private function make_bootstrap( string $marker ): string {
		$path = tempnam( sys_get_temp_dir(), 'uicore' ) . '.php';
		file_put_contents( $path, "<?php\n\$GLOBALS['{$marker}'] = true;\n" );
		$this->temp_files[] = $path;

		return $path;
	}

	public function test_highest_version_bootstrap_is_the_one_loaded(): void {
		$old = $this->make_bootstrap( 'loaded_old' );
		$new = $this->make_bootstrap( 'loaded_new' );

		unset( $GLOBALS['loaded_old'], $GLOBALS['loaded_new'] );

		// Register out of order, and with a version pair that lexical sorting gets wrong.
		mhm_ui_core_register( '1.10.0', $new );
		mhm_ui_core_register( '1.9.0', $old );

		mhm_ui_core_boot();

		$this->assertTrue( isset( $GLOBALS['loaded_new'] ), 'The highest version must be loaded.' );
		$this->assertFalse( isset( $GLOBALS['loaded_old'] ), 'The lower version must NOT be loaded.' );
	}

	public function test_registry_agrees_with_canonical_selector(): void {
		$candidates = array(
			'1.9.0'  => '/old/bootstrap.php',
			'1.10.0' => '/new/bootstrap.php',
			'1.2.0'  => '/older/bootstrap.php',
		);

		foreach ( $candidates as $version => $path ) {
			mhm_ui_core_register( (string) $version, $path );
		}

		global $mhm_ui_core_candidates;

		$this->assertSame(
			VersionSelector::select( $candidates ),
			VersionSelector::select( $mhm_ui_core_candidates ),
			'register.php and VersionSelector must never disagree.'
		);
	}

	public function test_boot_is_a_no_op_when_nothing_registered(): void {
		mhm_ui_core_boot();

		$this->addToAssertionCount( 1 ); // Reaching here without error is the assertion.
	}

	public function test_missing_winner_file_boots_nothing_and_does_not_fall_back(): void {
		// The winner (1.10.0) has no file on disk; the runner-up (1.9.0) does.
		// Documents CURRENT behaviour: boot() does not fall back to the next
		// highest version when the winner's bootstrap file is missing — it
		// silently boots nothing. This is not asserted to be desirable, only
		// pinned so a future change to that behaviour is a deliberate choice.
		$old               = $this->make_bootstrap( 'loaded_fallback_candidate' );
		$missing_candidate = sys_get_temp_dir() . '/uicore-does-not-exist-' . uniqid() . '.php';

		unset( $GLOBALS['loaded_fallback_candidate'] );

		mhm_ui_core_register( '1.9.0', $old );
		mhm_ui_core_register( '1.10.0', $missing_candidate );

		mhm_ui_core_boot();

		$this->assertFalse(
			isset( $GLOBALS['loaded_fallback_candidate'] ),
			'boot() must not fall back to the runner-up when the winner file is missing.'
		);
	}

	public function test_duplicate_registration_of_the_same_version_is_deterministic(): void {
		// Two copies register the identical version string; the later
		// registration overwrites the earlier one in the candidates map
		// (same array key), so its bootstrap is the one that boots.
		$first  = $this->make_bootstrap( 'loaded_first_1_0_0' );
		$second = $this->make_bootstrap( 'loaded_second_1_0_0' );

		unset( $GLOBALS['loaded_first_1_0_0'], $GLOBALS['loaded_second_1_0_0'] );

		mhm_ui_core_register( '1.0.0', $first );
		mhm_ui_core_register( '1.0.0', $second );

		mhm_ui_core_boot();

		$this->assertFalse( isset( $GLOBALS['loaded_first_1_0_0'] ), 'The first registration for a duplicate version must be overwritten.' );
		$this->assertTrue( isset( $GLOBALS['loaded_second_1_0_0'] ), 'The last registration for a duplicate version must win deterministically.' );
	}
}
