const { fixtureRepo, runGate } = require( './helpers' );

const css = ( body ) => ( { 'src-react/admin.css': body } );

describe( 'C — P2: an ID selector is caught in every CSS position, and only there', () => {
	test.each( [
		[ 'C-1 top level',        '#x { color: red }',                              1 ],
		[ 'C-2 inside @media',    '@media (min-width:1px) { #x { color: red } }',   1 ],
		[ 'C-3 inside @supports', '@supports (display:grid) { #x { color: red } }', 1 ],
		[ 'C-4 CSS nesting',      '.p { & #x { color: red } }',                     1 ],
		[ 'C-5 inside :is()',     ':is(#x, .y) { color: red }',                     1 ],
		[ 'C-6 escaped ident',    '#\\31 23 { color: red }',                        1 ],
		[ 'C-7 attribute value',  '[href="#x"] { color: red }',                     0 ],
		// The class name in a clean fixture is not incidental any more: since
		// 0.14.1 the shared config also carries selector-class-pattern, and the
		// gate lints a fixture repo with the SAME config as the package. A
		// fixture that expects exit 0 must therefore be clean under every rule,
		// not just the one it was written for -- `.x` now fails the class rule
		// and would make these two cases exit 1 for a reason that has nothing to
		// do with ID selectors. Cases expecting 1 are unaffected: an extra
		// violation cannot change an exit code that is already 1.
		[ 'C-8 url() fragment',   '.mhmui-x { filter: url(#g) }',                   0 ],
		[ 'C-9 string value',     '.mhmui-x { content: "#s" }',                     0 ],
	] )( '%s exits %i', ( _label, body, expected ) => {
		expect( runGate( fixtureRepo( css( body ) ) ) ).toBe( expected );
	} );

	// C-10: the no-op control. The real package, unmodified, must be clean —
	// this is the run a naive line regex fails: on the pre-migration file it
	// matched 11 lines of which only 3 were IDs.
	test( 'C-10 the real package, unmodified, is clean', () => {
		const { join } = require( 'node:path' );
		expect( runGate( join( __dirname, '..', '..' ) ) ).toBe( 0 );
	} );
} );
