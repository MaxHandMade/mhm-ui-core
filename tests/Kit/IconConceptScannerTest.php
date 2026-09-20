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

	/** @return array{raw: list<array<string, mixed>>, unknown: list<array<string, mixed>>, failed: list<array<string, mixed>>, concepts: int, files: int} */
	private function scan(): array {
		return ( new IconConceptScanner( self::ANCHORS ) )->scan( array( $this->dir() ) );
	}

	/**
	 * Scan one throwaway source file and delete it again.
	 *
	 * Deliberately NOT a committed fixture: these are pathological JS shapes,
	 * and adding them to tests/Fixtures/icon-concepts would move the tree's
	 * measured 5/2/2/1 totals and composer.json's --expect-raw=2 pin along
	 * with them. The precedent is this file's own
	 * test_an_ambiguous_suffix_lists_EVERY_candidate_concept.
	 *
	 * @param string $name   File name to create inside the fixture directory.
	 * @param string $source Contents to scan.
	 * @return array{raw: list<array<string, mixed>>, unknown: list<array<string, mixed>>, failed: list<array<string, mixed>>, concepts: int, files: int}
	 */
	private function scan_throwaway( string $name, string $source ): array {
		$file = $this->dir() . '/' . $name;
		file_put_contents( $file, $source );

		try {
			return ( new IconConceptScanner( self::ANCHORS ) )->scan( array( $file ) );
		} finally {
			unlink( $file );
		}
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

	public function test_an_escaped_slash_star_in_a_regex_does_not_swallow_the_file(): void {
		// Measured 2026-09-20 on the fix-round-3 tree: `/\/*abc/` is a regex
		// literal, but the comment stripper saw `\/*` and read `/*` as a block
		// comment that never closes -- so the WHOLE REST OF THE FILE was
		// blanked and both real violations below it disappeared. Alone that hit
		// EMPTY-SET; next to any other measuring file the gate exited 0, green
		// and silent. A false negative is the gate lying; a false positive is
		// only the gate nagging.
		$result = $this->scan_throwaway(
			'regex-escaped-slash-star.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "const trim = ( s ) => s.replace( /\\/*abc/, '' );\n"
				. "export const A = () => <StatCard icon={ 'money-alt' } />;\n"
				. "export const B = () => <StatCard icon={ 'calendar-alt' } />;\n"
		);

		self::assertSame( array(), $result['failed'], 'an escaped slash must not be read as an unclosed block comment' );
		self::assertCount( 2, $result['raw'], 'both raw suffixes after the regex literal must still be reported' );
		self::assertSame( 'money-alt', $result['raw'][0]['value'] );
		self::assertSame( 3, $result['raw'][0]['line'] );
		self::assertSame( 'calendar-alt', $result['raw'][1]['value'] );
		self::assertSame( 4, $result['raw'][1]['line'] );
	}

	public function test_an_escaped_slash_pair_in_a_regex_does_not_hide_the_rest_of_its_line(): void {
		// Same root cause, the `//` half of it: in `/https:\/\//` the escaped
		// slashes are NOT a comment start, but a stripper that ignores escapes
		// reads one and blanks the rest of the line -- which here carries the
		// real call site. Both must share ONE line: line_comment state already
		// resets at every "\n", so a split fixture proves nothing (the lesson
		// commit c2bdd6d paid for once already).
		$result = $this->scan_throwaway(
			'regex-escaped-slash-pair.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "const re = /https:\\/\\//; const c = { icon: 'money-alt' };\n"
		);

		self::assertSame( array(), $result['failed'] );
		self::assertCount( 1, $result['raw'], 'the call site sharing a line with the regex must still be read' );
		self::assertSame( 'money-alt', $result['raw'][0]['value'] );
		self::assertSame( 2, $result['raw'][0]['line'] );
	}

	public function test_an_unterminated_block_comment_fails_loudly_instead_of_reporting_clean(): void {
		// The escape rule above fixes two MEASURED shapes; it does not turn
		// this stripper into a JS parser, so the next unknown shape could
		// swallow a file the same way. This is the backstop: reaching EOF
		// inside a block comment is not a clean file, it is an unmeasured one,
		// and the scanner must say which file rather than return a confident
		// zero. Note the real call site on line 2, BEFORE the bad comment --
		// even a partial reading is dropped, because half a measurement
		// reported as a whole one is how a gate learns to lie.
		$result = $this->scan_throwaway(
			'unterminated-block.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "export const E = () => <StatCard icon={ 'money-alt' } />;\n"
				. "/* this block comment is never closed\n"
				. "export const F = () => <StatCard icon={ 'calendar-alt' } />;\n"
		);

		self::assertCount( 1, $result['failed'], 'an unread file must be reported, not silently skipped' );
		self::assertStringContainsString( 'unterminated-block.jsx', $result['failed'][0]['file'] );
		self::assertStringContainsString( 'block comment', $result['failed'][0]['reason'] );
		self::assertSame( 1, $result['files'], 'it is still an anchored file -- it just was not measured' );
		self::assertCount( 0, $result['raw'], 'a partial reading must not be reported as a measurement' );
		self::assertCount( 0, $result['unknown'] );
		self::assertSame( 0, $result['concepts'] );
	}

	public function test_POSITIVE_CONTROL_comments_stay_unscanned_while_real_call_sites_report(): void {
		// Fix round 3's win must survive fix round 4's escape rule: the two
		// commented decoys stay silent, and the real call sites on either side
		// of a regex literal are still both reported, with correct line
		// numbers. Without this, "nothing is reported" would look like a pass
		// for the two tests above.
		$result = $this->scan_throwaway(
			'escapes-and-comments.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "// discarded example: icon: 'money-alt' -- do not write this\n"
				. "/*\n"
				. " * another discarded shape:\n"
				. " * icon: 'calendar-alt'\n"
				. " */\n"
				. "const trim = ( s ) => s.replace( /\\/*abc/, '' );\n"
				. "export const A = () => <StatCard icon={ 'money-alt' } />;\n"
				. "export const B = () => <StatCard icon={ 'revenue' } />;\n"
		);

		self::assertSame( array(), $result['failed'] );
		self::assertCount( 1, $result['raw'], 'exactly the one real money-alt, not the two commented decoys' );
		self::assertSame( 'money-alt', $result['raw'][0]['value'] );
		self::assertSame( 8, $result['raw'][0]['line'], 'line numbers survive both the block comment and the regex' );
		self::assertCount( 0, $result['unknown'] );
		self::assertSame( 1, $result['concepts'], "the 'revenue' call site on the last line" );
	}

	public function test_an_unescaped_slash_star_in_a_regex_does_not_swallow_what_follows(): void {
		// Fix round 4 left this one open and said so. `/[/*]/` carries no
		// escape for the escape rule to catch, so its `/*` opened a block
		// comment -- and, worse than the escaped shapes, an ORDINARY well-formed
		// comment further down closed it again, so the unclosed-block guard
		// never fired either. Measured on commit dc5262a, this exact file
		// reported `1 file(s), 1 concept call site(s), 0 raw, 0 unknown`,
		// exit 0: a real money-alt violation swallowed in silence, between two
		// perfectly innocent lines.
		$result = $this->scan_throwaway(
			'regex-char-class.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "const re = /[/*]/;\n"
				. "export const A = () => <StatCard icon={ 'money-alt' } />;\n"
				. "/* an ordinary, properly closed comment */\n"
				. "export const B = () => <StatCard icon={ 'revenue' } />;\n"
		);

		self::assertSame( array(), $result['failed'], 'the `/*` inside a character class must not open a comment at all' );
		self::assertCount( 1, $result['raw'], 'the violation between the regex and the real comment must be reported' );
		self::assertSame( 'money-alt', $result['raw'][0]['value'] );
		self::assertSame( 3, $result['raw'][0]['line'] );
		self::assertSame( 1, $result['concepts'], "and 'revenue' after the real comment is still read" );
	}

	public function test_POSITIVE_CONTROL_a_comment_in_every_position_it_is_written_stays_unscanned(): void {
		// The narrowing in opens_a_comment() buys the test above by refusing to
		// see a comment after `[`, `(`, `=`, a letter or a digit. It must not
		// pay for it by losing fix round 3's win, so all THREE allowed
		// positions are pinned here in one file, each carrying the same decoy:
		// line start, after whitespace, and glued to `;` / `}`. If the
		// narrowing is ever written too tightly, this test reports decoys.
		$result = $this->scan_throwaway(
			'comment-positions.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "// line start: icon: 'money-alt'\n"
				. "const a = 1;   // after whitespace: icon: 'money-alt'\n"
				. "const b = 2;/* glued to a semicolon: icon: 'money-alt' */\n"
				. "function f() { return 3; }/* glued to a brace: icon: 'money-alt' */\n"
				. "export const A = () => <StatCard icon={ 'revenue' } />;\n"
		);

		self::assertSame( array(), $result['failed'] );
		self::assertCount( 0, $result['raw'], 'all four commented decoys must stay silent' );
		self::assertCount( 0, $result['unknown'] );
		self::assertSame( 1, $result['concepts'], 'and the one real call site is still reported' );
	}

	public function test_an_unrecognised_comment_position_is_SCANNED_not_swallowed(): void {
		// The deliberate cost of the narrowing, written down as a measurement
		// rather than left as a surprise: a block comment glued to `(` is not
		// recognised, so its contents are scanned and an icon literal inside it
		// surfaces as a finding. That is a NOISY FALSE POSITIVE -- the failure
		// direction this whole slice is built to prefer. The silent false
		// negative it replaced (the test above) is the one a gate must never
		// have. The `(` form with a space after it is NOT affected: whitespace
		// precedes the slash, so the common inline shape still strips.
		$glued = $this->scan_throwaway(
			'glued-inline-comment.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "f(/* icon: 'money-alt' */ 1);\n"
		);
		self::assertCount( 1, $glued['raw'], 'an unrecognised comment position is read as code -- noisy, never silent' );

		$spaced = $this->scan_throwaway(
			'spaced-inline-comment.jsx',
			"import { StatCard } from 'ui-core/src-react/components/Stat';\n"
				. "f( /* icon: 'money-alt' */ 1 );\n"
		);
		self::assertCount( 0, $spaced['raw'], 'the same comment with a space after ( is still stripped' );
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
