<?php
/**
 * The convergence gate for the kit's icon vocabulary.
 *
 * @package MHMUiCore\Kit
 */

declare(strict_types=1);

namespace MHMUiCore\Kit;

/**
 * Sorts the `icon` values at a consumer's kit call sites into three buckets:
 * concept (clean), raw suffix that HAS a concept (a finding), and unknown
 * suffix (listed, not a failure: probably a domain concept to register).
 *
 * WHY THIS IS A CLASS IN src/ AND NOT A SCRIPT IN bin/
 * bin/ is export-ignored and ShippedSurfaceTest refuses to let it ship, so a
 * consumer's vendor/mhm/ui-core never contains it. A gate meant to run in the
 * CONSUMER's CI has to be a shipped class. It calls no WordPress function, so
 * a plain `require` of Icons.php then this file is enough -- no autoloader.
 *
 * This file is development tooling, not runtime code, and a free core that
 * publishes to WordPress.org MUST exclude it from its ZIP with a .distignore
 * line -- the same pattern already applied there to src/Cli/ and
 * PurityScanner.php -- because a reviewer greps a shipped tree. Task 6
 * (2026-09-20) added that row to README.md's / README-tr.md's "What a free
 * core must keep out of its ZIP" table, the authoritative list a consumer's
 * own .distignore is derived from -- this repo ships no .distignore of its
 * own, since it is the package, not a WordPress.org plugin.
 *
 * WHY CALL SITES ARE FOUND BY ANCHOR AND NOT BY 'icon' ALONE
 * Measured 2026-09-20 in Rentiva: three different vocabularies write
 * `'icon' => ...` in one tree -- kit cards, the product's own inline-SVG
 * feature icons, and its dashicons-prefixed button helper. A scanner that
 * reads every `'icon'` reports the other two as violations, and a permanently
 * red gate is one nobody reads. Anchors are the caller's to declare: measured
 * the same day, this package's own function names find ZERO call sites in
 * Rentiva, because every one of them goes through a product wrapper
 * (AssetManager::stats_grid_html, ProKit).
 *
 * WHAT THIS CANNOT SEE -- a clean run does NOT mean "no raw suffixes exist":
 * an icon name in a variable; one built with sprintf(); a dynamic JSX prop
 * ( icon={ x } ); a multi-line object literal whose value sits on the next
 * line; a template literal; a quoted key ( 'icon': 'x' ); and every call site
 * in a file that mentions no anchor. A value built with `.` CONCATENATION is
 * NOT silently skipped -- measured 2026-09-20: `'icon' => 'money' . '-alt'`
 * reads only the first operand ( T_CONSTANT_ENCAPSED_STRING immediately after
 * the arrow, see php_icons() ) and reports 'money' as an UNKNOWN suffix, a
 * false "register it as a concept" suggestion that --expect-raw's count does
 * not catch because it only counts the raw bucket.
 *
 * A JS REGEX LITERAL IS NOT PARSED AS ONE -- AND THE GATE SAYS SO OUT LOUD
 * strip_js_comments() has no notion of a regex literal: it cannot tell
 * `/\/*abc/` from a division sign followed by the start of a block comment.
 * Measured 2026-09-20, before the escape rule existed: in a file holding
 * `s.replace( /\/*abc/, '' )` and two real raw suffixes, the `\/*` opened a
 * block comment that never closed, the whole rest of the file was blanked, and
 * BOTH real violations vanished -- alone the run hit EMPTY-SET, but paired
 * with any other measuring file it exited 0, green and silent. The same
 * happened to the rest of a line holding `/https:\/\//`. Both are closed now
 * by honouring a backslash escape while in code state, so an escaped slash
 * cannot open a comment. That is a patch on a symptom, not a JS parser: the
 * next unparsed shape could swallow a file the same way. So the machine no
 * longer returns a swallowed file's text at all -- reaching EOF inside a block
 * comment yields NULL, which scan() records in the 'failed' bucket and the CLI
 * prints as MEASURE-FAILED, naming the file, exit 2. A gate may fail to
 * understand a structure; it may not turn that into a silent pass.
 *
 * COMMENTS ARE NOT SCANNED, ON EITHER SIDE
 * php_icons() was already immune to this: token_get_all() gives a `//` or
 * `/* *\/` comment its own T_COMMENT token, never a T_CONSTANT_ENCAPSED_STRING,
 * so a commented-out `'icon' => 'money-alt'` was never reachable there. The JS
 * half had NO such immunity until this fix (Codex PR #32, measured
 * 2026-09-20): a bare per-line regex over the raw source read
 * `// icon: 'money-alt'` -- an explicit "do not write this" example -- as a
 * live call site and failed the gate over it. js_icons() now runs the source
 * through strip_js_comments() first, a small state machine that blanks
 * comment text (both forms) while leaving every string literal and every
 * newline untouched -- so a `//` inside a URL string is not mistaken for a
 * comment, and a hit's reported line number still matches the file on disk.
 */
