<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\LayoutEngine;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Drives the facade end to end -- mhmuicore_layout_engine() -- not the
 * LayoutEngine constructor directly. mhmuicore_layout_engine() is declared in
 * bootstrap.php, which is selected by "highest version wins" (see the
 * comment block above mhmuicore_enqueue_react_page() there) and is NOT part
 * of the Composer PSR-4 autoload map, so it must be required explicitly
 * rather than relied upon to already be loaded by another test class.
 */
final class LayoutEngineTest extends TestCase {

	/**
	 * Boot the package's bootstrap.php once for the whole class, exactly as
	 * ReactPageTest does for the other bootstrap.php facade.
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', sys_get_temp_dir() . '/' );
		}
		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
		require_once __DIR__ . '/../../bootstrap.php';
	}

	private function engine(): LayoutEngine {
		return mhmuicore_layout_engine(
			array(
				'error_prefix'  => 'zzz',
				'markup_prefix' => 'fixture',
				'adapters'      => array( 'hero' => new FixtureAdapter( '<p>ok {id}</p>' ) ),
			)
		);
	}

	public function test_build_renders_through_the_contract_adapter(): void {
		$markup = $this->engine()->build(
			array(
				'version'    => '1.0.0',
				'components' => array( 'c1' => array( 'type' => 'hero' ) ),
			),
			array( 'composition' => array( array( 'component_id' => 'c1', 'instance_id' => 'i1' ) ) )
		);

		$this->assertIsString( $markup );
		$this->assertStringContainsString( 'ok i1', $markup );
	}

	public function test_validate_is_reachable_from_the_facade(): void {
		$result = $this->engine()->validate(
			array(
				'version'     => '1.0.0',
				'source'      => 'test',
				'pages'       => array( array( 'slug' => 'home', 'layout' => 'default', 'composition' => array() ) ),
				'tokens'      => array(),
				'components'  => array(),
				'constraints' => array(),
			)
		);

		$this->assertTrue( $result );
	}

	public function test_normalize_and_diff_are_reachable_from_the_facade(): void {
		$engine   = $this->engine();
		$manifest = array( 'pages' => array( array( 'slug' => 'home' ) ) );

		$this->assertIsArray( $engine->normalize( $manifest ) );
		$this->assertIsArray( $engine->diff( $manifest, $manifest ) );
	}

	/**
	 * A malformed contract must throw, not be swallowed into a WP_Error: it is
	 * a programmer error, not a domain error, and no runtime path can recover
	 * from it (see LayoutContract's own constructor docblock).
	 */
	public function test_a_malformed_contract_throws_out_of_the_facade(): void {
		$this->expectException( \InvalidArgumentException::class );

		mhmuicore_layout_engine( array( 'error_prefix' => 'zzz' ) );
	}
}
