<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Kit;

use MHMUiCore\Kit\IconConceptScanner;
use PHPUnit\Framework\TestCase;

final class IconConceptScannerTest extends TestCase {

	private const ANCHORS = array(
		'mhmuicore_stat_card_html',
		'mhmuicore_stats_grid_html',
		'ui-core/src-react/components/Stat',
		'AssetManager::stats_grid_html',
	);

	private function dir(): string {
		return dirname( __DIR__ ) . '/Fixtures/icon-concepts';
	}

	/** @return array{raw: list<array<string, mixed>>, unknown: list<array<string, mixed>>, concepts: int, files: int} */
	private function scan(): array {
		return ( new IconConceptScanner( self::ANCHORS ) )->scan( array( $this->dir() ) );
	}

	public function test_POSITIVE_CONTROL_the_scanner_reaches_anchored_files(): void {
		// Negatif kontrolun pozitif kontrolu: capa kapsami her seyi eleyip
		// kapiyi kor etmis olabilir. v1'in bloke edici kusuru tam buydu --
		// kapi hicbir sey olcmeden yesildi.
		$result = $this->scan();

		self::assertSame( 5, $result['files'], 'exactly the five anchored fixtures must be scanned' );
		self::assertSame( 2, $result['concepts'], "kit-caller.php's 'revenue' and wrapper-caller.php's 'place'" );
	}

	public function test_a_raw_suffix_with_a_concept_is_reported_in_both_languages(): void {
		$raw = $this->scan()['raw'];

		self::assertCount( 2, $raw, 'raw-suffix.php and kit-caller.jsx both write money-alt' );
		foreach ( $raw as $hit ) {
			self::assertSame( 'money-alt', $hit['value'] );
			self::assertContains( 'revenue', $hit['concepts'] );
			self::assertGreaterThan( 0, $hit['line'] );
		}
	}

	public function test_an_unknown_suffix_is_listed_but_not_fatal(): void {
		$unknown = $this->scan()['unknown'];

		self::assertCount( 1, $unknown );
		self::assertSame( 'fuel', $unknown[0]['value'] );
	}

	public function test_a_file_without_a_kit_anchor_is_never_scanned(): void {
		// Olculdu 2026-09-20: Rentiva'nin agacinda 'icon' => yazan UC ayri
		// sozluk var (kit karti, urunun SVG sozlugu, dashicons- onekli dugme
		// yardimcisi). Capasiz tarayici kitle ilgisi olmayan satiri ihlal
		// sanar ve kalici kirmiziya kimse bakmaz.
		foreach ( array_merge( $this->scan()['raw'], $this->scan()['unknown'] ) as $hit ) {
			self::assertStringNotContainsString( 'not-a-kit-caller.php', $hit['file'] );
		}
	}

	public function test_an_ambiguous_suffix_lists_EVERY_candidate_concept(): void {
		// 'calendar-alt' hem tohum 'time' hem de tipik bir urun kaydi
		// ('bookings') tarafindan hedeflenebilir. Tek oneri basmak, son
		// yazana gore rastgele cevap vermektir.
		\MHMUiCore\Kit\Icons::register( array( 'bookings' => 'calendar-alt' ) );
		$file = $this->dir() . '/ambiguous.php';
		file_put_contents( $file, "<?php\n\$h = mhmuicore_stat_card_html( array( 'icon' => 'calendar-alt' ) );\n" );

		try {
			$raw = ( new IconConceptScanner( self::ANCHORS ) )->scan( array( $file ) )['raw'];
			self::assertCount( 1, $raw );
			self::assertEqualsCanonicalizing( array( 'time', 'bookings' ), $raw[0]['concepts'] );
		} finally {
			unlink( $file );
			\MHMUiCore\Kit\Icons::reset();
		}
	}
}
