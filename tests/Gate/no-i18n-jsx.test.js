/**
 * @jest-environment node
 */
/**
 * The package has no text domain: every string is a prop the consumer has
 * already translated (bin/check-no-i18n.php holds the PHP side). Nothing held
 * the JSX side -- a `__()` inside src-react/ would reach no .pot and ship
 * English to every consumer. This gate lints three probes AS IF they lived in
 * src-react/ (the eslint CLI on stdin with a virtual --stdin-filename -- no fixture file inside
 * src-react/, which `npm run lint:js` would otherwise lint too), and the same
 * probes as if they lived OUTSIDE it, where they must pass: that second half
 * is what makes a green run mean "the src-react override catches it" rather
 * than "something, somewhere, errors".
 */
const { join } = require( 'node:path' );
const { spawnSync } = require( 'node:child_process' );
const { existsSync } = require( 'node:fs' );

const ROOT = join( __dirname, '..', '..' );
// The real eslint CLI in its own process, reading the repository's
// eslint.config.cjs the way `npm run lint:js` does. In-process it cannot run
// under Jest: ESLint loads its config through import(), and
// @wordpress/eslint-plugin require()s an .mjs file, both of which Jest's
// module runtime refuses on Node 22. eslint's package `exports` hide bin/
// from require.resolve, hence the path.
const ESLINT_BIN = join( ROOT, 'node_modules', 'eslint', 'bin', 'eslint.js' );
const WP_SCRIPTS_BIN = join(
	ROOT,
	'node_modules',
	'@wordpress',
	'scripts',
	'bin',
	'wp-scripts.js'
);

// Cold ESLint starts, one per probe.
jest.setTimeout( 60000 );

function run( args, input ) {
	// A synchronous child cannot be cut by Jest's timeout; this one can. A
	// killed child reports status null, which the callers treat as failure.
	return spawnSync( process.execPath, args, {
		cwd: ROOT,
		input,
		encoding: 'utf8',
		timeout: 30000,
	} );
}

function lint( code, virtualPath ) {
	const result = run(
		[
			ESLINT_BIN,
			'--stdin',
			'--stdin-filename',
			join( ROOT, virtualPath ),
			'--format',
			'json',
		],
		code
	);
	// 0 clean, 1 findings; anything else is ESLint failing to run, and a
	// gate that cannot run must not read as a gate that found nothing.
	if ( result.status !== 0 && result.status !== 1 ) {
		throw new Error(
			`eslint did not run (status ${ result.status }): ${ result.stderr }`
		);
	}
	return {
		status: result.status,
		messages: JSON.parse( result.stdout )[ 0 ].messages,
	};
}

const PROBES = {
	'no-restricted-imports':
		"import { __ } from '@wordpress/i18n';\nexport const a = __( 'x' );\n",
	'no-restricted-globals': "export const a = wp.i18n.__( 'x' );\n",
	'no-restricted-properties': "export const a = window.wp.i18n.__( 'x' );\n",
};

describe( 'src-react/ carries no translation call -- strings are props', () => {
	test( 'the gate runs the ESLint that `npm run lint:js` runs', () => {
		// wp-scripts lint-js passes --version through to the ESLint it
		// resolves; a second copy in the tree would split the two.
		const gate = run( [ ESLINT_BIN, '--version' ] );
		const lintJs = run( [ WP_SCRIPTS_BIN, 'lint-js', '--version' ] );
		const version = /^v\d+\.\d+\.\d+$/m;

		expect( gate.stdout ).toMatch( version );
		expect( lintJs.stdout ).toMatch( version );
		expect( lintJs.stdout.match( version )[ 0 ] ).toBe(
			gate.stdout.match( version )[ 0 ]
		);
		// Same version could still be two copies; scripts' own nested copy is
		// the one lint-js would pick, so its absence makes it one binary.
		expect(
			existsSync(
				join(
					ROOT,
					'node_modules',
					'@wordpress',
					'scripts',
					'node_modules',
					'eslint'
				)
			)
		).toBe( false );
	} );

	for ( const [ rule, code ] of Object.entries( PROBES ) ) {
		test( `${ rule } fires inside src-react/, as an error`, () => {
			const { status, messages } = lint(
				code,
				'src-react/components/__probe__.jsx'
			);
			// An error, not a warning: `npm run lint:js` exits 0 on warnings,
			// so a rule turned down to 'warn' would let the import through CI.
			expect( status ).toBe( 1 );
			expect(
				messages.filter( ( m ) => m.ruleId === rule && m.severity === 2 )
			).not.toHaveLength( 0 );
		} );

		test( `${ rule } does not fire outside src-react/ (the override is what catches it)`, () => {
			const { messages } = lint( code, 'tests/Gate/__probe__.js' );
			// The file was really linted: an ignored file reports a null-rule
			// warning and a parse failure a fatal one, and either would pass
			// the absence check below for nothing.
			expect(
				messages.filter( ( m ) => m.ruleId === null || m.fatal )
			).toHaveLength( 0 );
			expect( messages.map( ( m ) => m.ruleId ) ).not.toContain( rule );
		} );
	}
} );
