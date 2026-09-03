<?php
/**
 * The core purity gate: proves a free core carries none of the three forbidden things.
 *
 * @package MHMUiCore\Seam
 */

declare(strict_types=1);

namespace MHMUiCore\Seam;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * "The free core will contain no artificial limit, no licence code and no
 * outbound HTTP." The design document lists this gate as missing; this is it.
 *
 * In May 2026 a human ran this audit by hand and every pass found a new leak.
 * A machine pass is not smarter, but it is REPEATABLE and it is CHEAP, so it
 * runs on every commit instead of once before a submission. Like every gate in
 * this package it tests its own blind spot before it scans (self_test()): a
 * scanner that would wave the known-bad fixture through reports itself broken
 * rather than reporting the tree clean.
 *
 * Shipped in src/, not bin/, on purpose: this gate is for CONSUMERS' free cores
 * and must reach their vendor/ tree.
 */
final class PurityScanner {

	public const CLASS_HTTP    = 'outbound_http';
	public const CLASS_LICENSE = 'license_code';
	public const CLASS_LIMIT   = 'artificial_limit';

	/**
	 * Function calls that reach outside the site. Matched on T_STRING followed by "(".
	 *
	 * @var list<string>
	 */
	private const HTTP_CALLS = array(
		'wp_remote_get',
		'wp_remote_post',
		'wp_remote_request',
		'wp_remote_head',
		'wp_safe_remote_get',
		'wp_safe_remote_post',
		'wp_safe_remote_request',
		'curl_init',
		'curl_exec',
		'fsockopen',
		'stream_socket_client',
	);

	/**
	 * Identifiers that only exist to check or store a licence. Matched as whole
	 * words inside T_STRING and T_VARIABLE tokens and inside string literals.
	 *
	 * @var list<string>
	 */
	private const LICENSE_WORDS = array(
		'license_key',
		'licence_key',
		'activate_license',
		'deactivate_license',
		'check_license',
		'validate_license',
		'license_status',
		'is_licensed',
		'edd_sl',
		'license_server',
	);

	/**
	 * Identifiers whose only purpose is to cap the free tier. Matched as whole
	 * words, same places as LICENSE_WORDS.
	 *
	 * @var list<string>
	 */
	private const LIMIT_WORDS = array(
		'free_limit',
		'free_tier_limit',
		'max_free',
		'upgrade_to_pro',
		'pro_only',
		'requires_pro',
		'is_pro_active',
		'pro_locked',
	);

	/**
	 * Directory names never scanned.
	 *
	 * @var list<string>
	 */
	private const SKIP_DIRS = array( 'vendor', 'node_modules', 'tests', '.git' );

	/**
	 * Scan a tree for the three forbidden classes.
	 *
	 * @param string $dir Directory to scan.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 *         Empty when clean. Never includes the matched line's content.
	 */
	public function scan( string $dir ): array {
		$violations = array();
		foreach ( $this->php_files( $dir ) as $path ) {
			$source = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file read in a gate, not a remote fetch.
			if ( false === $source ) {
				continue;
			}
			foreach ( $this->scan_source( $source ) as $hit ) {
				$hit['file']  = $path;
				$violations[] = $hit;
			}
		}
		return $violations;
	}

	/**
	 * Scan one PHP source string.
	 *
	 * @param string $source PHP source.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 */
	public function scan_source( string $source ): array {
		$hits   = array();
		$tokens = token_get_all( $source );
		$count  = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}

			list( $id, $text, $line ) = $token;

