import { renderHook, waitFor, act } from '@testing-library/react';
import { useApi } from './useApi';

describe( 'useApi', () => {
	it( 'starts in loading when no initial data was supplied', () => {
		const { result } = renderHook( () =>
			useApi( () => new Promise( () => {} ) )
		);

		expect( result.current.loading ).toBe( true );
		expect( result.current.data ).toBeNull();
	} );

	it( 'exposes pre-populated data immediately', () => {
		const { result } = renderHook( () =>
			useApi( () => new Promise( () => {} ), { rows: [] } )
		);

		expect( result.current.data ).toEqual( { rows: [] } );
	} );

	// This assertion used to say the opposite, and said so on purpose: the defect was
	// carried over from Lite unchanged so that moving the hook into this package could
	// not be blamed for a behaviour change. That was the deal, and this is the round
	// that pays it off.
	//
	// useState() already started loading at false when initialData was supplied; the
	// mount effect then raised it unconditionally, so the "avoid first-paint spinner"
	// promise in the doc block stopped holding one frame in.
	it( 'keeps the spinner down on first paint when initial data was supplied', () => {
		const { result } = renderHook( () =>
			useApi( () => new Promise( () => {} ), { rows: [] } )
		);

		expect( result.current.loading ).toBe( false );
		expect( result.current.data ).toEqual( { rows: [] } );
	} );

	// The control that stops the cheap fix. "Never raise loading" would satisfy the
	// assertion above and quietly remove every spinner in the product: a refetch has
	// nothing on screen to keep showing, so it must raise loading like any other run.
	it( 'raises loading on a refetch, even when it started primed', async () => {
		let calls = 0;
		let releaseSecond;
		const { result } = renderHook( () =>
			useApi(
				() => {
					calls += 1;
					return calls === 1
						? Promise.resolve( 'first' )
						: new Promise( ( resolve ) => {
								releaseSecond = resolve;
						  } );
				},
				{ rows: [] }
			)
		);

		await waitFor( () => expect( result.current.data ).toBe( 'first' ) );
		expect( result.current.loading ).toBe( false );

		act( () => result.current.refetch() );

		expect( result.current.loading ).toBe( true );

		releaseSecond( 'second' );
		await waitFor( () => expect( result.current.loading ).toBe( false ) );
		expect( result.current.data ).toBe( 'second' );
	} );

	// The other control: with no initial data there is nothing to show, so the spinner
	// must still come up on the very first run.
	it( 'still raises loading on first paint when nothing was pre-populated', () => {
		const { result } = renderHook( () =>
			useApi( () => new Promise( () => {} ), null )
		);

		expect( result.current.loading ).toBe( true );
	} );

	it( 'resolves data and clears loading', async () => {
		const { result } = renderHook( () =>
			useApi( () => Promise.resolve( 'payload' ) )
		);

		await waitFor( () => expect( result.current.loading ).toBe( false ) );
		expect( result.current.data ).toBe( 'payload' );
		expect( result.current.error ).toBeNull();
	} );

	it( 'captures a rejection as error and still clears loading', async () => {
		const boom = new Error( 'boom' );
		const { result } = renderHook( () =>
			useApi( () => Promise.reject( boom ) )
		);

		await waitFor( () => expect( result.current.loading ).toBe( false ) );
		expect( result.current.error ).toBe( boom );
	} );

	it( 'refetch runs the fetcher again', async () => {
		let n = 0;
		const { result } = renderHook( () =>
			useApi( () => Promise.resolve( ++n ) )
		);

		await waitFor( () => expect( result.current.data ).toBe( 1 ) );

		act( () => result.current.refetch() );

		await waitFor( () => expect( result.current.data ).toBe( 2 ) );
	} );

	it( 'refetches when a declared dependency changes', async () => {
		let n = 0;
		const { result, rerender } = renderHook(
			( { filter } ) =>
				useApi( () => Promise.resolve( `${ filter }-${ ++n }` ), null, [
					filter,
				] ),
			{ initialProps: { filter: 'all' } }
		);

		await waitFor( () => expect( result.current.data ).toBe( 'all-1' ) );

		rerender( { filter: 'pending' } );

		await waitFor( () =>
			expect( result.current.data ).toBe( 'pending-2' )
		);
	} );
	// 0.20.0: a request superseded by a deps change or a refetch must not
	// write state. Each test resolves the OLD promise last -- the order a slow
	// network produces -- and only the newest request may land.
	describe( 'a superseded request', () => {
		const deferred = () => {
			let resolve;
			let reject;
			const promise = new Promise( ( res, rej ) => {
				resolve = res;
				reject = rej;
			} );
			return { promise, resolve, reject };
		};

		const byPage = () => {
			const pending = {};
			const fetchFor = ( page ) => {
				pending[ page ] = deferred();
				return pending[ page ].promise;
			};
			return { pending, fetchFor };
		};

		it( "keeps the latest deps' data when an older request resolves last", async () => {
			const { pending, fetchFor } = byPage();
			const { result, rerender } = renderHook(
				( { page } ) =>
					useApi( () => fetchFor( page ), null, [ page ] ),
				{ initialProps: { page: 1 } }
			);
			rerender( { page: 2 } );

			await act( async () => pending[ 2 ].resolve( 'two' ) );
			expect( result.current.data ).toBe( 'two' );
			expect( result.current.loading ).toBe( false );

			await act( async () => pending[ 1 ].resolve( 'one' ) );
			expect( result.current.data ).toBe( 'two' );
			expect( result.current.loading ).toBe( false );
		} );

		it( 'does not let an older request lower loading', async () => {
			const { pending, fetchFor } = byPage();
			const { result, rerender } = renderHook(
				( { page } ) =>
					useApi( () => fetchFor( page ), null, [ page ] ),
				{ initialProps: { page: 1 } }
			);
			rerender( { page: 2 } );

			await act( async () => pending[ 1 ].resolve( 'one' ) );
			expect( result.current.loading ).toBe( true );
			expect( result.current.data ).toBeNull();

			await act( async () => pending[ 2 ].resolve( 'two' ) );
			expect( result.current.loading ).toBe( false );
		} );

		it( 'drops the answer of a request superseded by refetch', async () => {
			const calls = [];
			const { result } = renderHook( () =>
				useApi( () => {
					calls.push( deferred() );
					return calls[ calls.length - 1 ].promise;
				} )
			);
			act( () => result.current.refetch() );
			expect( calls ).toHaveLength( 2 );

			await act( async () => calls[ 1 ].resolve( 'new' ) );
			await act( async () => calls[ 0 ].resolve( 'old' ) );
			expect( result.current.data ).toBe( 'new' );
		} );

		it( 'ignores an older rejection', async () => {
			const { pending, fetchFor } = byPage();
			const { result, rerender } = renderHook(
				( { page } ) =>
					useApi( () => fetchFor( page ), null, [ page ] ),
				{ initialProps: { page: 1 } }
			);
			rerender( { page: 2 } );

			await act( async () => pending[ 2 ].resolve( 'two' ) );
			await act( async () => pending[ 1 ].reject( new Error( 'late' ) ) );
			expect( result.current.error ).toBeNull();
			expect( result.current.data ).toBe( 'two' );
		} );
	} );
} );
