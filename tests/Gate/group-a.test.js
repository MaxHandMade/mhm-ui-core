const { fixtureRepo, runGate } = require( './helpers' );

describe( 'A — P1a: only --mhmui-* names, defined or read', () => {
	test( 'A-1 a foreign DEFINITION bites', () => {
		expect( runGate( fixtureRepo( {
			'src-react/admin.css': '.mhmui-admin { --mhm-primary: #ff0000; }\n',
		} ) ) ).toBe( 1 );
	} );

	test( 'A-2 a foreign READ bites, independently of A-1', () => {
		expect( runGate( fixtureRepo( {
			'src-react/admin.css': '.mhmui-admin { color: var( --mhm-text ); }\n',
		} ) ) ).toBe( 1 );
	} );

	test( 'A-3 a compliant new token does NOT bite', () => {
		expect( runGate( fixtureRepo( {
			'src-react/admin.css': '.mhmui-admin { --mhmui-new-token: 1px; }\n',
		} ) ) ).toBe( 0 );
	} );

	test( 'A-4 a foreign name in a JSX inline style bites', () => {
		expect( runGate( fixtureRepo( {
			'src-react/Widget.jsx': "export const W = () => <div style={{ '--mhm-x': 1 }} />;\n",
		} ) ) ).toBe( 1 );
	} );

	test( 'A-5 a foreign name in a CSSOM call bites', () => {
		expect( runGate( fixtureRepo( {
			'src-react/apply.js': "el.style.setProperty( '--mhm-x', v );\n",
		} ) ) ).toBe( 1 );
	} );
} );
