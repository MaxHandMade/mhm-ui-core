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
} );
