import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { render } from '@testing-library/react';
import StatCard, {
	TONES,
	DIRECTIONS,
	DIRECTION_MARKS,
} from '../../src-react/components/StatCard';
import StatsGrid from '../../src-react/components/StatsGrid';
import Tabs from '../../src-react/components/Tabs';
import { SEED } from '../../src-react/icons';

const ROOT = join( __dirname, '..', '..' );
const manifest = JSON.parse(
	readFileSync( join( ROOT, 'src-react', 'components.json' ), 'utf8' )
);
const snapshot = JSON.parse(
	readFileSync( join( ROOT, 'src-react', 'kit-classes.json' ), 'utf8' )
);
const COMPONENTS = { StatCard, StatsGrid, Tabs };

// Pins the JSX vocabulary to the PHP twin's, parsed straight from its source
// (not re-typed here): StatCard::DIRECTIONS and the keys of its private
// DIRECTION_MARKS. Without this, shrinking DIRECTIONS/DIRECTION_MARKS on one
// side (or reverting both twins together) leaves every test below green
// vacuously -- the residual escape hasOwnProperty a reviewer measured after
// 4d9af87 (this gate's OWN vocabulary was still unpinned lever, not tied to
// what either renderer actually branches on).
const phpSource = readFileSync(
	join( ROOT, 'src', 'Kit', 'StatCard.php' ),
	'utf8'
);

function phpArrayStrings( source, constName ) {
	const m = source.match(
		new RegExp( `const\\s+${ constName }\\s*=\\s*array\\(([^;]*)\\);`, 's' )
	);
	if ( ! m ) {
		throw new Error(
			`gate 6: could not find PHP const ${ constName } in StatCard.php`
		);
	}
	return [ ...m[ 1 ].matchAll( /'([^']+)'/g ) ].map( ( x ) => x[ 1 ] );
}

/** `'k' => 'v'` pairs of a PHP associative const, parsed from the twin's source. */
function phpArrayPairs( source, constName ) {
	const m = source.match(
		new RegExp( `const\\s+${ constName }\\s*=\\s*array\\(([^;]*)\\);`, 's' )
	);
	if ( ! m ) {
		throw new Error( `gate 6: could not find PHP const ${ constName } in Icons.php` );
	}
	const pairs = {};
	for ( const p of m[ 1 ].matchAll( /'([^']+)'\s*=>\s*'([^']+)'/g ) ) {
		pairs[ p[ 1 ] ] = p[ 2 ];
	}
	return pairs;
}

const PHP_DIRECTIONS = phpArrayStrings( phpSource, 'DIRECTIONS' );
const PHP_DIRECTION_MARK_KEYS = phpArrayStrings( phpSource, 'DIRECTION_MARKS' );

function classesOf( element ) {
	const { container } = render( element );
	const all = new Set();
	container
		.querySelectorAll( '[class]' )
		.forEach( ( el ) => el.classList.forEach( ( c ) => all.add( c ) ) );
	return [ ...all ].sort();
}

/**
 * Every class a kit member's source can emit: static literals + template prefixes expanded by the vocabularies.
 * @param source
 */
