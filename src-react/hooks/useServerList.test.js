import { StrictMode, useEffect } from '@wordpress/element';
import { renderHook, act } from '@testing-library/react';
import {
	useServerList,
	pageRange,
	buildParams,
	normalizeTotals,
} from './useServerList';

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

// ---------------------------------------------------------------------------
// useServerList (spec v2.2 §3.1, tests §6). Numbers in the test names refer
// to the spec's test list.
// ---------------------------------------------------------------------------

const deferred = () => {
	let resolve;
	let reject;
	const promise = new Promise( ( res, rej ) => {
		resolve = res;
		reject = rej;
	} );
	return { promise, resolve, reject };
};

// Every call records its params and gets a promise the test settles by hand.
const makeFetch = () => {
	const calls = [];
	const fetch = jest.fn( ( params ) => {
		const d = deferred();
		calls.push( { params, d } );
		return d.promise;
	} );
	return { calls, fetch };
};

const page = ( pages, total = pages * 20, items = 'rows' ) => ( {
	items,
	total,
	pages,
} );

const keyOf = ( call ) => `${ call.params.status }/${ call.params.page }`;

const settle = ( call, value ) =>
	act( async () => {
		call.d.resolve( value );
	} );

const fail = ( call, reason ) =>
	act( async () => {
		call.d.reject( reason );
	} );

// Renders the hook and logs every render's { loading, page, data } so a test
// can assert that no in-between commit dropped loading.
const mountList = ( fetch, options = {}, extra ) => {
	const log = [];
	const view = renderHook(
		( props ) => {
			const list = useServerList( props.fetch, props.options );
			if ( extra ) {
				extra( list );
			}
			log.push( {
				loading: list.loading,
				page: list.page,
				data: list.data,
			} );
			return list;
		},
		{ initialProps: { fetch, options } }
	);
	return { ...view, log };
};

const ALL = { initialQuery: { search: '', status: 'all' }, perPage: 20 };

// The documented consumer pattern: a debounce effect that runs on mount.
const useMountPatch = ( list ) => {
	const { setQuery } = list;
	useEffect( () => {
		setQuery( { search: '' } );
	}, [ setQuery ] );
};

