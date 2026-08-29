const { fixtureRepo, runGate, PHP_GATE } = require( './helpers' );

describe( 'B — P1b: a name assembled at runtime can never be verified', () => {
	test( 'B-1 PHP string concatenation bites', () => {
		expect( runGate( fixtureRepo( {
			'bootstrap.php': "<?php\n$css = '--' . $prefix . ': red';\n",
		} ), PHP_GATE ) ).toBe( 1 );
	} );

	test( 'B-2 JS string concatenation bites', () => {
		expect( runGate( fixtureRepo( {
			'src-react/apply.js': "el.style.setProperty( '--' + ns, v );\n",
		} ) ) ).toBe( 1 );
	} );
} );
