import { pageRange, buildParams, normalizeTotals } from './useServerList';

describe( 'pageRange', () => {
	it.each( [
		[ 1, 20, 0, { from: 0, to: 0, total: 0 } ],
		[ 1, 20, 48, { from: 1, to: 20, total: 48 } ],
		[ 3, 20, 48, { from: 41, to: 48, total: 48 } ],
		[ 3, 20, 20, { from: 0, to: 0, total: 20 } ],
	] )( 'page %i of %i per page, %i total', ( page, perPage, total, out ) => {
		expect( pageRange( page, perPage, total ) ).toEqual( out );
	} );
} );

describe( 'buildParams', () => {
	it( "drops '', null and undefined but keeps 'all', 0 and false", () => {
		expect(
			buildParams(
				{
					search: '',
					a: null,
					b: undefined,
					status: 'all',
					n: 0,
					flag: false,
				},
				2,
				20
			)
		).toEqual( {
			status: 'all',
			n: 0,
			flag: false,
			page: 2,
			per_page: 20,
		} );
	} );

	it( 'lets the arguments win over page and per_page keys in the query', () => {
		expect( buildParams( { page: 9, per_page: 99 }, 1, 20 ) ).toEqual( {
			page: 1,
			per_page: 20,
		} );
	} );

	it( 'sends no per_page when there is no page size', () => {
		expect( buildParams( { per_page: 99 }, 1, undefined ) ).toEqual( {
			page: 1,
		} );
	} );
} );

describe( 'normalizeTotals', () => {
	it( 'keeps whole non-negative numbers', () => {
		expect( normalizeTotals( { total: 48, pages: 3 } ) ).toEqual( {
			total: 48,
			pages: 3,
		} );
	} );

	it.each( [ NaN, -1, 1.5, undefined, '3', Infinity ] )(
		'reads %p as 0',
		( bad ) => {
			expect( normalizeTotals( { total: bad, pages: bad } ) ).toEqual( {
				total: 0,
				pages: 0,
			} );
		}
	);

	it( 'reads a missing object as zeros', () => {
		expect( normalizeTotals( undefined ) ).toEqual( {
			total: 0,
			pages: 0,
		} );
	} );
} );
