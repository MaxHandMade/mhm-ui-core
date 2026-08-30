<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Layout;

use MHMUiCore\Layout\CompositionBuilder;
use MHMUiCore\Layout\ErrorCodes;
use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Tests\Fixtures\FixtureAdapter;
use PHPUnit\Framework\TestCase;

/**
 * G4 -- the governance gate.
 *
 * Two CDN cases are load-bearing history: adding a literal "cdn.tailwindcss.com"
 * to the pattern list once produced the consuming plugin's only Plugin Check
 * ERROR (the scanner reads a CDN host in source as offloaded content). The ban
 * is expressed as "tailwind", which stripos-matches the URL anyway. Do not
 * reintroduce the literal host.
 */
final class GovernanceGateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		require_once __DIR__ . '/../Fixtures/wp-function-stubs.php';
	}

	private function builder( string $markup, string $markup_prefix = 'fixture' ): CompositionBuilder {
		return new CompositionBuilder(
			new LayoutContract(
				array(
					'error_prefix'  => 'zzz',
					'markup_prefix' => $markup_prefix,
					'adapters'      => array( 'hero' => new FixtureAdapter( $markup ) ),
				)
			)
		);
	}

	/** @return array{0:array<string,mixed>,1:array<string,mixed>} */
	private function manifest(): array {
		return array(
			array(
				'version'    => '1.0.0',
				'components' => array( 'c1' => array( 'type' => 'hero' ) ),
			),
			array( 'composition' => array( array( 'component_id' => 'c1', 'instance_id' => 'i1' ) ) ),
		);
	}

	public function test_a_tailwind_cdn_url_is_blocked(): void {
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<script src="https://cdn.tailwindcss.com"></script>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::TAILWIND_LEAKAGE, $result->get_error_code() );
	}

	public function test_a_tailwind_class_is_blocked(): void {
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<div class="tw-flex"></div>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_prose_that_merely_contains_the_word_tailwind_passes(): void {
		// A second product in a sailing or aviation domain writes this legitimately.
		// The old scan was a stripos over the WHOLE rendered markup and rejected it.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<p>Sail with a strong tailwind today.</p>' )->build( $manifest, $page );

		$this->assertIsString( $result );
	}

	public function test_an_unprefixed_utility_class_is_blocked(): void {
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<div class="bg-red-500"></div>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::UTILITY_LEAKAGE, $result->get_error_code() );
	}

	public function test_a_class_carrying_the_contract_prefix_passes(): void {
		// The old regex exempted the literal "mhm-" only, so a second product's
		// "evimora-bg-card" -- and even the package's own "mhmui-bg-soft" -- were
		// reported as leakage.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<div class="fixture-bg-card"></div>' )->build( $manifest, $page );

		$this->assertIsString( $result );
	}

	public function test_an_unknown_component_reference_is_reported(): void {
		list( $manifest, $page ) = $this->manifest();
		$page                    = array( 'composition' => array( array( 'component_id' => 'ghost', 'instance_id' => 'i1' ) ) );
		$result                  = $this->builder( '<p>x</p>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::UNKNOWN_COMPONENT, $result->get_error_code() );
	}
}
