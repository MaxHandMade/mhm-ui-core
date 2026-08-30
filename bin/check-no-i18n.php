<?php
/**
 * G2 -- no gettext call may exist under src/Layout.
 *
 * This package owns no consumer slug and therefore no text domain. WordPress's
 * string extractor reads code WITHOUT executing it, so a domain passed as a
 * variable or a literal that belongs to no installed .pot is never collected.
 * A translatable string added here would not be "untranslated" -- untranslated
 * strings show up in a .pot and get flagged. This string would be invisible to
 * every extractor and ship as raw English forever, in every consumer, with
 * nothing anywhere reporting it. That is strictly worse than untranslated, and
 * it is a defect no other gate in this repository can catch.
 *
 * token_get_all() rather than a regex: a comment or a string that merely
 * mentions __() is not a call, and a scanner that cannot tell the difference
 * teaches people to work around it.
 *
 * @package MHMUiCore
 */

declare(strict_types=1);

/**
 * Whether the next significant token after index $i is exactly "(".
 *
 * token_get_all() interleaves T_WHITESPACE and comments between an identifier
 * and the parenthesis that would make it a call, so the next ARRAY INDEX is
 * almost never the next significant token: `__( "x", "d" )` puts whitespace at
 * $i+1 and "(" at $i+2. This is the same discipline as
 * mhmuicore_next_significant() in bin/check-php-namespace.php, narrowed to the
 * one question this gate needs answered: is this identifier a call?
 *
 * @param list<array{0:int,1:string,2:int}|string> $tokens
 */
function mhmuicore_i18n_is_call( array $tokens, int $i ): bool {
	for ( $j = $i + 1, $n = count( $tokens ); $j < $n; $j++ ) {
		$t = $tokens[ $j ];

		if ( is_string( $t ) ) {
			return '(' === $t;
		}

		if ( ! in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			return false; // a significant token that is not "("
		}
	}

	return false;
}

$root   = $argv[1] ?? dirname( __DIR__ );
$target = $root . '/src/Layout';

if ( ! is_dir( $target ) ) {
	// No SUMMARY line here on purpose: "I could not measure" must never look
	// like "I measured zero" (SUMMARY: 0), which is what a caller would see if
	// this exit code were mistaken for a clean run.
	fwrite( STDERR, "check-no-i18n: src/Layout not found under {$root}" . PHP_EOL );
	exit( 2 );
}

$banned = array(
	'__',
	'_e',
	'_x',
	'_n',
	'_ex',
	'_nx',
	'_n_noop',
	'_nx_noop',
	'esc_html__',
	'esc_html_e',
	'esc_html_x',
	'esc_attr__',
	'esc_attr_e',
	'esc_attr_x',
	'translate',
	'translate_with_gettext_context',
);

// T_STRING catches an unqualified call: __( ... ). A call written with a
// leading backslash or a namespace prefix -- \__( ... ), Layout\__( ... ) --
// never tokenizes as T_STRING at all: PHP 8's tokenizer gives it its own
// T_NAME_FULLY_QUALIFIED / T_NAME_QUALIFIED / T_NAME_RELATIVE token, carrying
// the backslashes as part of the token text. A scanner that matches only
// T_STRING lets \__( "x", "d" ) straight through -- confirmed empirically
// against this PHP version before this list was written. Matching all four
// token types and comparing only the last path segment closes that gap
// without having to special-case any one call spelling.
$name_token_types = array( T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE );

$failures = array();
$files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ) );

foreach ( $files as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}

	$tokens = token_get_all( (string) file_get_contents( $file->getPathname() ) );
	$count  = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) || ! in_array( $token[0], $name_token_types, true ) ) {
			continue;
		}

		$segments = explode( '\\', $token[1] );
		$name     = end( $segments );

		if ( ! in_array( $name, $banned, true ) ) {
			continue;
		}

		if ( mhmuicore_i18n_is_call( $tokens, $i ) ) {
			$failures[] = sprintf( '%s:%d %s()', $file->getPathname(), (int) $token[2], $name );
		}
	}
}

foreach ( $failures as $failure ) {
	fwrite( STDERR, 'check-no-i18n: ' . $failure . PHP_EOL );
}

printf( 'SUMMARY: %d%s', count( $failures ), PHP_EOL );

exit( array() === $failures ? 0 : 1 );