final class IconConceptScanner {

	/**
	 * Extensions this scanner reads.
	 *
	 * @var array<int, string>
	 */
	private const EXTENSIONS = array( 'php', 'js', 'jsx' );

	/**
	 * Substrings that mark a file as a kit call site.
	 *
	 * @var array<int, string>
	 */
	private array $anchors;

	/**
	 * Build a scanner for a given set of anchors.
	 *
	 * @param array<int, mixed> $anchors Substrings that mark a file as a kit call site.
	 */
	public function __construct( array $anchors ) {
		$this->anchors = array_values(
			array_filter( $anchors, static fn( $a ): bool => is_string( $a ) && '' !== $a )
		);
	}

	/**
	 * Scan the given paths and sort every `icon` value found into buckets.
	 *
	 * @param array<int, string> $paths Files or directories to scan.
	 * @return array{raw: array<int, array{file: string, line: int, value: string, concepts: array<int, string>}>, unknown: array<int, array{file: string, line: int, value: string}>, failed: array<int, array{file: string, reason: string}>, concepts: int, files: int}
	 */
	public function scan( array $paths ): array {
		$map = Icons::map();

		$raw      = array();
		$unknown  = array();
		$failed   = array();
		$concepts = 0;
		$files    = 0;

		foreach ( $this->files( $paths ) as $file ) {
			$source = $this->read( $file );

			if ( ! $this->is_kit_call_site( $source ) ) {
				continue;
			}
			++$files;

			$extension = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
			$icons     = ( 'php' === $extension ) ? $this->php_icons( $source ) : $this->js_icons( $source );

			if ( null === $icons ) {
				// This file was not measured. Its partial hits are dropped on
				// purpose: half a reading reported as a whole one is how a gate
				// learns to lie.
				$failed[] = array(
					'file'   => $file,
					'reason' => 'a block comment is never closed -- everything after it was swallowed, so this file was not measured',
				);
				continue;
			}

			foreach ( $icons as $hit ) {
				if ( isset( $map[ $hit['value'] ] ) ) {
					++$concepts;
					continue;
				}

				// EVERY concept that targets this suffix, not just one: two
				// concepts may map to the same icon, and naming whichever came
				// last is a random answer dressed as advice.
				$candidates = array_keys( $map, $hit['value'], true );

				if ( array() !== $candidates ) {
					$raw[] = array(
						'file'     => $file,
						'line'     => $hit['line'],
						'value'    => $hit['value'],
						'concepts' => $candidates,
					);
					continue;
				}

				$unknown[] = array(
					'file'  => $file,
					'line'  => $hit['line'],
					'value' => $hit['value'],
				);
			}
		}

		return array(
			'raw'      => $raw,
			'unknown'  => $unknown,
			'failed'   => $failed,
			'concepts' => $concepts,
			'files'    => $files,
		);
	}

	/**
	 * Read a source file.
	 *
	 * Uses file_get_contents() rather than WP_Filesystem (WordPress.WP.
	 * AlternativeFunctions): this class must run under plain PHP in a
	 * consumer's CI, where WordPress is not loaded at all.
	 *
	 * @param string $file Absolute path.
	 */
	private function read( string $file ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- runs outside WordPress, see method docblock.
		return (string) file_get_contents( $file );
	}

