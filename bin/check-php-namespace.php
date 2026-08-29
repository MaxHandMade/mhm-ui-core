<?php
/**
 * Measures the shipped PHP surface for custom-property names.
 *
 * PHP is read through token_get_all() rather than a regex: a docblock that shows
 * `--mhm-primary:#000;` as an example is indistinguishable from an emitter to any
 * pattern that cannot tell code from comment.
 */
declare( strict_types = 1 );

/**
 * The next token that is not whitespace or a comment, as a string, or null.
 *
 * token_get_all() interleaves T_WHITESPACE, so the next ARRAY INDEX is almost
 * never the next significant token: `'--' . $x` puts the string at $i, a space
 * at $i+1 and the '.' at $i+2. Comments can sit there too.
 *
 * @param list<array{0:int,1:string,2:int}|string> $tokens
 */
function mhmuicore_next_significant( array $tokens, int $i ): ?string {
	for ( $j = $i + 1, $n = count( $tokens ); $j < $n; $j++ ) {
		$t = $tokens[ $j ];
		if ( is_string( $t ) ) {
			return $t;
		}
		if ( ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			return null; // a significant token that is not an operator
		}
	}
	return null;
}

// The repo to measure. Defaults to this package; the regression suite points it at
// throwaway fixture repos so the archive itself can be shaped without touching this tree.
$root = $argv[1] ?? dirname( __DIR__ );
exec( 'git -C ' . escapeshellarg( $root ) . ' archive HEAD | tar -t', $listing, $code );
if ( 0 !== $code ) {
	fwrite( STDERR, "MEASURE-FAILED: git archive did not run\n" );
	exit( 2 );
}

$php = array_values( array_filter( $listing, static fn( $p ) => str_ends_with( $p, '.php' ) ) );
echo 'SCANNED-PHP: ' . implode( ' ', $php ) . "\n";

if ( array() === $php ) {
	echo "EMPTY-SET: no shipped PHP file matched\n";
	echo "SUMMARY: 1 violation(s)\n";
	exit( 1 );
}

