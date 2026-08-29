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
		if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
			continue; // comments and doc comments are their own token types: never reached.
		}

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
	}
}

foreach ( $violations as $v ) {
	echo $v['text'] . "\n";
}

$distinct_names = array_unique( array_column( $violations, 'name' ) );
echo 'SUMMARY: ' . count( $distinct_names ) . " violation(s)\n";
exit( array() === $violations ? 0 : 1 );
