import { StrictMode } from '@wordpress/element';
import { renderHook, act } from '@testing-library/react';
import { useTableSelection } from './useTableSelection';

/**
 * Mount the hook with a render counter. `rerenderWith( ids )` passes a NEW
 * array every time -- the way a consumer's rows.map( r => r.id ) does.
 *
 * @param {Array}  initial First ids.
 * @param {Object} options renderHook options (e.g. a StrictMode wrapper).
 * @return {Object} The hook's live result plus the render counter.
 */
function mount( initial, options = {} ) {
	const count = { renders: 0 };
	const view = renderHook(
		( { ids } ) => {
			count.renders++;
			return useTableSelection( ids );
		},
		{ initialProps: { ids: initial }, ...options }
	);
	return {
		count,
		get current() {
			return view.result.current;
		},
		rerenderWith: ( ids ) => view.rerender( { ids: [ ...ids ] } ),
	};
}

describe( 'useTableSelection', () => {
	test( 'none / some / all, setAll and clear', () => {
		const s = mount( [ 1, 2, 3 ] );
		expect( s.current.state ).toBe( 'none' );
		act( () => s.current.toggle( 2 ) );
		expect( s.current.state ).toBe( 'some' );
		act( () => s.current.setAll( true ) );
		expect( s.current.state ).toBe( 'all' );
		expect( s.current.selected ).toEqual( [ 1, 2, 3 ] );
		act( () => s.current.setAll( false ) );
		expect( s.current.state ).toBe( 'none' );
		act( () => s.current.toggle( 1 ) );
		act( () => s.current.clear() );
		expect( s.current.selected ).toEqual( [] );
	} );

	test( 'toggle twice deselects; isSelected answers for this render', () => {
		const s = mount( [ 1, 2 ] );
		act( () => s.current.toggle( 1 ) );
		expect( s.current.isSelected( 1 ) ).toBe( true );
		act( () => s.current.toggle( 1 ) );
		expect( s.current.isSelected( 1 ) ).toBe( false );
	} );

	test( 'selected follows ids order', () => {
		const s = mount( [ 3, 1, 2 ] );
		act( () => s.current.toggle( 2 ) );
		act( () => s.current.toggle( 3 ) );
		expect( s.current.selected ).toEqual( [ 3, 2 ] );
	} );

	test( 'same content in a new array keeps selection and costs no extra render', () => {
		const s = mount( [ 1, 2 ] );
		act( () => s.current.toggle( 1 ) );
		const before = s.count.renders;
		s.rerenderWith( [ 1, 2 ] );
		expect( s.count.renders - before ).toBe( 1 );
		expect( s.current.selected ).toEqual( [ 1 ] );
	} );

	test( 'a key change costs exactly one restarted render', () => {
		const s = mount( [ 1, 2 ] );
		act( () => s.current.toggle( 1 ) );
		const before = s.count.renders;
		s.rerenderWith( [ 2, 3 ] );
		expect( s.count.renders - before ).toBe( 2 );
	} );

	test( 'a dropped id does not come back', () => {
		const s = mount( [ 1, 2 ] );
		act( () => s.current.toggle( 1 ) );
		s.rerenderWith( [ 2, 3 ] );
		s.rerenderWith( [ 1, 2 ] );
		expect( s.current.isSelected( 1 ) ).toBe( false );
		expect( s.current.selected ).toEqual( [] );
	} );

	test( 'keeps selection when the same ids are reordered', () => {
		const s = mount( [ 1, 2, 3 ] );
		act( () => s.current.toggle( 1 ) );
		act( () => s.current.toggle( 2 ) );
		s.rerenderWith( [ 3, 2, 1 ] );
		expect( s.current.selected ).toEqual( [ 2, 1 ] );
	} );

	test( 'drops only the ids that left', () => {
		const s = mount( [ 1, 2, 3 ] );
		act( () => s.current.setAll( true ) );
		s.rerenderWith( [ 2, 3, 4 ] );
		expect( s.current.selected ).toEqual( [ 2, 3 ] );
		expect( s.current.state ).toBe( 'some' );
	} );

	test( 'empty ids: none, and setAll selects nothing', () => {
		const s = mount( [] );
		expect( s.current.state ).toBe( 'none' );
		act( () => s.current.setAll( true ) );
		expect( s.current.state ).toBe( 'none' );
		expect( s.current.selected ).toEqual( [] );
	} );

	test( 'ignores toggle of an id not on the page', () => {
		const s = mount( [ 1, 2 ] );
		act( () => s.current.toggle( 99 ) );
		expect( s.current.selected ).toEqual( [] );
		s.rerenderWith( [ 1, 2, 99 ] );
		expect( s.current.isSelected( 99 ) ).toBe( false );
	} );

	test( 'equality is strict: a string id does not select a number id', () => {
		const s = mount( [ 1 ] );
		act( () => s.current.toggle( '1' ) );
		expect( s.current.selected ).toEqual( [] );
	} );

	test( 'same result under StrictMode', () => {
		const s = mount( [ 1, 2, 3 ], { wrapper: StrictMode } );
		act( () => s.current.toggle( 1 ) );
		act( () => s.current.toggle( 3 ) );
		s.rerenderWith( [ 2, 3 ] );
		s.rerenderWith( [ 1, 2, 3 ] );
		expect( s.current.selected ).toEqual( [ 3 ] );
	} );

	test( 'mutators keep their identity across renders and selection changes', () => {
		const s = mount( [ 1, 2 ] );
		const { toggle, setAll, clear } = s.current;
		act( () => s.current.toggle( 1 ) );
		s.rerenderWith( [ 2, 3 ] );
		expect( s.current.toggle ).toBe( toggle );
		expect( s.current.setAll ).toBe( setAll );
		expect( s.current.clear ).toBe( clear );
	} );

	test( 'setAll uses the current ids, not the first ones', () => {
		const s = mount( [ 1, 2 ] );
		s.rerenderWith( [ 5, 6 ] );
		act( () => s.current.setAll( true ) );
		expect( s.current.selected ).toEqual( [ 5, 6 ] );
	} );
} );