// Each recorded violation is ['text' => ..., 'name' => ...]: `text` is the line
// printed below — one per location, so the gate always says WHERE — while `name`
// is the offending identifier, used only to de-duplicate the SUMMARY count
// further down. The spec's counting rule (matched by the sibling CSS/JS gate)
// counts DISTINCT offending names, not raw occurrences: the same literal
// referenced from fifty places is one name, not fifty. P1b has no single name to
// de-duplicate against, so — as with the JS gate's equivalent case — its own
// text (which already carries file:line) doubles as its name, so distinct
// locations never collapse into each other.
$violations = array();
foreach ( $php as $path ) {
	$source = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' show ' . escapeshellarg( "HEAD:{$path}" ) );
	$tokens = token_get_all( (string) $source );

	foreach ( $tokens as $i => $token ) {
		if ( ! is_array( $token ) ) {
			continue; // single-char tokens ('.', '"', '{', '}', ...) carry no name.
		}

		if ( T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
			$value = trim( $token[1], "'\"" );

			if ( str_starts_with( $value, '--' ) && ! str_starts_with( $value, '--mhmui-' ) ) {
				$violations[] = array(
					'text' => "VIOLATION: {$path}:{$token[2]} foreign-custom-property — {$value}",
					'name' => $value,
				);
			}

			// P1b: '--mhmui-x' . $suffix builds a name the gate can never verify.
			//
			// The next ARRAY INDEX is not the next significant token: token_get_all()
			// puts T_WHITESPACE between them, so `'--' . $x` lands the string at $i,
			// whitespace at $i+1 and the '.' at $i+2. Measured on this codebase's PHP;
			// an $i+1 comparison never fires on normally-spaced code.
			if ( str_starts_with( $value, '--' ) && '.' === mhmuicore_next_significant( $tokens, $i ) ) {
				$text = "VIOLATION: {$path}:{$token[2]} dynamic-custom-property-name";
				$violations[] = array(
					'text' => $text,
					'name' => $text,
				);
			}
			continue;
		}

		// A double-quoted string or heredoc/nowdoc that contains a variable never
		// tokenizes as T_CONSTANT_ENCAPSED_STRING: its static portion is
		// T_ENCAPSED_AND_WHITESPACE instead, a token type the check above never
		// looks at — so "--mhm-forbidden-{$n}" reached the shipped surface
		// unflagged (review round 1 finding). Confirmed empirically: a NOWDOC
		// body (<<<'EOT' ... EOT, no interpolation at all) also tokenizes as
		// T_ENCAPSED_AND_WHITESPACE, as a single token with no variable token
		// beside it — token_get_all() reuses the same token type for "possibly
		// interpolated" text regardless of whether interpolation actually
		// occurs, so heredoc-without-variables and nowdoc are indistinguishable
		// from each other here and are treated identically below.
		if ( T_ENCAPSED_AND_WHITESPACE === $token[0] && str_starts_with( $token[1], '--' ) ) {
			// Only the LEADING fragment of the string/heredoc is checked — the
			// one immediately after the opening delimiter ('"' or
			// T_START_HEREDOC). A fragment that instead follows a closed
			// interpolation ("{$a}--mhm-x") is not leading: no T_WHITESPACE is
			// ever interleaved inside an encapsed string body, so direct index
			// adjacency (not mhmuicore_next_significant()) is exactly right
			// here, unlike the '.'-concatenation check above. This mirrors the
			// JS gate, which only inspects quasis[0] of an interpolated
			// TemplateLiteral: a trailing static tail after a variable is
			// exactly as unverifiable as the variable itself, and is the same
			// documented boundary as a name assembled from a variable or an
			// import — not chased here either.
			$prev        = $tokens[ $i - 1 ] ?? null;
			$is_leading  = ( '"' === $prev ) || ( is_array( $prev ) && T_START_HEREDOC === $prev[0] );

			if ( $is_leading ) {
				$next        = $tokens[ $i + 1 ] ?? null;
				$closes_here = ( '"' === $next ) || ( is_array( $next ) && T_END_HEREDOC === $next[0] );

				if ( $closes_here ) {
					// No interpolation occurs in this string/heredoc at all —
					// this is every nowdoc, and a heredoc/double-quoted string
					// with no variable in it either. The value is fully known
					// at parse time, exactly like a T_CONSTANT_ENCAPSED_STRING,
					// so it is judged the same way: P1a.
					//
					// A heredoc/nowdoc body's last line always carries its
					// trailing newline as PART of this token's text (the
					// closing identifier must start its own line) — rtrim it
					// so a single-line violation never prints an embedded
					// newline, which would otherwise split one VIOLATION into
					// two physical lines.
					$static_value = rtrim( $token[1], "\r\n" );
					if ( ! str_starts_with( $static_value, '--mhmui-' ) ) {
						$violations[] = array(
							'text' => "VIOLATION: {$path}:{$token[2]} foreign-custom-property — {$static_value}",
							'name' => $static_value,
						);
					}
				} else {
					// The leading fragment is immediately followed by real
					// interpolation (T_CURLY_OPEN / T_VARIABLE /
					// T_DOLLAR_OPEN_CURLY_BRACES): the assembled value can
					// never be verified statically — same violation as the
					// '.'-concatenation case above, and on the same terms as
					// the sibling JS gate: an interpolated leading quasi is
					// flagged regardless of whether its own text looks
					// compliant, because "--mhmui-{$name}" is exactly as
					// unverifiable as "--mhm-x-{$n}".
					$text = "VIOLATION: {$path}:{$token[2]} dynamic-custom-property-name";
					$violations[] = array(
						'text' => $text,
						'name' => $text,
					);
				}
			}
		}
	}
}

foreach ( $violations as $v ) {
	echo $v['text'] . "\n";
}

$distinct_names = array_unique( array_column( $violations, 'name' ) );
echo 'SUMMARY: ' . count( $distinct_names ) . " violation(s)\n";
exit( array() === $violations ? 0 : 1 );
