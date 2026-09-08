const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );
const { renderTokensBlock, replaceBlock, flatTokens, START, END } = require( '../../bin/build-tokens.js' );

const ROOT = join( __dirname, '..', '..' );

describe( 'tokens:build keeps admin.css and tokens.json as one source', () => {
	const doc = JSON.parse( readFileSync( join( ROOT, 'src-react', 'tokens.json' ), 'utf8' ) );
	const css = readFileSync( join( ROOT, 'assets', 'react', 'admin.css' ), 'utf8' );

	test( 'the committed stylesheet is exactly what the generator produces (no drift)', () => {
		expect( replaceBlock( css, renderTokensBlock( doc, '.mhmui-admin' ) ) ).toBe( css );
	} );

	test( 'every token becomes a --mhmui- custom property and nothing else', () => {
		const block = renderTokensBlock( doc, '.mhmui-admin' );
		const props = block.match( /--[a-z0-9-]+:/g );
		expect( props ).toHaveLength( Object.keys( doc.scopes[ '.mhmui-admin' ] ).length );
		for ( const p of props ) {
			expect( p.startsWith( '--mhmui-' ) ).toBe( true );
		}
	} );

	test( 'the gate is not vacuous: a changed token is detected', () => {
		const mutated = {
			...doc,
			scopes: { ...doc.scopes, '.mhmui-admin': { ...doc.scopes[ '.mhmui-admin' ], blue: '#000000' } },
		};
		expect( replaceBlock( css, renderTokensBlock( mutated, '.mhmui-admin' ) ) ).not.toBe( css );
	} );

	test( 'a stylesheet without markers is refused, not silently rewritten', () => {
		expect( () => replaceBlock( '.x{}', renderTokensBlock( doc, '.mhmui-admin' ) ) ).toThrow( /markers/ );
		expect( css.indexOf( START ) ).toBeGreaterThan( -1 );
		expect( css.indexOf( END ) ).toBeGreaterThan( css.indexOf( START ) );
	} );
} );

describe( 'tokens.json kapsam şeması', () => {
	const doc = JSON.parse( readFileSync( join( ROOT, 'src-react', 'tokens.json' ), 'utf8' ) );

	test( 'scopes anahtarı vardır ve iki kapsam taşır', () => {
		expect( Object.keys( doc.scopes ).sort() ).toEqual( [ '.mhmui-admin', '.mhmui-front' ] );
	} );

	test( 'her kapsam kendi degerlerini tasir -- ayni ad, farkli deger olabilir', () => {
		expect( doc.scopes[ '.mhmui-admin' ] ).toHaveProperty( 'surface' );
		expect( doc.scopes[ '.mhmui-front' ] ).toHaveProperty( 'surface' );
	} );

	test( 'renderTokensBlock yalniz istenen kapsamin bloklarini basar', () => {
		const block = renderTokensBlock( doc, '.mhmui-admin' );
		expect( block ).toContain( '.mhmui-admin' );
		expect( block ).not.toContain( '.mhmui-front' );
	} );

	test( 'flatTokens eski duz gorunumu korur (tokens.json herkese acik API)', () => {
		expect( flatTokens( doc ) ).toEqual( doc.scopes[ '.mhmui-admin' ] );
	} );
} );
