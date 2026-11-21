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
const { spawnSync } = require( 'node:child_process' );

const ROOT = join( __dirname, '..', '..' );
// The real eslint CLI in its own process, reading the repository's
// eslint.config.cjs the way `npm run lint:js` does. In-process it cannot run
// under Jest: ESLint 9 loads its config through import(), and
// @wordpress/eslint-plugin require()s an .mjs file, both of which Jest's
// module runtime refuses on Node 22.
// The same binary wp-scripts lint-js resolves (resolve-bin: the root copy;
// eslint's package `exports` hide bin/ from require.resolve).
const ESLINT_BIN = join( ROOT, 'node_modules', 'eslint', 'bin', 'eslint.js' );

const PROBES = {
	'no-restricted-imports':
		"import { __ } from '@wordpress/i18n';\nexport const a = __( 'x' );\n",
	'no-restricted-globals': "export const a = wp.i18n.__( 'x' );\n",
	'no-restricted-properties': "export const a = window.wp.i18n.__( 'x' );\n",
};

function rulesHit( code, virtualPath ) {
	const run = spawnSync(
		process.execPath,
		[
			ESLINT_BIN,
			'--stdin',
			'--stdin-filename',
			join( ROOT, virtualPath ),
			'--format',
			'json',
		],
		{ cwd: ROOT, input: code, encoding: 'utf8' }
	);
	// 0 clean, 1 findings; anything else is ESLint failing to run, and a
	// gate that cannot run must not read as a gate that found nothing.
	expect( [ 0, 1 ] ).toContain( run.status );
	const [ result ] = JSON.parse( run.stdout );
	return result.messages.map( ( m ) => m.ruleId );
}

describe( 'src-react/ carries no translation call -- strings are props', () => {
	for ( const [ rule, code ] of Object.entries( PROBES ) ) {
		test( `${ rule } fires inside src-react/`, () => {
			expect(
				rulesHit( code, 'src-react/components/__probe__.jsx' )
			).toContain( rule );
		} );

		test( `${ rule } does not fire outside src-react/ (the override is what catches it)`, () => {
			expect( rulesHit( code, 'tests/Gate/__probe__.js' ) ).not.toContain(
				rule
			);
		} );
	}
} );
