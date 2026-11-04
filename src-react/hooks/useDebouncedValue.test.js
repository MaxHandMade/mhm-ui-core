import { renderHook, act } from '@testing-library/react';
import { useDebouncedValue } from './useDebouncedValue';

describe( 'useDebouncedValue', () => {
	beforeEach( () => jest.useFakeTimers() );
	afterEach( () => jest.useRealTimers() );

	const mount = ( value, delay ) =>
		renderHook(
			( props ) => useDebouncedValue( props.value, props.delay ),
			{
				initialProps: { value, delay },
			}
		);

	it( 'returns the initial value at once', () => {
		const { result } = mount( 'a' );
		expect( result.current ).toBe( 'a' );
	} );

	it( 'updates after 300 ms by default', () => {
		const { result, rerender } = mount( 'a' );
		rerender( { value: 'ab' } );
		act( () => jest.advanceTimersByTime( 299 ) );
		expect( result.current ).toBe( 'a' );
		act( () => jest.advanceTimersByTime( 1 ) );
		expect( result.current ).toBe( 'ab' );
	} );

	it( 'restarts the timer on each change', () => {
		const { result, rerender } = mount( 'a' );
		rerender( { value: 'ab' } );
		act( () => jest.advanceTimersByTime( 200 ) );
		rerender( { value: 'abc' } );
		act( () => jest.advanceTimersByTime( 200 ) );
		expect( result.current ).toBe( 'a' );
		act( () => jest.advanceTimersByTime( 100 ) );
		expect( result.current ).toBe( 'abc' );
	} );

	it( 'honours a custom delay', () => {
		const { result, rerender } = mount( 'a', 50 );
		rerender( { value: 'b', delay: 50 } );
		act( () => jest.advanceTimersByTime( 49 ) );
		expect( result.current ).toBe( 'a' );
		act( () => jest.advanceTimersByTime( 1 ) );
		expect( result.current ).toBe( 'b' );
	} );

	it( 'clears its timer on unmount', () => {
		const { rerender, unmount } = mount( 'a' );
		rerender( { value: 'ab' } );
		unmount();
		expect( jest.getTimerCount() ).toBe( 0 );
	} );

	// A function is a value too: React would call it as an initializer or
	// updater if it were handed to useState / setState directly.
	it( 'keeps a function value as it is, without calling it', () => {
		const first = jest.fn( () => 'first-called' );
		const second = jest.fn( () => 'second-called' );
		const { result, rerender } = mount( first );
		expect( result.current ).toBe( first );

		rerender( { value: second } );
		act( () => jest.advanceTimersByTime( 300 ) );
		expect( result.current ).toBe( second );
		expect( first ).not.toHaveBeenCalled();
		expect( second ).not.toHaveBeenCalled();
	} );
} );
