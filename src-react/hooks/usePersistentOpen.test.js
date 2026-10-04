import { act, renderHook } from '@testing-library/react';
import { usePersistentOpen } from '../index';

afterEach( () => {
	jest.restoreAllMocks();
	window.localStorage.clear();
} );

const hook = ( key, def ) =>
	renderHook( ( { k, d } ) => usePersistentOpen( k, d ), {
		initialProps: { k: key, d: def },
	} );

test( "reads '1'/'0' and falls back to the default for anything else", () => {
	window.localStorage.setItem( 'a', '1' );
	window.localStorage.setItem( 'b', '0' );
	window.localStorage.setItem( 'c', 'true' );
	expect( hook( 'a', false ).result.current[ 0 ] ).toBe( true );
	expect( hook( 'b', true ).result.current[ 0 ] ).toBe( false );
	expect( hook( 'c', false ).result.current[ 0 ] ).toBe( false );
	expect( hook( 'c', true ).result.current[ 0 ] ).toBe( true );
	expect( hook( 'missing', true ).result.current[ 0 ] ).toBe( true );
	expect( hook( 'missing', false ).result.current[ 0 ] ).toBe( false );
} );

test( 'writes on change', () => {
	const { result } = hook( 'w', true );
	act( () => result.current[ 1 ]( false ) );
	expect( result.current[ 0 ] ).toBe( false );
	expect( window.localStorage.getItem( 'w' ) ).toBe( '0' );
	act( () => result.current[ 1 ]( true ) );
	expect( result.current[ 0 ] ).toBe( true );
	expect( window.localStorage.getItem( 'w' ) ).toBe( '1' );
} );

test( 'without a key it is plain state and touches no storage', () => {
	const get = jest.spyOn( window.Storage.prototype, 'getItem' );
	const set = jest.spyOn( window.Storage.prototype, 'setItem' );
	const { result } = hook( undefined, true );
	expect( result.current[ 0 ] ).toBe( true );
	act( () => result.current[ 1 ]( false ) );
	expect( result.current[ 0 ] ).toBe( false );
	expect( [ get.mock.calls.length, set.mock.calls.length ] ).toEqual( [
		0, 0,
	] );
} );

test( 'storage that throws falls back to the default and never warns', () => {
	const warn = jest.spyOn( console, 'warn' );
	const error = jest.spyOn( console, 'error' );
	jest.spyOn( window.Storage.prototype, 'getItem' ).mockImplementation(
		() => {
			throw new Error( 'blocked' );
		}
	);
	jest.spyOn( window.Storage.prototype, 'setItem' ).mockImplementation(
		() => {
			throw new Error( 'blocked' );
		}
	);
	const { result } = hook( 'x', false );
	expect( result.current[ 0 ] ).toBe( false );
	act( () => result.current[ 1 ]( true ) );
	expect( result.current[ 0 ] ).toBe( true );
	expect( [ warn.mock.calls.length, error.mock.calls.length ] ).toEqual( [
		0, 0,
	] );
} );

test( 'a new key re-reads', () => {
	window.localStorage.setItem( 'card-narrow', '0' );
	window.localStorage.setItem( 'card-wide', '1' );
	const { result, rerender } = hook( 'card-narrow', true );
	expect( result.current[ 0 ] ).toBe( false );
	rerender( { k: 'card-wide', d: true } );
	expect( result.current[ 0 ] ).toBe( true );
	// A key with nothing stored falls back to the default it is given now.
	rerender( { k: 'card-other', d: false } );
	expect( result.current[ 0 ] ).toBe( false );
	// The setter writes to the CURRENT key.
	act( () => result.current[ 1 ]( true ) );
	expect( window.localStorage.getItem( 'card-other' ) ).toBe( '1' );
	expect( window.localStorage.getItem( 'card-wide' ) ).toBe( '1' );
	expect( window.localStorage.getItem( 'card-narrow' ) ).toBe( '0' );
} );
