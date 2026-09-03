const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );
const { renderTokensBlock, replaceBlock, START, END } = require( '../../bin/build-tokens.js' );

const ROOT = join( __dirname, '..', '..' );

describe( 'tokens:build keeps admin.css and tokens.json as one source', () => {
	const doc = JSON.parse( readFileSync( join( ROOT, 'src-react', 'tokens.json' ), 'utf8' ) );
	const css = readFileSync( join( ROOT, 'assets', 'react', 'admin.css' ), 'utf8' );

	test( 'the committed stylesheet is exactly what the generator produces (no drift)', () => {
		expect( replaceBlock( css, renderTokensBlock( doc ) ) ).toBe( css );
	} );

	test( 'every token becomes a --mhmui- custom property and nothing else', () => {
		const block = renderTokensBlock( doc );
		const props = block.match( /--[a-z0-9-]+:/g );
		expect( props ).toHaveLength( Object.keys( doc.tokens ).length );
		for ( const p of props ) {
			expect( p.startsWith( '--mhmui-' ) ).toBe( true );
		}
	} );

	test( 'the gate is not vacuous: a changed token is detected', () => {
		const mutated = { ...doc, tokens: { ...doc.tokens, blue: '#000000' } };
		expect( replaceBlock( css, renderTokensBlock( mutated ) ) ).not.toBe( css );
	} );

	test( 'a stylesheet without markers is refused, not silently rewritten', () => {
		expect( () => replaceBlock( '.x{}', renderTokensBlock( doc ) ) ).toThrow( /markers/ );
		expect( css.indexOf( START ) ).toBeGreaterThan( -1 );
		expect( css.indexOf( END ) ).toBeGreaterThan( css.indexOf( START ) );
	} );
} );
