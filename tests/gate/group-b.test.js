const { fixtureRepo, runGateOut, PHP_GATE } = require( './helpers' );

// Both fixtures use a COMPLIANT static prefix ('--mhmui-') on purpose. A bare
// '--' is independently a P1a foreign-custom-property violation (it starts
// with '--' and is not '--mhmui-'), so it would make these tests pass even
// with the P1b check fully disabled — the P1a hit alone already produces
// exit 1. Using '--mhmui-' keeps P1a silent, so only a live P1b check can
// produce a violation here, and the assertions below lock that in by
// checking the violation TYPE, not just the exit code.
describe( 'B — P1b: a name assembled at runtime can never be verified', () => {
	test( 'B-1 PHP string concatenation bites via P1b, not P1a', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'bootstrap.php': "<?php\n$css = '--mhmui-' . $suffix . ': red';\n",
		} ), PHP_GATE );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'dynamic-custom-property-name' );
		expect( out ).not.toContain( 'foreign-custom-property' );
	} );

	test( 'B-2 JS string concatenation bites via P1b, not P1a', () => {
		const { code, out } = runGateOut( fixtureRepo( {
			'src-react/apply.js': "el.style.setProperty( '--mhmui-' + ns, v );\n",
		} ) );
		expect( code ).toBe( 1 );
		expect( out ).toContain( 'dynamic-custom-property-name' );
		expect( out ).not.toContain( 'foreign-custom-property' );
	} );
} );
