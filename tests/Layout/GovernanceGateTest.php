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

	public function test_a_url_path_that_looks_like_a_utility_fragment_passes(): void {
		// "/uploads/hero-w-1200.jpg" contains "-w-1200": a word-boundary "w-"
		// followed by digits, structurally identical to the utility-class shape
		// the UTILITY_FRAGMENTS scan looks for, but sitting inside a src URL.
		// A URL cannot carry a CSS class, so this must pass, not be reported as
		// utility_leakage.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<img src="/uploads/hero-w-1200.jpg">' )->build( $manifest, $page );

		$this->assertIsString( $result );
	}

	public function test_a_framework_hit_inside_href_is_still_blocked(): void {
		// Proves narrowing the utility-fragment scan to class-only did not
		// accidentally narrow the framework scan too: src/href must still be
		// covered there, because a Tailwind CDN reference IS a URL.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<a href="https://cdn.tailwindcss.com/docs">docs</a>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::TAILWIND_LEAKAGE, $result->get_error_code() );
	}

	public function test_a_class_merely_ending_in_the_prefix_is_still_blocked(): void {
		// A fixed-length lookbehind matches trailing TEXT, not a whole token:
		// "xfixture-bg-card" ends in "fixture-" without CARRYING the consumer's
		// prefix as its own leading token -- it is a different word ("xfixture")
		// that happens to end the same way. Before the \b was added inside the
		// lookbehind, this passed unflagged.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<div class="xfixture-bg-card"></div>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::UTILITY_LEAKAGE, $result->get_error_code() );
	}

	public function test_a_style_block_importing_tailwind_is_blocked(): void {
		// Tailwind v4's documented entry point is a bare
		// "@import 'tailwindcss';" inside a stylesheet -- a shape the
		// class/src/href scan never sees, since it is not an attribute value.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<style>@import "tailwindcss";</style>' )->build( $manifest, $page );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'zzz_' . ErrorCodes::TAILWIND_LEAKAGE, $result->get_error_code() );
	}

	public function test_an_ordinary_style_block_still_passes(): void {
		// The <style>-body widening must not turn legitimate CSS into a false
		// positive -- prose (here, plain declarations) must still pass.
		list( $manifest, $page ) = $this->manifest();
		$result                  = $this->builder( '<style>.card { color: red; padding: 4px; }</style>' )->build( $manifest, $page );

		$this->assertIsString( $result );
	}
}