describe( 'useServerList', () => {
	it( '1: sends one request on mount and none after it is accepted', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, {
			initialQuery: { search: '', status: 'all', page: 9 },
			perPage: 20,
		} );

		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ].params ).toEqual( {
			status: 'all',
			page: 1,
			per_page: 20,
		} );
		expect( result.current.query ).toEqual( { search: '', status: 'all' } );

		await settle( calls[ 0 ], page( 3 ) );
		expect( calls ).toHaveLength( 1 );
		expect( result.current.loading ).toBe( false );
		expect( result.current.data ).toEqual( page( 3 ) );
	} );

	it( '2: resets to page one with a single request when the query changes', () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );

		act( () => result.current.setPage( 3 ) );
		act( () => result.current.setQuery( { status: 'x' } ) );

		expect( calls.map( keyOf ) ).toEqual( [ 'all/1', 'all/3', 'x/1' ] );
		expect( result.current.page ).toBe( 1 );
	} );

	it( '3: treats an equal patch as a no-op', () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL, useMountPatch );

		expect( calls ).toHaveLength( 1 );

		act( () => result.current.setPage( 3 ) );
		const setQuery = result.current.setQuery;
		act( () => result.current.setQuery( { status: 'all' } ) );

		expect( calls ).toHaveLength( 2 );
		expect( result.current.page ).toBe( 3 );
		expect( result.current.setQuery ).toBe( setQuery );
	} );

	it( '4: keeps the newest answer when answers arrive out of order', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		act( () => result.current.setPage( 2 ) );

		await settle( calls[ 0 ], page( 3, 60, 'one' ) );
		expect( result.current.loading ).toBe( true );
		expect( result.current.data ).toBeNull();

		await settle( calls[ 1 ], page( 3, 60, 'two' ) );
		expect( result.current.data.items ).toBe( 'two' );
		expect( result.current.loading ).toBe( false );
	} );

	it( '4: ignores an older rejection', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		act( () => result.current.setPage( 2 ) );

		await settle( calls[ 1 ], page( 3, 60, 'two' ) );
		await fail( calls[ 0 ], new Error( 'late' ) );
		expect( result.current.error ).toBeNull();
		expect( result.current.data.items ).toBe( 'two' );
	} );

	describe( '5: an old answer settling in the same batch as a new query', () => {
		it( 'does not clamp the new query or show the old rows', async () => {
			const { calls, fetch } = makeFetch();
			const { result, log } = mountList( fetch, ALL );
			await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
			act( () => result.current.setPage( 3 ) );
			const mark = log.length;

			await act( async () => {
				result.current.setQuery( { status: 'b' } );
				calls[ 1 ].d.resolve( page( 2, 30, 'STALE' ) );
				await Promise.resolve();
			} );

			const keys = calls.map( keyOf );
			expect( keys ).not.toContain( 'b/2' );
			expect( keys ).toContain( 'b/1' );
			expect( result.current.data.items ).toBe( 'A1' );
			expect( result.current.error ).toBeNull();
			expect( log.slice( mark ).every( ( r ) => r.loading ) ).toBe(
				true
			);

			await settle( calls[ calls.length - 1 ], page( 1, 5, 'B1' ) );
			expect( result.current.data.items ).toBe( 'B1' );
			expect( result.current.loading ).toBe( false );
		} );

		it.each( [ 'resolve', 'reject' ] )(
			'drops a refetch answer that settles (%s) with the new query',
			async ( how ) => {
				const { calls, fetch } = makeFetch();
				const { result, log } = mountList( fetch, ALL );
				await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
				act( () => result.current.refetch() );
				const mark = log.length;

				await act( async () => {
					result.current.setQuery( { status: 'b' } );
					if ( how === 'resolve' ) {
						calls[ 1 ].d.resolve( page( 5, 100, 'STALE' ) );
					} else {
						calls[ 1 ].d.reject( new Error( 'STALE' ) );
					}
					await Promise.resolve();
				} );

				expect( result.current.data.items ).toBe( 'A1' );
				expect( result.current.error ).toBeNull();
				expect( result.current.loading ).toBe( true );
				expect( log.slice( mark ).every( ( r ) => r.loading ) ).toBe(
					true
				);
			}
		);
	} );

	it( "6: ignores a stale answer's page count", async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5 ) );
		act( () => result.current.setPage( 2 ) );
		act( () => result.current.setPage( 3 ) );

		await settle( calls[ 1 ], page( 1 ) );
		expect( result.current.page ).toBe( 3 );
		expect( calls ).toHaveLength( 3 );

		await settle( calls[ 2 ], page( 5 ) );
		expect( result.current.page ).toBe( 3 );
		expect( calls ).toHaveLength( 3 );
	} );

	it( '7: pulls back to the last page and keeps loading while it does', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
		act( () => result.current.setPage( 3 ) );

		await settle( calls[ 1 ], page( 2, 30, 'P3' ) );
		expect( calls ).toHaveLength( 3 );
		expect( calls[ 2 ].params.page ).toBe( 2 );
		expect( result.current.page ).toBe( 2 );
		expect( result.current.loading ).toBe( true );
		expect( result.current.data.items ).toBe( 'A1' );

		await settle( calls[ 2 ], page( 2, 30, 'P2' ) );
		expect( result.current.loading ).toBe( false );
		expect( result.current.data.items ).toBe( 'P2' );
		expect( calls ).toHaveLength( 3 );
	} );

	it( '7a: zero pages on mount sends one request', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 0, 0 ) );
		expect( calls ).toHaveLength( 1 );
		expect( result.current.page ).toBe( 1 );
		expect( result.current.totalPages ).toBe( 1 );
		expect( result.current.loading ).toBe( false );
	} );

	it( '7b: zero pages on page three pulls back to one', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5 ) );
		act( () => result.current.setPage( 3 ) );

		await settle( calls[ 1 ], page( 0, 0 ) );
		expect( calls ).toHaveLength( 3 );
		expect( calls[ 2 ].params.page ).toBe( 1 );

		await settle( calls[ 2 ], page( 0, 0 ) );
		expect( calls ).toHaveLength( 3 );
		expect( result.current.page ).toBe( 1 );
		expect( result.current.totalPages ).toBe( 1 );
		expect( result.current.loading ).toBe( false );
	} );

	it( '8: writes nothing after unmount', async () => {
		const { calls, fetch } = makeFetch();
		const readTotals = jest.fn( ( r ) => r );
		const { unmount } = mountList( fetch, { ...ALL, readTotals } );
		unmount();
		await settle( calls[ 0 ], page( 3 ) );
		expect( readTotals ).not.toHaveBeenCalled();
	} );

	it( '10: reads total and pages by default, or through readTotals', async () => {
		const a = makeFetch();
		const def = mountList( a.fetch, ALL );
		await settle( a.calls[ 0 ], { total: 48, pages: 3 } );
		expect( def.result.current.total ).toBe( 48 );
		expect( def.result.current.totalPages ).toBe( 3 );

		const b = makeFetch();
		const own = mountList( b.fetch, {
			...ALL,
			readTotals: ( r ) => ( { total: r.total, pages: r.total_pages } ),
		} );
		await settle( b.calls[ 0 ], { total: 48, total_pages: 3 } );
		expect( own.result.current.totalPages ).toBe( 3 );

		const c = makeFetch();
		const bad = mountList( c.fetch, ALL );
		await settle( c.calls[ 0 ], { total: NaN, pages: -1 } );
		expect( bad.result.current.total ).toBe( 0 );
		expect( bad.result.current.totalPages ).toBe( 1 );
	} );

	it( '11: uses the latest committed fetchPage and readTotals without extra requests', async () => {
		const a = makeFetch();
		const b = makeFetch();
		const { result, rerender } = mountList( a.fetch, ALL );
		rerender( { fetch: b.fetch, options: { ...ALL } } );
		rerender( {
			fetch: b.fetch,
			options: { ...ALL, readTotals: () => ( { total: 7, pages: 1 } ) },
		} );
		expect( a.calls ).toHaveLength( 1 );
		expect( b.calls ).toHaveLength( 0 );

		act( () => result.current.refetch() );
		expect( a.calls ).toHaveLength( 1 );
		expect( b.calls ).toHaveLength( 1 );

		await settle( b.calls[ 0 ], page( 3 ) );
		expect( result.current.total ).toBe( 7 );
	} );

	it( '12: ignores invalid pages', () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		[ 0, -1, 1.5, NaN, Infinity, '2' ].forEach( ( n ) =>
			act( () => result.current.setPage( n ) )
		);
		expect( calls ).toHaveLength( 1 );
		expect( result.current.page ).toBe( 1 );
	} );

	it( '13: derives range from the accepted page', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		expect( result.current.range ).toBeNull();

		await settle( calls[ 0 ], page( 3, 48 ) );
		expect( result.current.range ).toEqual( {
			from: 1,
			to: 20,
			total: 48,
		} );

		act( () => result.current.setPage( 3 ) );
		expect( result.current.range ).toEqual( {
			from: 1,
			to: 20,
			total: 48,
		} );

		await settle( calls[ 1 ], page( 3, 48 ) );
		expect( result.current.range ).toEqual( {
			from: 41,
			to: 48,
			total: 48,
		} );

		const other = makeFetch();
		const noSize = mountList( other.fetch, { initialQuery: {} } );
		await settle( other.calls[ 0 ], page( 3, 48 ) );
		expect( noSize.result.current.range ).toBeNull();
		expect( other.calls[ 0 ].params ).toEqual( { page: 1 } );
	} );

	it.each( [ 'resolve', 'reject' ] )(
		'14: accepts only the second mount request under StrictMode (first %s)',
		async ( how ) => {
			const { calls, fetch } = makeFetch();
			const { result } = renderHook( () => useServerList( fetch, ALL ), {
				wrapper: StrictMode,
			} );
			expect( calls ).toHaveLength( 2 );

			if ( how === 'resolve' ) {
				await settle( calls[ 0 ], page( 3, 60, 'first' ) );
			} else {
				await fail( calls[ 0 ], new Error( 'first' ) );
			}
			expect( result.current.data ).toBeNull();
			expect( result.current.error ).toBeNull();
			expect( result.current.loading ).toBe( true );

			await settle( calls[ 1 ], page( 3, 60, 'second' ) );
			expect( result.current.data.items ).toBe( 'second' );
			expect( result.current.loading ).toBe( false );
		}
	);

	it( '15: keeps setQuery, setPage and refetch stable', async () => {
		const { calls, fetch } = makeFetch();
		const { result, rerender } = mountList( fetch, ALL );
		const { setQuery, setPage, refetch } = result.current;
		await settle( calls[ 0 ], page( 3 ) );
		rerender( { fetch, options: { ...ALL } } );
		expect( result.current.setQuery ).toBe( setQuery );
		expect( result.current.setPage ).toBe( setPage );
		expect( result.current.refetch ).toBe( refetch );
		expect( result.current.pagination.onChange ).toBe( setPage );
	} );

	it( '19: raises loading from the first commit after a settled state', async () => {
		const { calls, fetch } = makeFetch();
		const { result, log } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 3 ) );
		expect( result.current.loading ).toBe( false );
		const mark = log.length;

		act( () => result.current.setPage( 2 ) );
		expect( log.length ).toBeGreaterThan( mark );
		expect( log.slice( mark ).every( ( r ) => r.loading ) ).toBe( true );

		await settle( calls[ 1 ], page( 3 ) );
		expect( result.current.loading ).toBe( false );
	} );

	it( '20: a batch that returns to the start page still completes', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5 ) );

		act( () => {
			result.current.setPage( 3 );
			result.current.setPage( 1 );
		} );
		expect( calls ).toHaveLength( 2 );
		expect( calls[ 1 ].params.page ).toBe( 1 );

		await settle( calls[ 1 ], page( 5 ) );
		expect( result.current.loading ).toBe( false );
	} );

	it( '21: keeps the accepted page size for range', async () => {
		const { calls, fetch } = makeFetch();
		const { result, rerender } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5, 100 ) );
		act( () => result.current.setPage( 3 ) );
		await settle( calls[ 1 ], page( 5, 100 ) );
		expect( result.current.range ).toEqual( {
			from: 41,
			to: 60,
			total: 100,
		} );

		rerender( { fetch, options: { ...ALL, perPage: 10 } } );
		expect( calls ).toHaveLength( 2 );
		expect( result.current.range ).toEqual( {
			from: 41,
			to: 60,
			total: 100,
		} );

		act( () => result.current.refetch() );
		expect( calls[ 2 ].params.per_page ).toBe( 10 );
		await settle( calls[ 2 ], page( 10, 100 ) );
		expect( result.current.range ).toEqual( {
			from: 21,
			to: 30,
			total: 100,
		} );
	} );

	// Review Focus (plan).
	it( 'RF1: drops a refetch answer once the query moved on', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
		act( () => result.current.refetch() );
		act( () => result.current.setQuery( { status: 'b' } ) );

		await settle( calls[ 1 ], page( 5, 100, 'STALE' ) );
		expect( result.current.data.items ).toBe( 'A1' );
		expect( result.current.loading ).toBe( true );
	} );

	it( 'RF2: refetch after a failure clears error and accepts the new data', async () => {
		const { calls, fetch } = makeFetch();
		const boom = new Error( 'boom' );
		const { result } = mountList( fetch, ALL );
		await fail( calls[ 0 ], boom );
		expect( result.current.error ).toBe( boom );
		expect( result.current.loading ).toBe( false );

		act( () => result.current.refetch() );
		expect( result.current.error ).toBeNull();
		expect( result.current.loading ).toBe( true );

		await settle( calls[ 1 ], page( 1, 5, 'ok' ) );
		expect( result.current.data.items ).toBe( 'ok' );
		expect( result.current.error ).toBeNull();
	} );

	it( 'RF2: a rejection keeps the rows on screen', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
		act( () => result.current.setPage( 2 ) );
		await fail( calls[ 1 ], new Error( 'boom' ) );
		expect( result.current.data.items ).toBe( 'A1' );
		expect( result.current.range ).toEqual( {
			from: 1,
			to: 20,
			total: 100,
		} );
	} );

	it( 'RF3: ignores page and per_page keys in a patch', () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		act( () => result.current.setPage( 3 ) );
		act( () =>
			result.current.setQuery( { page: 2, per_page: 99, status: 'x' } )
		);
		expect( result.current.page ).toBe( 1 );
		expect( result.current.query ).toEqual( { search: '', status: 'x' } );
		expect( calls[ calls.length - 1 ].params ).toEqual( {
			status: 'x',
			page: 1,
			per_page: 20,
		} );
	} );

	it( 'RF4: two patches in one act send one request', () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		act( () => {
			result.current.setQuery( { status: 'x' } );
			result.current.setQuery( { search: 'y' } );
		} );
		expect( calls ).toHaveLength( 2 );
		expect( calls[ 1 ].params ).toEqual( {
			search: 'y',
			status: 'x',
			page: 1,
			per_page: 20,
		} );
	} );

	it( 'RF5: a throwing readTotals is treated as a rejection', async () => {
		const { calls, fetch } = makeFetch();
		const oops = new Error( 'bad shape' );
		let shouldThrow = false;
		const readTotals = ( r ) => {
			if ( shouldThrow ) {
				throw oops;
			}
			return r;
		};
		const { result } = mountList( fetch, { ...ALL, readTotals } );
		await settle( calls[ 0 ], page( 5, 100, 'A1' ) );

		shouldThrow = true;
		act( () => result.current.refetch() );
		await settle( calls[ 1 ], page( 5, 100, 'NEW' ) );
		expect( result.current.error ).toBe( oops );
		expect( result.current.data.items ).toBe( 'A1' );
		expect( result.current.loading ).toBe( false );
	} );

	it( 'exposes pagination ready for the kit Pagination', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 4 ) );
		expect( result.current.pagination ).toEqual( {
			page: 1,
			totalPages: 4,
			onChange: result.current.setPage,
		} );
	} );
} );

