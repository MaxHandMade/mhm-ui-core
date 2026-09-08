const { readFileSync } = require( 'node:fs' );
const { join } = require( 'node:path' );

const ROOT = join( __dirname, '..', '..' );

/**
 * B3 -- the branch's headline change (colour-named modifiers replaced by role
 * names: success/warning/danger/info/neutral) had zero protection before this
 * file existed. `tokens.test.js` only pins the generated token BLOCK,
 * `kit.test.jsx` passes `tone` straight through to a className string (so it
 * proves the JSX assembles the class it was told to, never that the class has
 * a rule), and the design-system self-check exempted every modifier. Nothing
 * would fail if `.mhmui-stat-card--blue` came back.
 *
 * This reads the shipped stylesheet as text -- the same technique
 * `tokens.test.js` already uses -- so it exercises the actual CSS a browser
 * loads, not a re-implementation of it.
 */
describe( 'CSS role vocabulary (admin.css)', () => {
	const css = readFileSync( join( ROOT, 'assets', 'react', 'admin.css' ), 'utf8' );

	const ROLES = [ 'success', 'warning', 'danger', 'info', 'neutral' ];
	const COLOUR_NAMES = [ 'blue', 'green', 'amber', 'red', 'grey' ];

	test.each( ROLES )( '.mhmui-stat-card--%s has a rule', ( role ) => {
		expect( css ).toMatch( new RegExp( '\\.mhmui-stat-card--' + role + '\\s*[,{]' ) );
	} );

	test.each( ROLES )( '.mhmui-status--%s has a rule', ( role ) => {
		expect( css ).toMatch( new RegExp( '\\.mhmui-status--' + role + '\\s*[,{]' ) );
	} );

	test.each( COLOUR_NAMES )( '.mhmui-stat-card--%s (retired colour name) is absent', ( colour ) => {
		expect( css ).not.toMatch( new RegExp( '\\.mhmui-stat-card--' + colour + '\\b' ) );
	} );

	test.each( COLOUR_NAMES )( '.mhmui-status--%s (retired colour name) is absent', ( colour ) => {
		expect( css ).not.toMatch( new RegExp( '\\.mhmui-status--' + colour + '\\b' ) );
	} );
} );
