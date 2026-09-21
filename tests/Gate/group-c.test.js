const { fixtureRepo, runGate, runGateOut } = require( './helpers' );

const css = ( body ) => ( { 'src-react/admin.css': body } );

describe( 'C — P2: an ID selector is caught in every CSS position, and only there', () => {
	// WHY THESE ASSERT THE RULE NAME AND NOT THE EXIT CODE.
	// Every case below is meant to prove one thing: selector-max-id still sees
	// an ID in this position. An exit-code assertion cannot prove that, because
	// the gate lints a fixture with the package's WHOLE config -- any other
	// rule the fixture happens to trip keeps the exit code at 1 whether or not
	// the rule under test fired at all. group-e wrote that lesson down first;
	// this group kept the weaker shape until it actually bit.
	//
	// It bit in 0.14.1. Adding selector-class-pattern made C-4's `.p` and
	// C-5's `.y` produce class violations of their own, so both cases would
	// have stayed green with ID detection removed entirely -- measured, not
	// feared: linting those two bodies with ONLY the class rule returns one
	// violation each. The GitHub review bot caught it on the PR that added the
	// rule; the commit that introduced it had argued, wrongly, that cases
	// expecting 1 were unaffected.
	//
	// Two changes together, because either alone leaves a hole: the incidental
	// classes are renamed into this package's own namespace, AND the assertion
	// names the rule. The rename fixes today's collision; naming the rule is
	// what survives the NEXT rule somebody adds to the shared config.
	test.each( [
		[ 'C-1 top level',        '#x { color: red }' ],
		[ 'C-2 inside @media',    '@media (min-width:1px) { #x { color: red } }' ],
		[ 'C-3 inside @supports', '@supports (display:grid) { #x { color: red } }' ],
		[ 'C-4 CSS nesting',      '.mhmui-p { & #x { color: red } }' ],
		[ 'C-5 inside :is()',     ':is(#x, .mhmui-y) { color: red }' ],
		[ 'C-6 escaped ident',    '#\\31 23 { color: red }' ],
	] )( '%s is caught BY selector-max-id', ( _label, body ) => {
		const { code, out } = runGateOut( fixtureRepo( css( body ) ) );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'selector-max-id' );
		// The fixture must trip the rule under test and nothing else, or the
		// assertion above stops being evidence about this position.
		expect( out ).toContain( 'SUMMARY: 1 violation(s)' );
	} );

	// A clean fixture has to be clean under EVERY rule in the shared config,
	// not just the one it was written for -- the same coupling as above, seen
	// from the other side. `.x` would fail selector-class-pattern and turn
	// these into false positives about ID detection.
	test.each( [
		[ 'C-7 attribute value',  '[href="#x"] { color: red }' ],
		[ 'C-8 url() fragment',   '.mhmui-x { filter: url(#g) }' ],
		[ 'C-9 string value',     '.mhmui-x { content: "#s" }' ],
	] )( '%s is not an ID selector', ( _label, body ) => {
		expect( runGate( fixtureRepo( css( body ) ) ) ).toBe( 0 );
	} );

	// C-10: the no-op control. The real package, unmodified, must be clean —
	// this is the run a naive line regex fails: on the pre-migration file it
	// matched 11 lines of which only 3 were IDs.
	test( 'C-10 the real package, unmodified, is clean', () => {
		const { join } = require( 'node:path' );
		expect( runGate( join( __dirname, '..', '..' ) ) ).toBe( 0 );
	} );
} );
