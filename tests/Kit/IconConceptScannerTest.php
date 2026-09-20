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

	public function test_a_data_attribute_is_not_mistaken_for_the_icon_prop(): void {
		// kit-caller.jsx carries `data-icon="not-a-kit-icon-prop"` alongside its
		// real `icon: 'money-alt'` -- the js_icons() lookbehind ( (?<![\w-]) )
		// exists to keep a DOM attribute like this out of every bucket. A
		// distinctive, unregisterable value makes a regression unambiguous: if
		// the lookbehind breaks, this string surfaces as a spurious 'unknown'.
		$result = $this->scan();

		foreach ( array_merge( $result['raw'], $result['unknown'] ) as $hit ) {
			self::assertNotSame( 'not-a-kit-icon-prop', $hit['value'], "data-icon leaked into bucket for {$hit['file']}:{$hit['line']}" );
		}

		// The data attribute must not have moved any of the totals either:
		// same five files, same two concepts, same two raw, same one unknown
		// ('fuel') as before this fixture line existed.
		self::assertSame( 5, $result['files'] );
		self::assertSame( 2, $result['concepts'] );
		self::assertCount( 2, $result['raw'] );
		self::assertCount( 1, $result['unknown'] );
	}

	public function test_a_commented_icon_example_is_never_reported(): void {
		// Codex PR #32 finding, measured 2026-09-20: a bare per-line regex read
		// `// icon: 'money-alt'` -- an explicit "do not write this" example --
		// as a live call site and failed the gate over it. Both comment forms
		// are covered here, each carrying the same decoy value on its own line.
		$file = $this->dir() . '/commented-decoys.jsx';
		file_put_contents(
			$file,
			"mhmuicore_stat_card_html;\n"
				. "// legacy example: icon: 'money-alt' -- kept for reference, do not read this\n"
				. "/*\n"
				. " * another discarded shape:\n"
				. " * icon: 'money-alt'\n"
				. " */\n"
		);

		try {
			$result = ( new IconConceptScanner( self::ANCHORS ) )->scan( array( $file ) );

			self::assertSame( 1, $result['files'], 'the anchor alone still makes it a kit call site' );
			self::assertCount( 0, $result['raw'], 'the // decoy must be silent' );
			self::assertCount( 0, $result['unknown'], 'the /* */ decoy must be silent' );
			self::assertSame( 0, $result['concepts'] );
		} finally {
			unlink( $file );
		}
	}

	public function test_a_real_call_site_survives_next_to_commented_decoys(): void {
		// Positive control for comment-stripping: kit-caller.jsx's real
		// `icon: 'money-alt'` (line 9) sits right after a // comment (line 3)
		// and a MULTI-LINE /* */ block (lines 4-7) that both write the same
		// decoy string. Stripping must silence the decoys WITHOUT blinding the
		// scanner to the real line that follows them, and without shifting its
		// reported line number -- the same failure class this package's own
		// ritual gate hit once before (a probe flagging its own explanation).
		$raw = $this->scan()['raw'];

		$real = array_values(
			array_filter( $raw, static fn( $hit ): bool => str_contains( $hit['file'], 'kit-caller.jsx' ) )
		);

		self::assertCount( 1, $real, 'exactly the one real money-alt call site, not the two commented decoys' );
		self::assertSame( 'money-alt', $real[0]['value'] );
		self::assertContains( 'revenue', $real[0]['concepts'] );
		self::assertSame( 9, $real[0]['line'], 'the line number after a multi-line block comment must still be correct' );
	}

	public function test_a_double_slash_inside_a_string_is_not_mistaken_for_a_comment(): void {
		// 'https://example.com/icons' contains // but is not a comment start.
		// Both on the SAME line as the real call site: a strip that ignores
		// string boundaries treats everything from that // to end-of-line as
		// commentary -- which, on this line, IS the real call site -- and
		// blanks it out. Splitting the URL and the call onto separate lines
		// would not catch that regression: this scanner's line_comment state
		// already resets at every "\n" regardless of string-awareness, so the
		// failure only shows up when both share one line.
		$file = $this->dir() . '/url-then-call.jsx';
		file_put_contents(
			$file,
			"const DOCS_URL = 'https://example.com/icons'; mhmuicore_stat_card_html( { icon: 'revenue' } );\n"
		);

		try {
			$result = ( new IconConceptScanner( self::ANCHORS ) )->scan( array( $file ) );

			self::assertSame( 1, $result['files'] );
			self::assertSame( 1, $result['concepts'], 'the real call site after the URL must still be read' );
			self::assertCount( 0, $result['raw'] );
			self::assertCount( 0, $result['unknown'] );
		} finally {
			unlink( $file );
		}
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
