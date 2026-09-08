const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const ROOT = join( __dirname, '..', '..' );
const manifest = JSON.parse( readFileSync( join( ROOT, 'src-react', 'components.json' ), 'utf8' ) );
const barrel = readFileSync( join( ROOT, 'src-react', 'index.js' ), 'utf8' );

describe( 'components.json kapilarin girdisidir', () => {
	test( 'barrel ile manifest ayni bileseni sayar -- biri otekini gecemez', () => {
		// 🔴 Yorumlar ONCE atilir. Barrel'da bu deseni ANLATAN bir yorum var
		// (Task 1, index.js:15-19) ve ham metinde aranirsa o yorum bir bilesen
		// gibi sayilir. Bir kod yorumunun kirabildigi kapi, ihracati degil
		// metni olcuyor demektir.
		const code = barrel.replace( /\/\/.*$/gm, '' ).replace( /\/\*[\s\S]*?\*\//g, '' );
		const exported = [ ...code.matchAll( /export \{ default as (\w+) \}/g ) ].map( ( m ) => m[ 1 ] );
		expect( exported ).toHaveLength( 8 );
		expect( Object.keys( manifest ).sort() ).toEqual( exported.sort() );
	} );

	test( 'her uye uc kapinin okudugu alanlari tasir', () => {
		for ( const [ name, entry ] of Object.entries( manifest ) ) {
			expect( typeof entry.interactive ).toBe( 'boolean' );
			expect( typeof entry.tone_semantics ).toBe( 'boolean' );
			expect( Array.isArray( entry.fixtures ) ).toBe( true );
			expect( entry.fixtures.length ).toBeGreaterThan( 0 );
		}
	} );

	test( 'ton tasiyan her uye anlamini renkten baska bir seyle de verir (WCAG 1.4.1)', () => {
		for ( const [ name, entry ] of Object.entries( manifest ) ) {
			if ( entry.tone_semantics ) {
				const hasText = entry.fixtures.some(
					( f ) => 'string' === typeof f.label || 'string' === typeof f.children
				);
				expect( hasText ).toBe( true );
			}
		}
	} );
} );
