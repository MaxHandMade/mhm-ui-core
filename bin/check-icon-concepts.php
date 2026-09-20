<?php
/**
 * CLI wrapper for the icon-concept gate. THIS FILE DOES NOT SHIP
 * (.gitattributes: /bin/ export-ignore) -- a consumer requires
 * src/Kit/IconConceptScanner.php directly; the README shows how.
 *
 *   php bin/check-icon-concepts.php [--anchor=NAME]... [--expect-raw=N] <path>...
 *
 * exit 1  a call site writes a raw suffix the vocabulary already has a concept for
 * exit 2  the run measured NOTHING (no anchored file, or no readable icon value),
 *         or --expect-raw was given and the count did not match: an empty gate is
 *         a broken gate, not a clean one
 * exit 0  otherwise; unknown suffixes are listed
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );
require_once $root . '/src/Kit/Icons.php';
require_once $root . '/src/Kit/IconConceptScanner.php';

$anchors     = array();
$paths       = array();
$expect_raw  = null;

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( 0 === strpos( $arg, '--anchor=' ) ) {
		$anchors[] = substr( $arg, strlen( '--anchor=' ) );
		continue;
	}
	if ( 0 === strpos( $arg, '--expect-raw=' ) ) {
		$expect_raw = (int) substr( $arg, strlen( '--expect-raw=' ) );
		continue;
	}
	$paths[] = $arg;
}

if ( array() === $anchors ) {
	$anchors = array( 'mhmuicore_stat_card_html', 'mhmuicore_stats_grid_html', 'ui-core/src-react/components/Stat' );
}

if ( array() === $paths ) {
	fwrite( STDERR, "usage: php bin/check-icon-concepts.php [--anchor=NAME]... [--expect-raw=N] <path>...\n" );
	exit( 2 );
}

$result    = ( new \MHMUiCore\Kit\IconConceptScanner( $anchors ) )->scan( $paths );
$measured  = $result['concepts'] + count( $result['raw'] ) + count( $result['unknown'] );

if ( 0 === $result['files'] ) {
	fwrite( STDERR, "EMPTY-SET: no file mentioned any anchor -- the gate measured nothing\n" );
	exit( 2 );
}

if ( 0 === $measured ) {
	fwrite( STDERR, "EMPTY-SET: {$result['files']} anchored file(s) but not one readable icon value -- green here proves nothing\n" );
	exit( 2 );
}

foreach ( $result['unknown'] as $hit ) {
	fwrite( STDOUT, sprintf( "unknown  %s:%d  '%s' -- register it as a concept if it is one\n", $hit['file'], $hit['line'], $hit['value'] ) );
}

foreach ( $result['raw'] as $hit ) {
	fwrite( STDERR, sprintf( "RAW      %s:%d  '%s' -- write %s\n", $hit['file'], $hit['line'], $hit['value'], "'" . implode( "' or '", $hit['concepts'] ) . "'" ) );
}

fwrite(
	STDOUT,
	sprintf(
		"icon-concepts: %d file(s), %d concept call site(s), %d raw, %d unknown\n",
		$result['files'],
		$result['concepts'],
		count( $result['raw'] ),
		count( $result['unknown'] )
	)
);

if ( null !== $expect_raw ) {
	if ( count( $result['raw'] ) !== $expect_raw ) {
		fwrite( STDERR, sprintf( "EXPECT-RAW: wanted %d, measured %d\n", $expect_raw, count( $result['raw'] ) ) );
		exit( 2 );
	}
	exit( 0 );
}

exit( array() === $result['raw'] ? 0 : 1 );
