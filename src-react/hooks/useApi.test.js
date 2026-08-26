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

	// KNOWN DEFECT, carried over from Lite on purpose: this move must not change what the
	// screens do. useState() starts loading at false when initialData was supplied, but the
	// mount effect calls setLoading( true ) unconditionally, so the "avoid first-paint
	// spinner" promise in the doc block stops holding one frame in. Fixing it is a
	// behaviour change across 4 Lite screens and Pro, and belongs to its own round.
	it( 'still flips to loading after mount even with initial data (known defect)', () => {
		const { result } = renderHook( () =>
			useApi( () => new Promise( () => {} ), { rows: [] } )
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
