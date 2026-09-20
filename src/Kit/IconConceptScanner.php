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
 * PurityScanner.php -- because a reviewer greps a shipped tree. (Measured
 * 2026-09-20: that .distignore line for THIS file does not exist yet; adding
 * it is Task 6's job, not this class's.)
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
	 * @return array{raw: array<int, array{file: string, line: int, value: string, concepts: array<int, string>}>, unknown: array<int, array{file: string, line: int, value: string}>, concepts: int, files: int}
	 */
	public function scan( array $paths ): array {
		$map = Icons::map();

		$raw      = array();
		$unknown  = array();
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
				// A variable, a sprintf(), a concatenation: out of reach.
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
	 * `icon: 'value'`, `icon="value"` and `icon={ 'value' }` in JS/JSX. Regex,
	 * not a parser: the package ships no JS parser into a consumer's vendor
	 * tree, and a dynamic prop is out of reach either way.
	 *
	 * @param string $source File contents.
	 * @return array<int, array{line: int, value: string}>
	 */
	private function js_icons( string $source ): array {
		// The lookbehind keeps `data-icon="x"` and `my-icon: 'y'` out: without
		// it the scanner reports a product's own attributes as kit call sites.
		$pattern = '/(?<![\w-])icon\s*(?::\s*|=\s*\{?\s*)[\'"]([^\'"]+)[\'"]/';
		$out     = array();

		foreach ( explode( "\n", $source ) as $index => $line ) {
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
