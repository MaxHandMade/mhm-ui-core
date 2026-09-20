<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Integration;

use MHMUiCore\Kit\Icons;
use WP_UnitTestCase;

/**
 * K5: no concept KEY may be a real Dashicon name, and every VALUE must be one.
 *
 * Measured against the Dashicons stylesheet of the WordPress this suite runs
 * on, not a remembered list -- CI installs 7.1 while the slice was designed
 * against 6.9.1, and the set can grow.
 */
final class IconVocabularyTest extends WP_UnitTestCase {

	/** @return list<string> Suffixes the installed WordPress defines. */
	private function dashicon_suffixes(): array {
		$css = (string) file_get_contents( ABSPATH . WPINC . '/css/dashicons.css' );
		preg_match_all( '/\.dashicons-([a-z0-9-]+):before/', $css, $m );

		return array_values( array_unique( $m[1] ) );
	}

	public function test_POSITIVE_CONTROL_the_stylesheet_was_actually_parsed(): void {
		// Bir regex kaymasi bos kume dondurur ve asagidaki iki test de
		// bos kumeye karsi yesil kalir.
		$suffixes = $this->dashicon_suffixes();

		self::assertGreaterThan( 100, count( $suffixes ), 'dashicons.css parsed to almost nothing' );
		self::assertContains( 'money-alt', $suffixes );
		fwrite( STDERR, "\nIconVocabularyTest: WordPress " . get_bloginfo( 'version' ) . ', ' . count( $suffixes ) . " dashicons\n" );
	}

	public function test_every_concept_VALUE_is_a_real_dashicon(): void {
		$suffixes = $this->dashicon_suffixes();

		foreach ( Icons::map() as $concept => $value ) {
			self::assertContains( $value, $suffixes, "concept '{$concept}' maps to '{$value}', which is not a dashicon" );
		}
	}

	public function test_no_concept_KEY_is_a_real_dashicon(): void {
		$suffixes = $this->dashicon_suffixes();

		foreach ( array_keys( Icons::map() ) as $concept ) {
			self::assertNotContains(
				$concept,
				$suffixes,
				"concept '{$concept}' is ALSO a dashicon name: a consumer writing it as a raw suffix would silently get a different icon"
			);
		}
	}
}
