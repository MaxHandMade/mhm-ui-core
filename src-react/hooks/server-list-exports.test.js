import * as kit from '../index';

// 0.20.0: the server list helpers are NAMED exports (not components), and
// the two param helpers stay module-internal.
describe( 'server list exports', () => {
	it( 'exports the server list helpers by name', () => {
		expect( typeof kit.useServerList ).toBe( 'function' );
		expect( typeof kit.pageRange ).toBe( 'function' );
		expect( typeof kit.useDebouncedValue ).toBe( 'function' );
	} );

	it( 'keeps the param helpers out of the barrel', () => {
		expect( kit.buildParams ).toBeUndefined();
		expect( kit.normalizeTotals ).toBeUndefined();
	} );
} );
