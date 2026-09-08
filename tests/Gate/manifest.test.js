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

	test( 'ton tasiyan her uye tone_cue ile anlam kiladı -- renk aramasin', () => {
		// 🔴 A gate that cannot fail is worse than none: the old test just checked
		// whether tone_semantics: true entries happened to include label or children
		// (which all fixtures of those components require anyway, so the test was
		// always green). This gate verifies the intent: every tone-carrying component
		// declares which prop conveys meaning without relying on color. If someone adds
		// a tone-semantics component without declaring a cue, or writes a fixture that
		// omits it, this test goes red.
		for ( const [ name, entry ] of Object.entries( manifest ) ) {
			if ( entry.tone_semantics ) {
				// tone_cue is REQUIRED when tone_semantics is true
				expect( entry.tone_cue ).toBeDefined();
				expect( typeof entry.tone_cue ).toBe( 'string' );

				// tone_cue must name a key that exists in props.
				// Failure: Component declares tone_cue but it is not in props.
				expect( Object.keys( entry.props ) ).toContain( entry.tone_cue );

				// EVERY fixture must include the tone_cue prop.
				// Failure: A fixture is missing the tone_cue that the component declares.
				for ( let i = 0; i < entry.fixtures.length; i++ ) {
					const fixture = entry.fixtures[ i ];
					expect( Object.keys( fixture ) ).toContain( entry.tone_cue );
				}
			}
		}
	} );
} );
