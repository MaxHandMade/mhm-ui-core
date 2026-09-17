<?php
/**
 * Gate 6, PHP half: the class set the PHP kit renderer emits for every fixture
 * in src-react/components.json, written to src-react/kit-classes.json.
 *
 *   php bin/dump-kit-classes.php          rewrite the snapshot
 *   php bin/dump-kit-classes.php --check  exit 1 if stale, 2 if nothing to compare
 *
 * The JS half (tests/Gate/kit-parity.test.js) renders the same fixtures through
 * JSX and compares against this file. ci.yml runs PHP and JS in separate jobs;
 * the committed snapshot is the contract between them.
 */

declare( strict_types = 1 );

define( 'ABSPATH', sys_get_temp_dir() . '/' );

$root = dirname( __DIR__ );
require_once $root . '/tests/Fixtures/wp-function-stubs.php';
require_once $root . '/bootstrap.php';

$manifest = json_decode( (string) file_get_contents( $root . '/src-react/components.json' ), true );
if ( ! is_array( $manifest ) ) {
	fwrite( STDERR, "MEASURE-FAILED: components.json is not a JSON object\n" );
	exit( 2 );
}

/**
 * @param string $html Rendered HTML.
 * @return list<string> Sorted, unique class names.
 */
function mhmuicore_gate_classes( string $html ): array {
	preg_match_all( '/class="([^"]*)"/', $html, $m );
	$all = array();
	foreach ( $m[1] as $attr ) {
		foreach ( preg_split( '/\s+/', trim( $attr ) ) ?: array() as $c ) {
			if ( '' !== $c ) {
				$all[] = $c;
			}
		}
	}
	$all = array_values( array_unique( $all ) );
	sort( $all );
	return $all;
}

$snapshot = array();
foreach ( $manifest as $name => $entry ) {
	if ( ! is_array( $entry ) || ! isset( $entry['php'] ) ) {
		continue;
	}
	$fn = (string) $entry['php'];
	if ( ! function_exists( $fn ) ) {
		fwrite( STDERR, "MEASURE-FAILED: {$name} names {$fn}, which bootstrap.php does not define\n" );
		exit( 2 );
	}
	$rows = array();
	foreach ( (array) ( $entry['fixtures'] ?? array() ) as $fixture ) {
		$fixture = (array) $fixture;
		$html    = 'mhmuicore_stats_grid_html' === $fn
			? $fn( (array) ( $fixture['cards'] ?? array() ), $fixture['columns'] ?? 4 )
			: $fn( $fixture );
		$rows[]  = mhmuicore_gate_classes( (string) $html );
	}
	if ( array() === $rows ) {
		fwrite( STDERR, "EMPTY-SET: {$name} has a PHP renderer and no fixture\n" );
		exit( 2 );
	}
	$snapshot[ $name ] = $rows;
}

if ( array() === $snapshot ) {
	fwrite( STDERR, "EMPTY-SET: no manifest member declares a PHP renderer -- a parity gate over nothing is a broken gate\n" );
	exit( 2 );
}

$target = $root . '/src-react/kit-classes.json';
$json   = json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";

if ( in_array( '--check', $argv, true ) ) {
	if ( ! is_file( $target ) || file_get_contents( $target ) !== $json ) {
		fwrite( STDERR, "kit-parity: src-react/kit-classes.json is stale -- run `composer dump:kit-classes`\n" );
		exit( 1 );
	}
	fwrite( STDOUT, 'kit-parity: ' . count( $snapshot ) . " PHP renderer(s), snapshot in sync\n" );
	exit( 0 );
}

file_put_contents( $target, $json );
fwrite( STDOUT, 'kit-parity: wrote ' . count( $snapshot ) . " member(s)\n" );
