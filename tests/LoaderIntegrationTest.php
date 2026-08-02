<?php

declare(strict_types=1);

namespace MHMUiCore\Tests;

use MHMUiCore\VersionSelector;
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

		global $mhmuicore_candidates;
		$mhmuicore_candidates = array();
	}

	protected function tearDown(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		$this->temp_files = array();

		global $mhmuicore_candidates;
		$mhmuicore_candidates = array();

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
		mhmuicore_register( '1.10.0', $new );
		mhmuicore_register( '1.9.0', $old );

		mhmuicore_boot();

		$this->assertTrue( isset( $GLOBALS['loaded_new'] ), 'The highest version must be loaded.' );
		$this->assertFalse( isset( $GLOBALS['loaded_old'] ), 'The lower version must NOT be loaded.' );
	}

	/**
	 * Version battles used to prove register.php's own winner-selection loop
	 * agrees with the canonical VersionSelector::select() on the ACTUAL
	 * winner it boots — not merely on re-running the same function twice.
	 *
	 * @return array<string, array<int, array<int, string>>>
	 */
	public static function version_battle_provider(): array {
		return array(
			'lexical trap: "1.10.0" must beat "1.9.0"'                  => array( array( '1.9.0', '1.10.0' ) ),
			'prerelease loses to stable'                                => array( array( '2.0.0-beta.1', '2.0.0' ) ),
			'differently-formatted version strings ("1.0" vs "1.0.0")' => array( array( '1.0.0', '1.0' ) ),
			'single candidate'                                         => array( array( '1.0.0' ) ),
		);
	}

	/**
	 * Proves that register.php's own foreach/version_compare loop — exercised
	 * for real through mhmuicore_register()/mhmuicore_boot() and observed
	 * via which fake bootstrap file actually ran — boots the same file that
	 * the canonical VersionSelector::select() independently picks.
	 *
	 * The two sides of the comparison run genuinely different code:
	 *  - left  (booted):    register.php's registry, driven for real by
	 *                       mhmuicore_boot(); the winner is observed as a
	 *                       side effect (which fake bootstrap set its marker),
	 *                       not read back from the registry.
	 *  - right (canonical): VersionSelector::select(), called directly on an
	 *                       array the test built itself — never on
	 *                       $mhmuicore_candidates, and never fed the other
	 *                       side's output back into itself.
	 *
	 * @dataProvider version_battle_provider
	 * @param array<int, string> $versions Versions to register, in this order.
	 */
	public function test_registry_boot_agrees_with_canonical_selector_on_the_actual_winner( array $versions ): void {
		$path_by_version = array();
		$marker_by_path  = array();

		foreach ( $versions as $index => $version ) {
			$marker = 'battle_' . $index . '_' . preg_replace( '/[^a-zA-Z0-9]/', '_', $version );
			unset( $GLOBALS[ $marker ] );

			$path                         = $this->make_bootstrap( (string) $marker );
			$path_by_version[ $version ]  = $path;
			$marker_by_path[ $path ]      = $marker;
		}

		// Left side: the REAL registry, populated and booted through
		// register.php's own public functions. This runs register.php's
		// foreach/version_compare loop — VersionSelector is not involved.
		foreach ( $path_by_version as $version => $path ) {
			mhmuicore_register( (string) $version, $path );
		}

		mhmuicore_boot();

		$booted_path = null;
		foreach ( $marker_by_path as $path => $marker ) {
			if ( isset( $GLOBALS[ $marker ] ) ) {
				$this->assertNull( $booted_path, 'At most one bootstrap file may load.' );
				$booted_path = $path;
			}
		}

		// Right side: the canonical selector, called directly on the same
		// version=>path facts the test constructed above — not on
		// $mhmuicore_candidates, so this is not a second call feeding the
		// left side's own output back into itself.
		$canonical_path = VersionSelector::select( $path_by_version );

		$this->assertNotNull( $booted_path, 'register.php must boot exactly one candidate.' );
		$this->assertSame(
			$canonical_path,
			$booted_path,
			"register.php's own winner-selection loop must boot the same file VersionSelector::select() would pick."
		);
	}

	public function test_boot_is_a_no_op_when_nothing_registered(): void {
		mhmuicore_boot();

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

		mhmuicore_register( '1.9.0', $old );
		mhmuicore_register( '1.10.0', $missing_candidate );

		mhmuicore_boot();

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

		mhmuicore_register( '1.0.0', $first );
		mhmuicore_register( '1.0.0', $second );

		mhmuicore_boot();

		$this->assertFalse( isset( $GLOBALS['loaded_first_1_0_0'] ), 'The first registration for a duplicate version must be overwritten.' );
		$this->assertTrue( isset( $GLOBALS['loaded_second_1_0_0'] ), 'The last registration for a duplicate version must win deterministically.' );
	}
}
