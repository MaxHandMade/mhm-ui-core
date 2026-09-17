import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { render } from '@testing-library/react';
import StatCard, { TONES, DIRECTIONS } from '../../src-react/components/StatCard';
import StatsGrid from '../../src-react/components/StatsGrid';

const ROOT = join( __dirname, '..', '..' );
const manifest = JSON.parse( readFileSync( join( ROOT, 'src-react', 'components.json' ), 'utf8' ) );
const snapshot = JSON.parse( readFileSync( join( ROOT, 'src-react', 'kit-classes.json' ), 'utf8' ) );
const COMPONENTS = { StatCard, StatsGrid };

function classesOf( element ) {
	const { container } = render( element );
	const all = new Set();
	container.querySelectorAll( '[class]' ).forEach( ( el ) => el.classList.forEach( ( c ) => all.add( c ) ) );
	return [ ...all ].sort();
}

/** Every class a kit member's source can emit: static literals + template prefixes expanded by the vocabularies. */
export function classUniverse( source ) {
	const code = source.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /\/\/.*$/gm, '' );
	const universe = new Set();
	// Negative lookbehind excludes a `--mhmui-*` CSS custom property (e.g.
	// StatsGrid.jsx's '--mhmui-columns' style var): it is not a class, and
	// without the lookbehind the regex still matches "mhmui-columns" starting
	// one character in.
	for ( const m of code.matchAll( /(?<!-)mhmui-[a-z0-9_-]*[a-z0-9_](?![a-z0-9_-]*\$\{)/g ) ) {
		universe.add( m[ 0 ] );
	}
	for ( const m of code.matchAll( /(mhmui-[a-z0-9_-]+--)\$\{\s*(\w+)\s*\}/g ) ) {
		const vocab = m[ 2 ] === 'tone' ? TONES : DIRECTIONS.filter( ( d ) => d !== 'flat' );
		vocab.forEach( ( v ) => universe.add( m[ 1 ] + v ) );
	}
	return universe;
}

describe( 'gate 6 -- the PHP and JSX kit renderers emit the same classes', () => {
	const members = Object.entries( manifest ).filter( ( [ , e ] ) => e.php !== undefined );

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
				expect( [ name, i, classesOf( <Component { ...fixture } /> ) ] ).toEqual( [ name, i, snapshot[ name ][ i ] ] );
			} );
		}
	} );

	test( 'branch coverage: every class each PHP-twinned member can emit appears in its own fixtures', () => {
		for ( const [ name ] of members ) {
			const source = readFileSync( join( ROOT, 'src-react', 'components', `${ name }.jsx` ), 'utf8' );
			const covered = new Set( snapshot[ name ].flat() );
			const missing = [ ...classUniverse( source ) ].filter( ( c ) => ! covered.has( c ) );
			expect( [ name, missing ] ).toEqual( [ name, [] ] );
		}
	} );

	test( 'the coverage check is not vacuous: a class literal no fixture reaches is reported', () => {
		const universe = classUniverse( "const x = 'mhmui-stat-card__never';" );
		expect( universe.has( 'mhmui-stat-card__never' ) ).toBe( true );
		expect( snapshot.StatCard.flat() ).not.toContain( 'mhmui-stat-card__never' );
	} );
} );