// Branch review (Fable + Codex, 0.20.0): mutation classes the first suite
// did not kill.
describe( 'useServerList — review additions', () => {
	it( 'X1: a request in the same commit as a new fetchPage uses the new one', async () => {
		const a = makeFetch();
		const b = makeFetch();
		const { result, rerender } = mountList( a.fetch, ALL );
		await settle( a.calls[ 0 ], page( 5 ) );

		act( () => {
			rerender( { fetch: b.fetch, options: { ...ALL, perPage: 10 } } );
			result.current.setPage( 2 );
		} );
		expect( a.calls ).toHaveLength( 1 );
		expect( b.calls ).toHaveLength( 1 );
		expect( b.calls[ 0 ].params.per_page ).toBe( 10 );
	} );

	it( 'X2: a synchronously throwing fetchPage becomes the error', async () => {
		const boom = new Error( 'sync' );
		const { result } = mountList( () => {
			throw boom;
		}, ALL );
		await act( async () => {} );
		expect( result.current.error ).toBe( boom );
		expect( result.current.loading ).toBe( false );
	} );

	it( 'X3: setPage to the current page is a no-op', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5 ) );
		act( () => result.current.setPage( 1 ) );
		expect( calls ).toHaveLength( 1 );
		expect( result.current.loading ).toBe( false );
	} );

	it( 'X4: readTotals and perPage are the ones at request start', async () => {
		const { calls, fetch } = makeFetch();
		const { result, rerender } = mountList( fetch, ALL );
		rerender( {
			fetch,
			options: {
				...ALL,
				perPage: 10,
				readTotals: () => ( { total: 1, pages: 1 } ),
			},
		} );
		await settle( calls[ 0 ], page( 5, 100 ) );
		expect( result.current.total ).toBe( 100 );
		expect( result.current.range ).toEqual( {
			from: 1,
			to: 20,
			total: 100,
		} );
	} );

	it( 'reads totals once per accepted answer under StrictMode', async () => {
		const { calls, fetch } = makeFetch();
		const readTotals = jest.fn( ( r ) => r );
		renderHook( () => useServerList( fetch, { ...ALL, readTotals } ), {
			wrapper: StrictMode,
		} );
		await settle( calls[ 1 ], page( 3 ) );
		expect( readTotals ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'rule 12: a failed pull-back keeps the rows it has and shows the error', async () => {
		const { calls, fetch } = makeFetch();
		const boom = new Error( 'boom' );
		const { result } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
		act( () => result.current.setPage( 3 ) );
		await settle( calls[ 1 ], page( 5, 100, 'P3' ) );
		act( () => result.current.setPage( 4 ) );

		await settle( calls[ 2 ], page( 2, 30, 'short' ) );
		expect( calls[ 3 ].params.page ).toBe( 2 );
		await fail( calls[ 3 ], boom );

		expect( result.current.page ).toBe( 2 );
		expect( result.current.data.items ).toBe( 'P3' );
		expect( result.current.range ).toEqual( {
			from: 41,
			to: 60,
			total: 100,
		} );
		expect( result.current.error ).toBe( boom );
		expect( result.current.loading ).toBe( false );
	} );

	it( '4: keeps the newest answer when the newest settles first', async () => {
		const { calls, fetch } = makeFetch();
		const { result } = mountList( fetch, ALL );
		act( () => result.current.setPage( 2 ) );

		await settle( calls[ 1 ], page( 3, 60, 'two' ) );
		await settle( calls[ 0 ], page( 3, 60, 'one' ) );
		expect( result.current.data.items ).toBe( 'two' );
		expect( result.current.page ).toBe( 2 );
	} );

	it( '7: loading stays up on every commit from the pull-back to its answer', async () => {
		const { calls, fetch } = makeFetch();
		const { result, log } = mountList( fetch, ALL );
		await settle( calls[ 0 ], page( 5, 100, 'A1' ) );
		act( () => result.current.setPage( 3 ) );
		const mark = log.length;

		await settle( calls[ 1 ], page( 2, 30, 'P3' ) );
		const between = log.slice( mark );
		expect( between.length ).toBeGreaterThan( 0 );
		expect( between.every( ( r ) => r.loading ) ).toBe( true );
		expect( between.every( ( r ) => r.data.items === 'A1' ) ).toBe( true );
	} );
} );
