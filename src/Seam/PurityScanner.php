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
	 * Not a forbidden thing: a place the gate could not read or could not decide.
	 *
	 * "I could not look" and "I looked and it was clean" are different answers,
	 * and a purity run may end on only one of them. Reporting the first as the
	 * second is the failure this whole class exists to make impossible.
	 */
	public const CLASS_UNMEASURABLE = 'unmeasurable';

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
		'wp_safe_remote_head',
		'fsockopen',
		'stream_socket_client',
	);

	/**
	 * Calls that reach outside only when an absolute URL is handed to them.
	 *
	 * `wp_enqueue_script( 'x', 'https://cdn…' )` pulls a script from somebody
	 * else's server on every page load -- the shape WP.org rejects submissions
	 * for, and the shape a consumer's own gate already hunts. `file_get_contents`
	 * is `fsockopen`'s sibling the moment its argument has a scheme.
	 *
	 * Evidence only, and that is the point: these functions exist to read local
	 * files and enqueue local assets. Reporting them on the ABSENCE of a URL would
	 * make every enqueue in every plugin a finding, and a gate nobody can read is
	 * a gate nobody reads.
	 *
	 * @var list<string>
	 */
	private const URL_CARRYING_CALLS = array(
		'wp_enqueue_script',
		'wp_enqueue_style',
		'wp_register_script',
		'wp_register_style',
		'download_url',
		'wp_remote_fopen',
		'file_get_contents',
		'fopen',
		'get_headers',
		'simplexml_load_file',
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
	 * Extensions read as PHP.
	 *
	 * @var list<string>
	 */
	private const PHP_EXTENSIONS = array( 'php' );

	/**
	 * Extensions read as JavaScript. A free core's browser half is shipped code
	 * like any other: leaving it unscanned is what made the gate's own promise
	 * false while it reported clean.
	 *
	 * @var list<string>
	 */
	private const JS_EXTENSIONS = array( 'js', 'jsx', 'mjs', 'cjs', 'ts', 'tsx' );

	/**
	 * Ways a browser reaches the network, and how hard the gate presses each one.
	 *
	 * `strict` shapes are requests: a target the gate cannot resolve is reported
	 * as unmeasurable, because a request whose destination is unknown is exactly
	 * what a purity claim may not stay silent about. The rest are sinks -- a URL
	 * assigned to `src`, `action` or `location` -- reported only on the evidence
	 * of an absolute URL, since `img.src = imageUrl` is ordinary code and a gate
	 * that shouts at it gets switched off, which helps nobody.
	 *
	 * `window` says where the decision is read: `call` means the call's own
	 * argument list, `assignment` means the right-hand side up to the end of the
	 * statement. Neither is ever the block around the call -- that window made the
	 * verdict depend on unrelated strings in the enclosing callback.
	 *
	 * `arg` is which argument carries the target: `xhr.open( 'POST', url )` and
	 * `setAttribute( 'src', url )` both put it second.
	 *
	 * @var list<array{name:string, pattern:string, strict:bool, window:string, arg:int}>
	 */
	private const JS_OUTBOUND = array(
		array(
			'name'    => 'fetch',
			'pattern' => '/\bfetch\s*(\?\.)?\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'sendBeacon',
			'pattern' => '/\bsendBeacon\s*\(|\[\s*[\'\"]sendBeacon[\'\"]\s*\]\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'XMLHttpRequest.open',
			'pattern' => '/\.\s*open\s*\(\s*[\'\"](?:GET|POST|PUT|PATCH|DELETE|HEAD)[\'\"]/i',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 1,
		),
		array(
			'name'    => 'axios',
			'pattern' => '/\baxios\s*(?:\.\s*(?:get|post|put|patch|delete|head|request)\s*)?\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'jQuery.ajax',
			'pattern' => '/(?:\$|jQuery)\s*\.\s*(?:get|post|getJSON|ajax)\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'WebSocket',
			'pattern' => '/\bnew\s+WebSocket\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'EventSource',
			'pattern' => '/\bnew\s+EventSource\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'importScripts',
			'pattern' => '/\bimportScripts\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'import()',
			'pattern' => '/\bimport\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'window.open',
			'pattern' => '/\bwindow\s*\.\s*open\s*\(/',
			'strict'  => true,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'location',
			'pattern' => '/\blocation\s*(?:\.\s*(?:href|assign|replace)\s*)?=(?!=)/',
			'strict'  => false,
			'window'  => 'assignment',
			'arg'     => 0,
		),
		array(
			'name'    => 'location',
			'pattern' => '/\blocation\s*\.\s*(?:assign|replace)\s*\(/',
			'strict'  => false,
			'window'  => 'call',
			'arg'     => 0,
		),
		array(
			'name'    => 'src/action',
			'pattern' => '/(?:^|[\s.])(?:src|action)\s*=(?!=)/',
			'strict'  => false,
			'window'  => 'assignment',
			'arg'     => 0,
		),
		array(
			'name'    => 'src/action',
			'pattern' => '/\[\s*[\'\"](?:src|action|href)[\'\"]\s*\]\s*=(?!=)/',
			'strict'  => false,
			'window'  => 'assignment',
			'arg'     => 0,
		),
		array(
			'name'    => 'setAttribute',
			'pattern' => '/\bsetAttribute\s*\(\s*[\'\"](?:src|action)[\'\"]/',
			'strict'  => false,
			'window'  => 'call',
			'arg'     => 1,
		),
		array(
			'name'    => 'XMLHttpRequest.open',
			'pattern' => '/\.\s*open\s*\(/',
			'strict'  => false,
			'window'  => 'call',
			'arg'     => 1,
		),
	);

	/**
	 * Longest line this gate will read as source.
	 *
	 * A generated bundle puts a whole module graph on one line. Every call and
	 * every URL in the file then share a statement window, so an unrelated local
	 * `fetch(` and an unrelated "http://www.w3.org/2000/svg" become an outbound
	 * call -- an audit found precisely that in two real bundles -- while mangled
	 * identifiers make the vocabulary half worthless at the same time. Neither
	 * finding nor silence is true of such a file, so it is reported as what it is.
	 *
	 * The threshold is generous on purpose: a block icon's `<path d="…">` and a
	 * base64 data URI are ordinary long lines in hand-written source, and calling
	 * one of those generated means the file is never read at all.
	 */
	private const MAX_SOURCE_LINE = 2000;

	/**
	 * A binding whose value is a plain string literal: name in 1, literal in 2.
	 */
	private const JS_BINDING_PATTERN = '/\b(?:const|let|var)\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*([\'"`][^\'"`]*[\'"`])/';

	/**
	 * A quoted target on this site: "/x", "./x" or "../x", never "//host".
	 */
	private const JS_LOCAL_TARGET_PATTERN = '#[\'"`]\s*(\.\.?)?/(?!/)#';

	/**
	 * A global WordPress itself defines to point at this site.
	 *
	 * Only these two. `ajax_url`, `rest_url`, `restUrl` and `admin_url` look like
	 * the same thing but are names a PLUGIN chooses for its own
	 * `wp_localize_script` payload -- this package's own consumer defines all four
	 * -- and reading them as WordPress-filled let an audit point one at an external
	 * telemetry host and still get a clean run.
	 */
	private const JS_SITE_GLOBAL_PATTERN = '/\b(?:ajaxurl|wpApiSettings)\b/';

	/**
	 * The property of an option object that names where the call goes.
	 */
	private const JS_URL_PROPERTY_PATTERN = '/\b(?:url|uri|path|baseURL|src|endpoint)\s*:\s*(.+)$/is';

	/**
	 * A quoted absolute URL. Schemes are named because a socket does not speak
	 * http: `wss://relay.example.com` is as outbound as anything with a scheme,
	 * and a protocol-relative "//host" carries no scheme at all.
	 */
	private const JS_ABSOLUTE_URL_PATTERN = '#[\'"`]\s*(?:(?:https?|wss?):)?//[^\'"`\s]#i';

	/**
	 * Scan a tree for the three forbidden classes.
	 *
	 * @param string $dir Directory to scan.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 *         Empty when clean. Never includes the matched line's content.
	 */
	public function scan( string $dir ): array {
		$violations = array();

		foreach ( $this->scannable_files( $dir ) as $path ) {
			$violations = array_merge( $violations, $this->scan_file( $path ) );
		}

		return $violations;
	}

	/**
	 * Scan one file, choosing the reader from its extension.
	 *
	 * An unreadable file is reported, never swallowed: an empty string would
	 * scan clean and the run would end on a finding that was never measured.
	 *
	 * @param string $path File path.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 */
	public function scan_file( string $path ): array {
		$source = is_readable( $path )
			? file_get_contents( $path ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file read in a gate, not a remote fetch.
			: false;

		if ( false === $source ) {
			return array(
				array(
					'class' => self::CLASS_UNMEASURABLE,
					'file'  => $path,
					'line'  => 0,
					'name'  => 'unreadable',
				),
			);
		}

		$is_js = in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), self::JS_EXTENSIONS, true );

		if ( $is_js && $this->looks_generated( $source ) ) {
			return array(
				array(
					'class' => self::CLASS_UNMEASURABLE,
					'file'  => $path,
					'line'  => 0,
					'name'  => 'minified',
				),
			);
		}

		$hits = $is_js ? $this->scan_js_source( $source ) : $this->scan_source( $source );

		foreach ( $hits as $index => $hit ) {
			$hits[ $index ]['file'] = $path;
		}

		return $hits;
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

			if ( T_STRING === $id || ( defined( 'T_NAME_FULLY_QUALIFIED' ) && T_NAME_FULLY_QUALIFIED === $id ) ) {
				/*
				 * Namespaced plugin code writes `\wp_remote_get(`, and PHP 8 hands
				 * that over as one T_NAME_FULLY_QUALIFIED token rather than as
				 * T_STRING. It is not "a call through a variable function name" --
				 * it is the ordinary spelling in namespaced code, and reading only
				 * T_STRING made it invisible.
				 */
				$lower = ltrim( strtolower( $text ), '\\' );
				if ( in_array( $lower, self::HTTP_CALLS, true ) && $this->is_call( $tokens, $i ) ) {
					$hits[] = array(
						'class' => self::CLASS_HTTP,
						'file'  => '',
						'line'  => $line,
						'name'  => $lower,
					);
					continue;
				}

				if (
					in_array( $lower, self::URL_CARRYING_CALLS, true )
					&& $this->is_call( $tokens, $i )
					&& $this->call_carries_a_url( $tokens, $i )
				) {
					$hits[] = array(
						'class' => self::CLASS_HTTP,
						'file'  => '',
						'line'  => $line,
						'name'  => $lower,
					);
					continue;
				}

				/*
				 * The RAW text, not the lowercased one: strtolower() erases the camel
				 * boundary before to_snake_case() can find it, and a PSR-styled licence
				 * client -- activateLicense(), getLicenseStatus() -- stays invisible
				 * while the gate claims to read both spellings.
				 */
				$hits = array_merge( $hits, $this->word_hits( $this->to_snake_case( $text ), $line ) );
			} elseif ( T_VARIABLE === $id ) {
				$hits = array_merge( $hits, $this->word_hits( $this->to_snake_case( ltrim( $text, '$' ) ), $line ) );
			} elseif ( T_CONSTANT_ENCAPSED_STRING === $id ) {
				$hits = array_merge( $hits, $this->word_hits( $this->to_snake_case( trim( $text, '\'"' ) ), $line ) );
				$hits = array_merge( $hits, $this->embedded_js_hits( trim( $text, '\'"' ), $line ) );
			} elseif ( T_ENCAPSED_AND_WHITESPACE === $id || T_INLINE_HTML === $id ) {
				$hits = array_merge( $hits, $this->word_hits( $this->to_snake_case( $text ), $line ) );
				$hits = array_merge( $hits, $this->embedded_js_hits( $text, $line ) );
			}
		}

		return $hits;
	}

	/**
	 * Scan one JavaScript source string.
	 *
	 * WHAT THIS PROVES, AND WHAT IT DOES NOT
	 *
	 * Vocabulary is matched per line. An outbound call is decided inside THE
	 * CALL'S OWN ARGUMENT LIST -- never inside the block around it. An audit ran
	 * an earlier version, which used the enclosing parentheses as the window, over
	 * a real Lite core: the same `$.ajax({ url: ajax_url })` came back undecided in
	 * thirty files and clean in six, because almost all WordPress JavaScript sits
	 * inside an IIFE or a jQuery callback and one unrelated "/" anywhere in that
	 * block decided the verdict. A random answer is worse than either answer.
	 *
	 * Three outcomes, and the third is not the first: a target that resolves to an
	 * absolute URL is a finding, one that resolves to this site is clean, and one
	 * that does not resolve is `unmeasurable` -- said out loud, never swallowed.
	 *
	 * @param string $source JavaScript source.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 */
	public function scan_js_source( string $source ): array {
		$hits  = array();
		$lines = $this->js_code_lines( $source );

		foreach ( $lines as $line => $code ) {
			foreach ( $this->js_words( $code ) as $word ) {
				$hits = array_merge( $hits, $this->word_hits( $word, $line ) );
			}
		}

		$text      = implode( "\n", $lines );
		$constants = $this->js_constants( $text );

		$claimed = array();

		foreach ( self::JS_OUTBOUND as $shape ) {
			$matches = array();
			preg_match_all( $shape['pattern'], $text, $matches, PREG_OFFSET_CAPTURE );

			foreach ( $matches[0] as $match ) {
				$at  = (int) $match[1];
				$end = $at + strlen( (string) $match[0] );

				if ( 'call' === $shape['window'] ) {
					/*
					 * One call is one finding. `window.open(` matches the shape that
					 * names it AND the generic `.open(`; both land on the same
					 * parenthesis, and the more specific shape is listed first.
					 */
					$paren = strrpos( substr( $text, $at, $end - $at ), '(' );
					$paren = false === $paren ? $end - 1 : $at + $paren;
					if ( isset( $claimed[ $paren ] ) ) {
						continue;
					}

					$arguments = $this->argument_window( $text, $paren );
					if ( $this->is_a_definition( $text, $paren, $arguments ) ) {
						continue;
					}

					$claimed[ $paren ] = true;
					$window            = $this->target_argument( $arguments, (int) $shape['arg'] );
				} else {
					$window = $this->assignment_window( $text, $end );
				}

				$class = $this->classify_target( $window, $constants, (bool) $shape['strict'] );
				if ( '' === $class ) {
					continue;
				}

				$hits[] = array(
					'class' => $class,
					'file'  => '',
					'line'  => 1 + substr_count( substr( $text, 0, $at ), "\n" ),
					'name'  => $shape['name'],
				);
			}
		}

		usort(
			$hits,
			static fn( array $a, array $b ): int => $a['line'] <=> $b['line']
		);

		return $hits;
	}

	/**
	 * The text between a call's parentheses, quotes and nesting respected.
	 *
	 * @param string $text Comment-free source.
	 * @param int    $open Offset of the opening parenthesis.
	 * @return string
	 */
	private function argument_window( string $text, int $open ): string {
		$length = strlen( $text );
		$depth  = 0;
		$quote  = '';
		$window = '';

		for ( $i = $open; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( '' !== $quote ) {
				$window .= $char;
				if ( '\\' === $char && $i + 1 < $length ) {
					$window .= $text[ $i + 1 ];
					++$i;
				} elseif ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$quote = $char;
			} elseif ( '(' === $char ) {
				++$depth;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					return $window;
				}
			}

			$window .= $char;
		}

		return $window;
	}

	/**
	 * The argument that carries the call's target.
	 *
	 * The window narrowed once already, from the enclosing block to the argument
	 * list, and the defect followed it in: a `'/checkout'` sitting in an option
	 * object made an unresolved target clean, and a documentation URL in a payload
	 * made a same-origin call outbound. The target is one argument -- or, when
	 * that argument is an option object, its url/path property. A string somewhere
	 * else in the call is somebody else's business.
	 *
	 * @param string $arguments The call's whole argument list.
	 * @param int    $index     Which argument carries the target.
	 * @return string
	 */
	private function target_argument( string $arguments, int $index ): string {
		$parts    = $this->split_arguments( $arguments );
		$argument = trim( $parts[ $index ] ?? '' );

		if ( '' === $argument || '{' !== $argument[0] ) {
			return $argument;
		}

		$values = array();
		foreach ( $this->split_arguments( trim( $argument, '{}' ) ) as $property ) {
			$pair = array();
			if ( 1 === preg_match( self::JS_URL_PROPERTY_PATTERN, $property, $pair ) ) {
				$values[] = $pair[1];
			}
		}

		return implode( ' ', $values );
	}

	/**
	 * Split on commas that sit outside strings and nesting.
	 *
	 * @param string $text An argument list, or an object literal's body.
	 * @return list<string>
	 */
	private function split_arguments( string $text ): array {
		$parts   = array();
		$current = '';
		$quote   = '';
		$depth   = 0;
		$length  = strlen( $text );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( '' !== $quote ) {
				$current .= $char;
				if ( '\\' === $char && $i + 1 < $length ) {
					$current .= $text[ $i + 1 ];
					++$i;
				} elseif ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$quote = $char;
			} elseif ( '(' === $char || '[' === $char || '{' === $char ) {
				++$depth;
			} elseif ( ')' === $char || ']' === $char || '}' === $char ) {
				--$depth;
			}

			if ( ',' === $char && 0 === $depth ) {
				$parts[] = $current;
				$current = '';
				continue;
			}

			$current .= $char;
		}

		$parts[] = $current;

		return $parts;
	}

	/**
	 * Whether this parenthesis opens a method's parameter list, not a call.
	 *
	 * `class Store { fetch( id ) { … } }` declares a method that happens to share a
	 * primitive's name. Its parameters are bare identifiers and a body follows, so
	 * the shape is unmistakable -- and reporting it makes a clean tree fail.
	 *
	 * @param string $text      Comment-free source.
	 * @param int    $paren     Offset of the opening parenthesis.
	 * @param string $arguments The text between the parentheses.
	 * @return bool
	 */
	private function is_a_definition( string $text, int $paren, string $arguments ): bool {
		if ( 1 !== preg_match( '/^[\s\w$,]*$/', $arguments ) ) {
			return false;
		}

		$after = substr( $text, $paren + strlen( $arguments ) + 2 );

		return 1 === preg_match( '/^\s*{/', $after );
	}

	/**
	 * The right-hand side of an assignment, to the end of its statement.
	 *
	 * @param string $text  Comment-free source.
	 * @param int    $after Offset just past the "=".
	 * @return string
	 */
	private function assignment_window( string $text, int $after ): string {
		$length = strlen( $text );
		$quote  = '';
		$window = '';

		for ( $i = $after; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( '' !== $quote ) {
				$window .= $char;
				if ( '\\' === $char && $i + 1 < $length ) {
					$window .= $text[ $i + 1 ];
					++$i;
				} elseif ( $char === $quote ) {
					$quote = '';
				}
				continue;
			}

			if ( ';' === $char ) {
				return $window;
			}

			/*
			 * A newline ends the statement only when the line so far is complete.
			 * Prettier wraps at eighty columns, so `img.src =` and its URL routinely
			 * sit on different lines; stopping at the newline hid the commonest
			 * formatting there is.
			 */
			if ( "\n" === $char ) {
				$trimmed = rtrim( $window );
				if ( '' !== $trimmed && 1 !== preg_match( '/[=+?:,(\[&|]$/', $trimmed ) ) {
					return $window;
				}
				continue;
			}
			if ( "'" === $char || '"' === $char || '`' === $char ) {
				$quote = $char;
			}

			$window .= $char;
		}

		return $window;
	}

	/**
	 * Names bound exactly once to a string literal, and their literal.
	 *
	 * `const ENDPOINT = 'https://…'` one line above `fetch( ENDPOINT )` is not a
	 * URL "assembled at run time"; it is a URL written down. A name bound TWICE is
	 * dropped instead: the map is file-wide and knows nothing of scope, so
	 * resolving `url` when two functions each bind their own would hand one
	 * function's URL to the other's call -- a finding on innocent code or a miss on
	 * guilty code, decided by which binding came last.
	 *
	 * @param string $text Comment-free source.
	 * @return array<string, string>
	 */
	private function js_constants( string $text ): array {
		$matches = array();
		preg_match_all( self::JS_BINDING_PATTERN, $text, $matches );

		$seen  = array();
		$value = array();
		foreach ( $matches[1] as $index => $name ) {
			$seen[ $name ]  = ( $seen[ $name ] ?? 0 ) + 1;
			$value[ $name ] = $matches[2][ $index ];
		}

		$constants = array();
		foreach ( $value as $name => $literal ) {
			if ( 1 === $seen[ $name ] ) {
				$constants[ $name ] = $literal;
			}
		}

		return $constants;
	}

	/**
	 * Decide one outbound shape: a named finding, clean, or undecided.
	 *
	 * @param string                $window    The call's argument list, or an assignment's right side.
	 * @param array<string, string> $constants Unambiguous string bindings in the file.
	 * @param bool                  $strict    Whether an unresolved target is reported.
	 * @return string A CLASS_* value, or '' when the target is local or tolerated.
	 */
	private function classify_target( string $window, array $constants, bool $strict ): string {
		if ( $this->has_absolute_url( $window ) ) {
			return self::CLASS_HTTP;
		}

		$identifiers = array();
		preg_match_all( '/[A-Za-z_$][A-Za-z0-9_$]*/', $window, $identifiers );
		foreach ( $identifiers[0] as $identifier ) {
			if ( isset( $constants[ $identifier ] ) && $this->has_absolute_url( $constants[ $identifier ] ) ) {
				return self::CLASS_HTTP;
			}
		}

		if ( ! $strict || $this->has_local_target( $window ) ) {
			return '';
		}

		return self::CLASS_UNMEASURABLE;
	}

	/**
	 * Whether the window names a target on this site.
	 *
	 * A quoted path, or one of the globals WordPress itself defines to point at
	 * this site: `ajaxurl` is admin-ajax.php on the current host by definition,
	 * and `wpApiSettings.root` is what `wp_localize_script` fills with `rest_url()`.
	 * Reading them as undecided would bury the real undecided calls under the
	 * ordinary ones.
	 *
	 * @param string $window The call's argument list.
	 * @return bool
	 */
	private function has_local_target( string $window ): bool {
		if ( 1 === preg_match( self::JS_LOCAL_TARGET_PATTERN, $window ) ) {
			return true;
		}

		return 1 === preg_match( self::JS_SITE_GLOBAL_PATTERN, $window );
	}

	/**
	 * Whether the source is a generated bundle rather than something written.
	 *
	 * @param string $source File contents.
	 * @return bool
	 */
	private function looks_generated( string $source ): bool {
		foreach ( (array) preg_split( "/\r\n|\n|\r/", $source ) as $line ) {
			if ( strlen( (string) $line ) > self::MAX_SOURCE_LINE ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Characters after which a "/" opens a regular expression rather than dividing.
	 *
	 * @var list<string>
	 */
	private const REGEX_POSITION_CHARS = array(
		'(',
		',',
		'=',
		':',
		'[',
		'!',
		'&',
		'|',
		'?',
		'{',
		'}',
		';',
		'+',
		'-',
		'*',
		'%',
		'~',
		'^',
	);

	/**
	 * Keywords after which a "/" opens a regular expression.
	 *
	 * @var list<string>
	 */
	private const REGEX_POSITION_WORDS = array(
		'return',
		'typeof',
		'instanceof',
		'case',
		'in',
		'of',
		'new',
		'delete',
		'void',
		'do',
		'else',
		'yield',
		'await',
	);

	/**
	 * Source lines with comments and regular-expression bodies removed, keyed by
	 * 1-based line number.
	 *
	 * Quote state is tracked character by character because the naive strip --
	 * cut at the first "/" + "/" -- deletes the scheme separator of every URL the
	 * scanner exists to find. Template literals carry their state across lines.
	 *
	 * Regular expressions are recognised for one reason: `/(\\/index\\.php)?\\/*$/` is
	 * ordinary "strip the trailing slash" code, and its `/*` read as a block
	 * comment never closes -- every line below it leaves the scan while the run
	 * still reports clean. Their bodies are dropped rather than kept, so a URL
	 * inside a pattern is not mistaken for a call target.
	 *
	 * @param string $source JavaScript source.
	 * @return array<int, string>
	 */
	private function js_code_lines( string $source ): array {
		$lines       = (array) preg_split( "/\r\n|\n|\r/", $source );
		$out         = array();
		$in_block    = false;
		$in_template = false;

		foreach ( $lines as $index => $line ) {
			$line     = (string) $line;
			$code     = '';
			$length   = strlen( $line );
			$quote    = $in_template ? '`' : '';
			$in_regex = false;
			$in_class = false;

			for ( $i = 0; $i < $length; $i++ ) {
				$char = $line[ $i ];
				$next = $i + 1 < $length ? $line[ $i + 1 ] : '';

				if ( $in_block ) {
					if ( '*' === $char && '/' === $next ) {
						$in_block = false;
						++$i;
					}
					continue;
				}

				if ( $in_regex ) {
					if ( '\\' === $char ) {
						++$i;
					} elseif ( '[' === $char ) {
						$in_class = true;
					} elseif ( ']' === $char ) {
						$in_class = false;
					} elseif ( '/' === $char && ! $in_class ) {
						$in_regex = false;
					}
					continue;
				}

				if ( '' !== $quote ) {
					$code .= $char;
					if ( '\\' === $char && '' !== $next ) {
						$code .= $next;
						++$i;
						continue;
					}
					if ( $char === $quote ) {
						$quote = '';
					}
					continue;
				}

				if ( '/' === $char && '/' === $next ) {
					break;
				}
				if ( '/' === $char && '*' === $next ) {
					$in_block = true;
					++$i;
					continue;
				}
				if ( '/' === $char && $this->opens_a_regex( $code ) ) {
					$in_regex = true;
					$in_class = false;
					continue;
				}
				if ( "'" === $char || '"' === $char || '`' === $char ) {
					$quote = $char;
				}

				$code .= $char;
			}

			$in_template       = ( '`' === $quote );
			$out[ $index + 1 ] = $code;
		}

		return $out;
	}

	/**
	 * Whether a "/" at the end of the code read so far starts a regex literal.
	 *
	 * @param string $code Code accumulated on this line before the slash.
	 * @return bool
	 */
	private function opens_a_regex( string $code ): bool {
		$code = rtrim( $code );
		if ( '' === $code ) {
			return true;
		}

		if ( in_array( substr( $code, -1 ), self::REGEX_POSITION_CHARS, true ) ) {
			return true;
		}

		$word = array();

		return 1 === preg_match( '/([A-Za-z_$][A-Za-z0-9_$]*)$/', $code, $word )
			&& in_array( $word[1], self::REGEX_POSITION_WORDS, true );
	}

	/**
	 * Whether the code carries an absolute URL literal.
	 *
	 * @param string $code Comment-free code.
	 * @return bool
	 */
	private function has_absolute_url( string $code ): bool {
		return 1 === preg_match( self::JS_ABSOLUTE_URL_PATTERN, $code );
	}

	/**
	 * Identifiers and string contents of one line, normalised to snake_case so
	 * `licenseKey` and `license_key` are the same word to the vocabulary.
	 *
	 * @param string $code Comment-free line.
	 * @return list<string>
	 */
	private function js_words( string $code ): array {
		$words = array();

		$matches = array();
		preg_match_all( '/[A-Za-z_$][A-Za-z0-9_$]*/', $code, $matches );
		foreach ( $matches[0] as $identifier ) {
			$words[] = $this->to_snake_case( $identifier );
		}

		$literals = array();
		preg_match_all( '/[\'"`]([^\'"`]*)[\'"`]/', $code, $literals );
		foreach ( $literals[1] as $literal ) {
			$words[] = $this->to_snake_case( $literal );
		}

		return $words;
	}

	/**
	 * Convert camelCase and PascalCase to snake_case; lowercase everything else.
	 *
	 * @param string $text Identifier or literal.
	 * @return string
	 */
	private function to_snake_case( string $text ): string {
		return strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', str_replace( '$', '', $text ) ) );
	}

	/**
	 * Outbound calls in JavaScript that a PHP file hands to the browser.
	 *
	 * `wp_add_inline_script`, a heredoc of script, a `<script>` block printed by a
	 * template: a free core's telemetry is at least as likely to live in the glue
	 * that prints script as in a .js file, and reading only .js files looks past
	 * the most convenient hiding place in a WordPress plugin.
	 *
	 * Evidence only, and that is the point. Inside PHP the gate cannot tell script
	 * from prose -- an error message, a label, a pattern in a table all look like
	 * source -- so only a named outbound call WITH an absolute URL is reported. An
	 * undecided call is not: running this gate over its own package reported the
	 * shape table's label "import()" as a call, and a gate that shouts at prose is
	 * a gate somebody switches off. Licence and limit vocabulary is left to the PHP
	 * token walk, which already counts it.
	 *
	 * @param string $text Literal or markup carried by one PHP token.
	 * @param int    $line The line that token starts on.
	 * @return list<array{class:string, file:string, line:int, name:string}>
	 */
	private function embedded_js_hits( string $text, int $line ): array {
		$hits = array();

		foreach ( $this->scan_js_source( $text ) as $hit ) {
			if ( self::CLASS_HTTP !== $hit['class'] ) {
				continue;
			}

			$hit['line'] = $line + $hit['line'] - 1;
			$hits[]      = $hit;
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
	 * Whether an absolute URL literal sits inside this call's parentheses.
	 *
	 * @param list<array{0:int,1:string,2:int}|string> $tokens Tokens.
	 * @param int                                      $i      Index of the call's name.
	 * @return bool
	 */
	private function call_carries_a_url( array $tokens, int $i ): bool {
		$depth = 0;
		$count = count( $tokens );

		for ( $j = $i + 1; $j < $count; $j++ ) {
			$token = $tokens[ $j ];

			if ( is_string( $token ) ) {
				if ( '(' === $token ) {
					++$depth;
				} elseif ( ')' === $token ) {
					--$depth;
					if ( 0 === $depth ) {
						return false;
					}
				}
				continue;
			}

			if ( 0 === $depth ) {
				continue;
			}

			if (
				( T_CONSTANT_ENCAPSED_STRING === $token[0] || T_ENCAPSED_AND_WHITESPACE === $token[0] )
				&& $this->has_absolute_url( '"' . trim( $token[1], '\'"' ) . '"' )
			) {
				return true;
			}
		}

		return false;
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
	 * Files carrying one of the given extensions, skipping vendor/, node_modules/,
	 * tests/ and .git/.
	 *
	 * @param string             $dir        Root.
	 * @param array<int, string> $extensions Lowercase extensions without the dot.
	 * @return list<string>
	 */
	private function files_with_extensions( string $dir, array $extensions ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		/*
		 * `check:purity plugins/foo/` is the ordinary invocation -- shell completion
		 * puts the slash there. Without this the relative path below starts
		 * "vendor/..." with no leading slash, the "/vendor/" test never matches, and
		 * the gate scans its own vendor tree, reporting the scanner's own vocabulary
		 * as the core's findings.
		 */
		$dir = rtrim( str_replace( '\\', '/', $dir ), '/' );

		$files    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $entry ) {
			if ( ! $entry instanceof SplFileInfo || ! $entry->isFile() || ! in_array( strtolower( $entry->getExtension() ), $extensions, true ) ) {
				continue;
			}
			$relative = substr( str_replace( '\\', '/', $entry->getPathname() ), strlen( $dir ) );
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
	 * Files this scanner would read under a directory, both languages.
	 *
	 * A caller reports a clean tree only after checking this is not empty: a
	 * scan that read nothing says nothing, and "no findings" from an empty set
	 * is the loudest way a gate can lie.
	 *
	 * @param string $dir Root.
	 * @return list<string>
	 */
	public function scannable_files( string $dir ): array {
		return array_merge(
			$this->files_with_extensions( $dir, self::PHP_EXTENSIONS ),
			$this->files_with_extensions( $dir, self::JS_EXTENSIONS )
		);
	}

	/**
	 * The fixture set the self-test runs, per language.
	 *
	 * A fixture with an empty class is the negative control: it must produce
	 * nothing. Every other fixture must produce its own class.
	 *
	 * @return array<string, list<array{class:string, source:string}>>
	 */
	private function fixtures(): array {
		return array(
			'php' => array(
				array(
					'class'  => self::CLASS_HTTP,
					'source' => "<?php\n\$r = wp_remote_get( 'https://example.com' );\n",
				),
				array(
					'class'  => self::CLASS_LICENSE,
					'source' => "<?php\nfunction check_license( \$key ) { return true; }\n",
				),
				array(
					'class'  => self::CLASS_LIMIT,
					'source' => "<?php\nif ( \$count > FREE_LIMIT ) { return; }\n",
				),
				array(
					'class'  => '',
					'source' => "<?php\n"
						. "// wp_remote_get is mentioned in a comment only.\n"
						. "\$licensed_keys = get_option( 'keys' );\n"
						. "\$max = 10;\n"
						. "function fetch_local() { return file_get_contents( __DIR__ . '/x.json' ); }\n",
				),
			),
			'js'  => array(
				array(
					'class'  => self::CLASS_HTTP,
					'source' => "fetch( 'https://api.example.com/collect' );\n",
				),
				array(
					'class'  => self::CLASS_LICENSE,
					'source' => "const licenseKey = settings.key;\n",
				),
				array(
					'class'  => self::CLASS_LIMIT,
					'source' => "if ( window.pro_only ) { return; }\n",
				),
				array(
					'class'  => '',
					'source' => "// fetch( 'https://api.example.com/x' ) is documented, not called\n"
						. "const help = 'https://docs.example.com/guide';\n"
						. "apiFetch( { path: '/myplugin/v1/items' } );\n"
						. "const licensedKeys = counts.max_items;\n",
				),
			),
		);
	}

	/**
	 * Prove the scanner sees what it claims to see, before trusting a clean run.
	 *
	 * Positive fixtures must each produce their class; the negative fixture must
	 * produce nothing. Returns the failed expectations, empty when the scanner is
	 * sound.
	 *
	 * A language that ran NO fixture is itself a failure. Counting the fixture
	 * table would not catch that: an independent audit built a scanner that
	 * skipped the JavaScript half and the count-based check stayed green while the
	 * self-test proved only PHP. What is recorded here is what actually ran.
	 *
	 * @return list<string>
	 */
	public function self_test(): array {
		return $this->run_self_test()['failures'];
	}

	/**
	 * How many fixtures the self-test actually executed, per language.
	 *
	 * Produced by running them, never by counting the table, so the number cannot
	 * describe a run that did not happen. A caller prints it beside a clean
	 * verdict: "clean" is the claim, this is how far it reaches.
	 *
	 * @return array<string, int>
	 */
	public function self_test_coverage(): array {
		return $this->run_self_test()['coverage'];
	}

	/**
	 * Run every fixture, recording both the failures and what was executed.
	 *
	 * @return array{failures: list<string>, coverage: array<string, int>}
	 */
	private function run_self_test(): array {
		$failures = array();
		$coverage = array();
		$fixtures = $this->fixtures();

		foreach ( $fixtures as $language => $set ) {
			foreach ( $set as $fixture ) {
				$hits = 'php' === $language
					? $this->scan_source( $fixture['source'] )
					: $this->scan_js_source( $fixture['source'] );

				$coverage[ $language ] = ( $coverage[ $language ] ?? 0 ) + 1;

				if ( '' === $fixture['class'] ) {
					if ( array() !== $hits ) {
						$failures[] = $language . ' negative fixture tripped: ' . implode( ',', array_column( $hits, 'name' ) );
					}
					continue;
				}

				if ( ! in_array( $fixture['class'], array_column( $hits, 'class' ), true ) ) {
					$failures[] = $language . ' positive fixture not caught: ' . $fixture['class'];
				}
			}
		}

		foreach ( array_keys( $fixtures ) as $language ) {
			if ( ! isset( $coverage[ $language ] ) ) {
				$failures[]            = $language . ' fixtures did not run: the self-test proves nothing about that half';
				$coverage[ $language ] = 0;
			}
		}

		return array(
			'failures' => $failures,
			'coverage' => $coverage,
		);
	}
}