			if ( T_STRING === $id ) {
				$lower = strtolower( $text );
				if ( in_array( $lower, self::HTTP_CALLS, true ) && $this->is_call( $tokens, $i ) ) {
					$hits[] = array(
						'class' => self::CLASS_HTTP,
						'file'  => '',
						'line'  => $line,
						'name'  => $lower,
					);
					continue;
				}
				$hits = array_merge( $hits, $this->word_hits( $lower, $line ) );
			} elseif ( T_VARIABLE === $id ) {
				$hits = array_merge( $hits, $this->word_hits( strtolower( ltrim( $text, '$' ) ), $line ) );
			} elseif ( T_CONSTANT_ENCAPSED_STRING === $id ) {
				$hits = array_merge( $hits, $this->word_hits( strtolower( trim( $text, '\'"' ) ), $line ) );
			}
		}

		return $hits;
	}

	/**
	 * Match licence and limit vocabulary inside one identifier or literal.
	 *
	 * @param string $word Lowercased token text.
	 * @param int    $line Line number.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 */
	private function word_hits( string $word, int $line ): array {
		$hits = array();
		foreach ( self::LICENSE_WORDS as $needle ) {
			if ( $this->contains_word( $word, $needle ) ) {
				$hits[] = array(
					'class' => self::CLASS_LICENSE,
					'file'  => '',
					'line'  => $line,
					'name'  => $needle,
				);
			}
		}
		foreach ( self::LIMIT_WORDS as $needle ) {
			if ( $this->contains_word( $word, $needle ) ) {
				$hits[] = array(
					'class' => self::CLASS_LIMIT,
					'file'  => '',
					'line'  => $line,
					'name'  => $needle,
				);
			}
		}
		return $hits;
	}

	/**
	 * Whole-word containment: "license_key" matches "get_license_key" and
	 * "license_key_field" but not "licensed_keys".
	 *
	 * @param string $haystack Token text.
	 * @param string $needle   Vocabulary word.
	 * @return bool
	 */
	private function contains_word( string $haystack, string $needle ): bool {
		return 1 === preg_match( '/(^|[^a-z0-9])' . preg_quote( $needle, '/' ) . '([^a-z0-9]|$)/', $haystack );
	}

	/**
	 * Whether the T_STRING at $i is followed by "(" (a call, not a mention).
	 *
	 * @param list<array{0:int,1:string,2:int}|string> $tokens Tokens.
	 * @param int                                      $i      Index.
	 * @return bool
	 */
	private function is_call( array $tokens, int $i ): bool {
		for ( $j = $i + 1, $n = count( $tokens ); $j < $n; $j++ ) {
			$t = $tokens[ $j ];
			if ( is_string( $t ) ) {
				return '(' === $t;
			}
			if ( ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				return false;
			}
		}
		return false;
	}

	/**
	 * PHP files under a directory, skipping vendor/, node_modules/, tests/ and .git/.
	 *
	 * @param string $dir Root.
	 * @return list<string>
	 */
	private function php_files( string $dir ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$files    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $entry ) {
			if ( ! $entry instanceof SplFileInfo || ! $entry->isFile() || 'php' !== strtolower( $entry->getExtension() ) ) {
				continue;
			}
			$relative = str_replace( '\\', '/', substr( $entry->getPathname(), strlen( $dir ) ) );
			$skip     = false;
			foreach ( self::SKIP_DIRS as $skip_dir ) {
				if ( false !== strpos( $relative, '/' . $skip_dir . '/' ) ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip ) {
				$files[] = $entry->getPathname();
			}
		}

		sort( $files );
		return $files;
	}

	/**
	 * Prove the scanner sees what it claims to see, before trusting a clean run.
	 *
	 * Positive fixtures must each produce exactly their class; the negative
	 * fixture must produce nothing. Returns the list of failed expectations,
	 * empty when the scanner is sound.
	 *
	 * @return list<string>
	 */
	public function self_test(): array {
		$failures = array();

		$positives = array(
			self::CLASS_HTTP    => "<?php\n\$r = wp_remote_get( 'https://example.com' );\n",
			self::CLASS_LICENSE => "<?php\nfunction check_license( \$key ) { return true; }\n",
			self::CLASS_LIMIT   => "<?php\nif ( \$count > FREE_LIMIT ) { return; }\n",
		);
		foreach ( $positives as $class => $source ) {
			$classes = array_column( $this->scan_source( $source ), 'class' );
			if ( ! in_array( $class, $classes, true ) ) {
				$failures[] = 'positive fixture not caught: ' . $class;
			}
		}

		$negative = "<?php\n"
			. "// wp_remote_get is mentioned in a comment only.\n"
			. "\$licensed_keys = get_option( 'keys' );\n"
			. "\$max = 10;\n"
			. "function fetch_local() { return file_get_contents( __DIR__ . '/x.json' ); }\n";
		$hits     = $this->scan_source( $negative );
		if ( array() !== $hits ) {
			$failures[] = 'negative fixture tripped: ' . implode( ',', array_column( $hits, 'name' ) );
		}

		return $failures;
	}
}