	/**
	 * Walk the given files or directories and keep only scannable extensions.
	 *
	 * @param array<int, string> $paths Files or directories.
	 * @return array<int, string> Paths with a scannable extension.
	 */
	private function files( array $paths ): array {
		$out = array();

		foreach ( $paths as $path ) {
			if ( is_file( $path ) ) {
				$out[] = $path;
				continue;
			}
			if ( ! is_dir( $path ) ) {
				continue;
			}

			$walker = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $walker as $entry ) {
				if ( $entry->isFile() ) {
					$out[] = $entry->getPathname();
				}
			}
		}

		return array_values(
			array_filter(
				$out,
				static fn( $f ): bool => in_array(
					strtolower( (string) pathinfo( $f, PATHINFO_EXTENSION ) ),
					self::EXTENSIONS,
					true
				)
			)
		);
	}

	/**
	 * Whether the source mentions any of this scanner's anchors.
	 *
	 * @param string $source File contents.
	 */
	private function is_kit_call_site( string $source ): bool {
		foreach ( $this->anchors as $anchor ) {
			if ( false !== strpos( $source, $anchor ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * `'icon' => 'value'` pairs, read from TOKENS rather than raw text: a
	 * docblock that explains this rule must not be mistaken for the rule being
	 * broken.
	 *
	 * @param string $source File contents.
	 * @return array<int, array{line: int, value: string}>
	 */
	private function php_icons( string $source ): array {
		$tokens = token_get_all( $source );
		$count  = count( $tokens );
		$out    = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! $this->is_icon_key( $tokens[ $i ] ) ) {
				continue;
			}

			$arrow = $this->next_meaningful( $tokens, $i + 1 );
			if ( ( null === $arrow ) || ( ! is_array( $tokens[ $arrow ] ) ) || ( T_DOUBLE_ARROW !== $tokens[ $arrow ][0] ) ) {
				continue;
			}

			$value = $this->next_meaningful( $tokens, $arrow + 1 );
			if ( ( null === $value ) || ( ! is_array( $tokens[ $value ] ) ) || ( T_CONSTANT_ENCAPSED_STRING !== $tokens[ $value ][0] ) ) {
				// A variable or a sprintf() result: genuinely out of reach here.
				// NOT a concatenation -- `'money' . '-alt'` does not land in this
				// branch: the first operand IS a T_CONSTANT_ENCAPSED_STRING, so
				// it is taken as the value below and misfiled as an 'unknown'
				// suffix. See the class docblock's WHAT THIS CANNOT SEE note.
				continue;
			}

			$out[] = array(
				'line'  => (int) $tokens[ $value ][2],
				'value' => trim( $tokens[ $value ][1], "'\"" ),
			);
		}

		return $out;
	}

	/**
	 * Whether a token is the string literal `'icon'` (or `"icon"`).
	 *
	 * @param array{0: int, 1: string, 2: int}|string $token One token_get_all() entry.
	 */
	private function is_icon_key( $token ): bool {
		if ( ( ! is_array( $token ) ) || ( T_CONSTANT_ENCAPSED_STRING !== $token[0] ) ) {
			return false;
		}

		return ( "'icon'" === $token[1] ) || ( '"icon"' === $token[1] );
	}

	/**
	 * The index of the next non-whitespace, non-comment token.
	 *
	 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens Token list.
	 * @param int                                                 $from   Index to start at.
	 */
	private function next_meaningful( array $tokens, int $from ): ?int {
		$count = count( $tokens );
		$skip  = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );

		for ( $i = $from; $i < $count; $i++ ) {
			if ( ( ! is_array( $tokens[ $i ] ) ) || ( ! in_array( $tokens[ $i ][0], $skip, true ) ) ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Strip `//` and `/* *\/` comments from JS/JSX source before the regex in
	 * js_icons() ever sees it.
	 *
	 * A character-by-character state machine, not a strip-first regex: it
	 * tracks single-, double- and backtick-quoted strings so a `//` inside a
	 * URL literal is never mistaken for a comment start, it honours a
	 * backslash escape in CODE state so a regex literal's escaped slash cannot
	 * open a comment, and it replaces comment TEXT with spaces rather than
	 * deleting it, so every newline survives and a hit's reported line number
	 * still matches the untouched file. Returns NULL, never a string, when the
	 * source ends while still inside a block comment -- see the bottom of the
	 * method. Measured 2026-09-20 (Codex PR #32): without this, a discarded
	 * example left in a `//` comment -- `// icon: 'money-alt'` -- was read as
	 * a live call site and reported RAW, punishing the exact "do not write
	 * this" comment it was written to prevent. php_icons() never had this
	 * blind spot: token_get_all() already treats T_COMMENT as its own token,
	 * never as a T_CONSTANT_ENCAPSED_STRING; this brings the JS half in line
	 * with the PHP half's existing immunity, instead of leaving the two twins
	 * disagreeing about what a comment is.
	 *
	 * @param string $source JS/JSX file contents.
	 */
	private function strip_js_comments( string $source ): ?string {
		$out    = '';
		$length = strlen( $source );
		$state  = 'normal';
		$quote  = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $source[ $i ];
			$next = ( $i + 1 < $length ) ? $source[ $i + 1 ] : '';

			if ( 'normal' === $state ) {
				if ( ( '\\' === $char ) && ( '' !== $next ) ) {
					// An escape in CODE state, which is where a regex literal's
					// body lives: consume both characters so the escaped slash
					// in /\/*abc/ or /https:\/\// can never open a comment.
					$out .= $char . $next;
					++$i;
					continue;
				}
				if ( ( '/' === $char ) && ( '/' === $next ) ) {
					$state = 'line_comment';
					$out  .= '  ';
					++$i;
					continue;
				}
				if ( ( '/' === $char ) && ( '*' === $next ) ) {
					$state = 'block_comment';
					$out  .= '  ';
					++$i;
					continue;
				}
				if ( ( "'" === $char ) || ( '"' === $char ) || ( '`' === $char ) ) {
					$state = 'string';
					$quote = $char;
				}
				$out .= $char;
				continue;
			}

			if ( 'string' === $state ) {
				if ( ( '\\' === $char ) && ( '' !== $next ) ) {
					$out .= $char . $next;
					++$i;
					continue;
				}
				if ( $char === $quote ) {
					$state = 'normal';
				}
				$out .= $char;
				continue;
			}

			if ( 'line_comment' === $state ) {
				if ( "\n" === $char ) {
					$state = 'normal';
					$out  .= $char;
					continue;
				}
				$out .= ' ';
				continue;
			}

			// 'block_comment' state.
			if ( ( '*' === $char ) && ( '/' === $next ) ) {
				$state = 'normal';
				$out  .= '  ';
				++$i;
				continue;
			}
			$out .= ( "\n" === $char ) ? $char : ' ';
		}

		// Reaching EOF still inside a block comment means this machine read a
		// structure it does not understand -- most likely something that is not
		// a comment at all. Everything from that point on was blanked, so the
		// rest of the file was NOT measured. Returning the blanked text here
		// would hand js_icons() a silent, confident zero: the gate's worst
		// possible answer. Null is the signal; scan() turns it into a named
		// entry in the 'failed' bucket and the CLI into MEASURE-FAILED + exit 2.
		return ( 'block_comment' === $state ) ? null : $out;
	}

	/**
	 * `icon: 'value'`, `icon="value"` and `icon={ 'value' }` in JS/JSX. Regex,
	 * not a parser: the package ships no JS parser into a consumer's vendor
	 * tree, and a dynamic prop is out of reach either way.
	 *
	 * Returns null when strip_js_comments() could not finish the file -- NOT an
	 * empty array, which would read as "scanned, found nothing".
	 *
	 * @param string $source File contents.
	 * @return array<int, array{line: int, value: string}>|null
	 */
	private function js_icons( string $source ): ?array {
		$stripped = $this->strip_js_comments( $source );

		if ( null === $stripped ) {
			return null;
		}

		// The lookbehind keeps `data-icon="x"` and `my-icon: 'y'` out: without
		// it the scanner reports a product's own attributes as kit call sites.
		$pattern = '/(?<![\w-])icon\s*(?::\s*|=\s*\{?\s*)[\'"]([^\'"]+)[\'"]/';
		$out     = array();

		foreach ( explode( "\n", $stripped ) as $index => $line ) {
			if ( 0 === preg_match_all( $pattern, $line, $matches ) ) {
				continue;
			}

			foreach ( $matches[1] as $value ) {
				$out[] = array(
					'line'  => $index + 1,
					'value' => $value,
				);
			}
		}

		return $out;
	}
}
