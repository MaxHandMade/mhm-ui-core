<?php

declare(strict_types=1);

namespace MHMUiCore\Tests\Fixtures;

use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * The engine's only currency is WP_Error with an EMPTY message: the package owns
 * no text domain, so the code and the data are the whole payload. A stub that
 * quietly substitutes a message would hide exactly the regression G2 exists for.
 */
final class WpErrorStubTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/wp-function-stubs.php';
	}

	public function test_code_survives_and_message_stays_empty(): void {
		$error = new WP_Error( 'zzz_unknown_component', '', array( 'component_id' => 'hero' ) );

		$this->assertSame( 'zzz_unknown_component', $error->get_error_code() );
		$this->assertSame( '', $error->get_error_message() );
		$this->assertSame( array( 'component_id' => 'hero' ), $error->get_error_data() );
	}

	public function test_empty_data_is_dropped_like_core_drops_it(): void {
		// Core's real WP_Error stores $data only `if ( ! empty( $data ) )`, so
		// get_error_data() returns null for a code built with array() as its
		// payload (NO_PAGES, INVALID_COMPONENTS) -- not array(). A stub that kept
		// array() here would disagree with production about what those two codes
		// actually return.
		$error = new WP_Error( 'zzz_no_pages', '', array() );

		$this->assertNull( $error->get_error_data() );
	}

	public function test_is_wp_error_distinguishes_error_from_string(): void {
		$this->assertTrue( is_wp_error( new WP_Error( 'zzz_x', '' ) ) );
		$this->assertFalse( is_wp_error( '<div></div>' ) );
	}

	public function test_esc_attr_escapes_quotes_but_not_semicolons(): void {
		// Not a wish -- core's behaviour. TokenMapper's hardening (Task 5) exists
		// BECAUSE esc_attr lets ";" through; a stub that escaped it would make
		// the injection negative-control pass for the wrong reason.
		$this->assertSame( '&quot;x&quot;', esc_attr( '"x"' ) );
		$this->assertSame( 'a; b', esc_attr( 'a; b' ) );
	}
}
