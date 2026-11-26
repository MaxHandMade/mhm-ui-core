/**
 * Every kit class a component can emit has a rule in the stylesheet a
 * consumer enqueues (admin.css + pro.css).
 *
 * Moved here from bin/build-design-system.py (2026-11-26), where the same
 * check ran over hand-drawn design-system cards. It caught a real defect
 * there: 0.8.1 moved the pro-lock rule into pro.css and the card drew an
 * unstyled box for five releases. Here it reads the components' own JSX, so
 * it covers what the package actually renders, not a copy of it.
 *
 * What it proves is narrow on purpose: the class is MENTIONED in a selector
 * (a grouped or compound selector counts), not that it has declarations of
 * its own. A template class (`mhmui-button--${ v }`) is checked by its
 * prefix: at least one `.mhmui-button--…` rule must exist.
 */
const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const ROOT = join( __dirname, '..', '..' );
const read = ( p ) => readFileSync( join( ROOT, p ), 'utf8' );
const KIT_CSS = read( 'assets/react/admin.css' ) + read( 'assets/react/pro.css' );
const MANIFEST = JSON.parse( read( 'src-react/components.json' ) );

/** Classes emitted only as hooks: styled through another class, by design. */
const HOOKS = {
	'mhmui-pagination__button': 'drawn by core `.button` (inline) or the kit `.mhmui-button` classes (footer)',
	'mhmui-table__th': 'base hook of SortHeader; the rules sit on --sortable / --end',
	'mhmui-table__select-all': 'styled as `.mhmui-table__select-hit input[type="checkbox"]`',
	// A key ending in `--` is a template prefix hook.
	'mhmui-notice--': 'Notice is drawn by core `.notice notice-<tone>`; the kit tone class is a hook',
};

const strip = ( src ) => src.replace( /\/\*[\s\S]*?\*\//g, '' ).replace( /\/\/.*$/gm, '' );
// CSS has no line comments; `//` there is a URL.
const stripCss = ( css ) => css.replace( /\/\*[\s\S]*?\*\//g, '' );

function emitted( src ) {
	const code = strip( src );
	const literals = new Set(
		[ ...code.matchAll( /(?<!-)mhmui-[a-z0-9_-]*[a-z0-9_](?![a-z0-9_-]*\$\{)/g ) ].map( ( m ) => m[ 0 ] )
	);
	const prefixes = new Set( [ ...code.matchAll( /(mhmui-[a-z0-9_-]+--)\$\{/g ) ].map( ( m ) => m[ 1 ] ) );
	return { literals, prefixes };
}

const mentions = ( css, cls ) =>
	new RegExp( `\\.${ cls.replace( /[.*+?^${}()|[\]\\-]/g, '\\$&' ) }(?![\\w-])` ).test( stripCss( css ) );

/** Classes (and template prefixes) of `src` that `css` never mentions. */
function missing( src, css ) {
	const { literals, prefixes } = emitted( src );
	return [
		...[ ...literals ].filter( ( c ) => ! HOOKS[ c ] && ! mentions( css, c ) ),
		...[ ...prefixes ].filter( ( p ) => ! HOOKS[ p ] && ! stripCss( css ).includes( `.${ p }` ) ).map( ( p ) => `${ p }*` ),
	];
}

const SOURCES = Object.keys( MANIFEST ).map( ( name ) => [
	name,
	read( `src-react/components/${ name }.jsx` ),
] );

describe( 'every kit class a component emits has a rule', () => {
	test( 'EMPTY-SET guard: every manifest member was read and emits classes', () => {
		expect( SOURCES.length ).toBeGreaterThanOrEqual( 18 );
		const total = SOURCES.reduce( ( n, [ , src ] ) => n + emitted( src ).literals.size, 0 );
		expect( total ).toBeGreaterThan( 80 ); // 91 measured 2026-11-26
	} );

	test.each( SOURCES )( '%s', ( name, src ) => {
		expect( [ name, missing( src, KIT_CSS ) ] ).toEqual( [ name, [] ] );
	} );

	test( 'not vacuous: a class whose rule is gone is reported, and a template prefix too', () => {
		const src = "<div className={ `mhmui-toolbar mhmui-x--${ tone }` } />";
		expect( missing( src, '.mhmui-toolbar { display: flex; } .mhmui-x--a {}' ) ).toEqual( [] );
		expect( missing( src, '/* .mhmui-toolbar */ .mhmui-x--a {}' ) ).toEqual( [ 'mhmui-toolbar' ] );
		expect( missing( src, '.mhmui-toolbar {}' ) ).toEqual( [ 'mhmui-x--*' ] );
		// A longer class that merely starts with the name does not count.
		expect( missing( '"mhmui-toolbar"', '.mhmui-toolbar-x {}' ) ).toEqual( [ 'mhmui-toolbar' ] );
	} );

	test( 'every hook is still emitted and still has no rule of its own (else drop it from HOOKS)', () => {
		const lits = new Set( SOURCES.flatMap( ( [ , src ] ) => [ ...emitted( src ).literals ] ) );
		const pres = new Set( SOURCES.flatMap( ( [ , src ] ) => [ ...emitted( src ).prefixes ] ) );
		for ( const hook of Object.keys( HOOKS ) ) {
			const prefix = hook.endsWith( '--' );
			expect( [ hook, ( prefix ? pres : lits ).has( hook ) ] ).toEqual( [ hook, true ] );
			const ruled = prefix ? stripCss( KIT_CSS ).includes( `.${ hook }` ) : mentions( KIT_CSS, hook );
			expect( [ hook, ruled ] ).toEqual( [ hook, false ] );
		}
	} );
} );
