import { act, render, screen } from '@testing-library/react';
import { useContainerWidth } from '../index';

// Ported from Rentiva Pro's messages.test.jsx ("thread, narrow layout"):
// a ResizeObserver stand-in whose observe() reports a width at once, and a
// handle (`notify`) to report later widths. jsdom has no layout, so the
// node's clientWidth is 0 -- "not laid out", which the hook ignores.
const realRO = window.ResizeObserver;
afterEach( () => {
	window.ResizeObserver = realRO;
} );

let notify;
let disconnects;
const observeWith = ( width ) => {
	disconnects = 0;
	window.ResizeObserver = class {
		constructor( cb ) {
			notify = cb;
		}
		observe() {
			notify( [ { contentRect: { width } } ] );
		}
		disconnect() {
			disconnects++;
		}
	};
};

function Probe( { show = true, threshold } ) {
	// No argument at all when no threshold is given, so the default is measured.
	const [ ref, layout, measured ] = useContainerWidth(
		...( threshold === undefined ? [] : [ threshold ] )
	);
	return (
		<>
			{ show && <div ref={ ref } /> }
			<output>{ layout }</output>
			<span data-testid="measured">{ String( measured ) }</span>
		</>
	);
}
const layout = () => screen.getByRole( 'status' ).textContent;

test( 'the observer attaches when the container mounts late', () => {
	observeWith( 366 );
	const { rerender } = render( <Probe show={ false } /> );
	expect( layout() ).toBe( 'wide' );
	rerender( <Probe show /> );
	expect( layout() ).toBe( 'narrow' );
} );

test( 'without ResizeObserver the wide layout is used', () => {
	delete window.ResizeObserver;
	render( <Probe /> );
	expect( layout() ).toBe( 'wide' );
} );

test( 'the threshold is inclusive: 600 is narrow, 601 is wide', () => {
	observeWith( 600 );
	render( <Probe /> );
	expect( layout() ).toBe( 'narrow' );
	act( () => notify( [ { contentRect: { width: 601 } } ] ) );
	expect( layout() ).toBe( 'wide' );
	act( () => notify( [ { contentRect: { width: 600 } } ] ) );
	expect( layout() ).toBe( 'narrow' );
} );

test( 'a custom threshold moves the line', () => {
	observeWith( 700 );
	render( <Probe threshold={ 800 } /> );
	expect( layout() ).toBe( 'narrow' );
} );

test( 'a zero width (not laid out) keeps the current layout', () => {
	observeWith( 366 );
	render( <Probe /> );
	expect( layout() ).toBe( 'narrow' );
	act( () => notify( [ { contentRect: { width: 0 } } ] ) );
	expect( layout() ).toBe( 'narrow' );
	act( () => notify( [] ) );
	expect( layout() ).toBe( 'narrow' );
} );

test( 'the observer is disconnected when the container goes away', () => {
	observeWith( 366 );
	const { rerender } = render( <Probe /> );
	expect( disconnects ).toBe( 0 );
	rerender( <Probe show={ false } /> );
	expect( disconnects ).toBe( 1 );
} );

const measured = () => screen.getByTestId( 'measured' ).textContent;

test( 'measured is false before the first measurement and true after', () => {
	observeWith( 366 );
	const { rerender } = render( <Probe show={ false } /> );
	expect( [ layout(), measured() ] ).toEqual( [ 'wide', 'false' ] );
	rerender( <Probe show /> );
	expect( [ layout(), measured() ] ).toEqual( [ 'narrow', 'true' ] );
} );

test( 'no ResizeObserver: measured true, wide', () => {
	delete window.ResizeObserver;
	render( <Probe /> );
	expect( [ layout(), measured() ] ).toEqual( [ 'wide', 'true' ] );
} );

test( '0 width keeps measured false', () => {
	observeWith( 0 );
	render( <Probe /> );
	expect( [ layout(), measured() ] ).toEqual( [ 'wide', 'false' ] );
	act( () => notify( [ { contentRect: { width: 900 } } ] ) );
	expect( [ layout(), measured() ] ).toEqual( [ 'wide', 'true' ] );
} );

test( 'a two-value caller keeps working', () => {
	observeWith( 366 );
	function Two() {
		const [ ref, l ] = useContainerWidth();
		return (
			<>
				<div ref={ ref } />
				<output>{ l }</output>
			</>
		);
	}
	render( <Two /> );
	expect( layout() ).toBe( 'narrow' );
} );
