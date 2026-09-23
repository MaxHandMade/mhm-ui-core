/**
 * @jest-environment node
 */
/**
 * The package has no text domain: every string is a prop the consumer has
 * already translated (bin/check-no-i18n.php holds the PHP side). Nothing held
 * the JSX side -- a `__()` inside src-react/ would reach no .pot and ship
 * English to every consumer. This gate lints three probes AS IF they lived in
 * src-react/ (lintText with a virtual filePath -- no fixture file inside
 * src-react/, which `npm run lint:js` would otherwise lint too), and the same
 * probes as if they lived OUTSIDE it, where they must pass: that second half
 * is what makes a green run mean "the src-react override catches it" rather
 * than "something, somewhere, errors".
 */
const { join } = require( 'node:path' );
const { ESLint } = require( 'eslint' );

const ROOT = join( __dirname, '..', '..' );
const eslint = new ESLint( { cwd: ROOT } );

const PROBES = {
	'no-restricted-imports': "import { __ } from '@wordpress/i18n';\nexport const a = __( 'x' );\n",
	'no-restricted-globals': "export const a = wp.i18n.__( 'x' );\n",
	'no-restricted-properties': "export const a = window.wp.i18n.__( 'x' );\n",
};

async function rulesHit( code, virtualPath ) {
	const [ result ] = await eslint.lintText( code, { filePath: join( ROOT, virtualPath ) } );
	return result.messages.map( ( m ) => m.ruleId );
}

describe( 'src-react/ carries no translation call -- strings are props', () => {
	for ( const [ rule, code ] of Object.entries( PROBES ) ) {
		test( `${ rule } fires inside src-react/`, async () => {
			expect( await rulesHit( code, 'src-react/components/__probe__.jsx' ) ).toContain( rule );
		} );

		test( `${ rule } does not fire outside src-react/ (the override is what catches it)`, async () => {
			expect( await rulesHit( code, 'tests/Gate/__probe__.js' ) ).not.toContain( rule );
		} );
	}
} );