export function classUniverse( source ) {
	const code = source
		.replace( /\/\*[\s\S]*?\*\//g, '' )
		.replace( /\/\/.*$/gm, '' );
	const universe = new Set();
	// Negative lookbehind excludes a `--mhmui-*` CSS custom property (e.g.
	// StatsGrid.jsx's '--mhmui-columns' style var): it is not a class, and
	// without the lookbehind the regex still matches "mhmui-columns" starting
	// one character in.
	for ( const m of code.matchAll(
		/(?<!-)mhmui-[a-z0-9_-]*[a-z0-9_](?![a-z0-9_-]*\$\{)/g
	) ) {
		universe.add( m[ 0 ] );
	}
	for ( const m of code.matchAll(
		/(mhmui-[a-z0-9_-]+--)\$\{\s*(\w+)\s*\}/g
	) ) {
		// Since 0.13.0 the delta line renders for every DIRECTION_MARKS
		// member, flat included (it is no longer a dead branch the fixtures
		// never need to reach) -- so the full vocabulary applies here too.
		// Sourced from DIRECTION_MARKS, not the plain DIRECTIONS array:
		// DIRECTION_MARKS is the object both StatCard.jsx and StatCard.php
		// actually branch on, so THIS is what a fixture must be proven to
		// reach -- DIRECTIONS is documentation-only and could drift from it
		// unnoticed.
		const vocab =
			m[ 2 ] === 'tone' ? TONES : Object.keys( DIRECTION_MARKS );
		vocab.forEach( ( v ) => universe.add( m[ 1 ] + v ) );
	}
	return universe;
}

describe( 'gate 6 -- the PHP and JSX kit renderers emit the same classes', () => {
	const members = Object.entries( manifest ).filter(
		( [ , e ] ) => e.php !== undefined
	);

	test( 'EMPTY-SET guard: at least one member has a PHP renderer and every one has fixtures', () => {
		expect( members.length ).toBeGreaterThan( 0 );
		for ( const [ name, entry ] of members ) {
			expect( entry.fixtures.length ).toBeGreaterThan( 0 );
			expect( snapshot[ name ] ).toHaveLength( entry.fixtures.length );
		}
	} );

	test( 'every fixture: JSX class set equals the committed PHP snapshot', () => {
		for ( const [ name, entry ] of members ) {
			const Component = COMPONENTS[ name ];
			expect( Component ).toBeDefined();
			entry.fixtures.forEach( ( fixture, i ) => {
				expect( [
					name,
					i,
					classesOf( <Component { ...fixture } /> ),
				] ).toEqual( [ name, i, snapshot[ name ][ i ] ] );
			} );
		}
	} );

	test( 'branch coverage: every class each PHP-twinned member can emit appears in its own fixtures', () => {
		for ( const [ name ] of members ) {
			const source = readFileSync(
				join( ROOT, 'src-react', 'components', `${ name }.jsx` ),
				'utf8'
			);
			const covered = new Set( snapshot[ name ].flat() );
			const missing = [ ...classUniverse( source ) ].filter(
				( c ) => ! covered.has( c )
			);
			expect( [ name, missing ] ).toEqual( [ name, [] ] );
		}
	} );

	test( 'the coverage check is not vacuous: a class literal no fixture reaches is reported', () => {
		const universe = classUniverse( "const x = 'mhmui-stat-card__never';" );
		expect( universe.has( 'mhmui-stat-card__never' ) ).toBe( true );
		expect( snapshot.StatCard.flat() ).not.toContain(
			'mhmui-stat-card__never'
		);
	} );

	test( 'the JSX direction vocabulary is pinned to the PHP twin -- DIRECTIONS and DIRECTION_MARKS keys', () => {
		// Shrinking DIRECTIONS or DIRECTION_MARKS on ONE side (or reverting
		// both twins together, so they still agree with each other but no
		// longer with what this file used to assume) must go red here, not
		// pass vacuously because every other gate 6 test derives its
		// vocabulary from the same shrunken source.
		expect( [ ...DIRECTIONS ].sort() ).toEqual(
			[ ...PHP_DIRECTIONS ].sort()
		);
		expect( Object.keys( DIRECTION_MARKS ).sort() ).toEqual(
			[ ...PHP_DIRECTION_MARK_KEYS ].sort()
		);
		expect( Object.keys( DIRECTION_MARKS ).sort() ).toEqual(
			[ ...PHP_DIRECTIONS ].sort()
		);
	} );
} );

describe( 'gate 6 -- the icon vocabulary is one table with two copies', () => {
	const PHP_SEED = phpArrayPairs(
		readFileSync( join( ROOT, 'src', 'Kit', 'Icons.php' ), 'utf8' ),
		'SEED'
	);

	test( 'EMPTY-SET guard: the parsed PHP table is not empty', () => {
		expect( Object.keys( PHP_SEED ).length ).toBeGreaterThanOrEqual( 12 );
	} );

	test( 'every seed concept maps to the same suffix in both twins', () => {
		expect( SEED ).toEqual( PHP_SEED );
	} );
} );
