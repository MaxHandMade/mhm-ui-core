import { render, screen, fireEvent } from '@testing-library/react';
import { PageHeader, Tabs } from '../index';

/**
 * Tabs and PageHeader each carry their own copy of isPlainLeftClick (audit of
 * the 0.15.0 branch, M-7). Moving it into one module is new import surface --
 * a minor bump by the house rule -- so it waits for 0.16.0. Until then this
 * table holds the two copies to one behaviour: a drift in either goes red.
 */
const CLICKS = [
	[ 'plain left', { button: 0 }, true ],
	[ 'ctrl', { button: 0, ctrlKey: true }, false ],
	[ 'meta', { button: 0, metaKey: true }, false ],
	[ 'shift', { button: 0, shiftKey: true }, false ],
	[ 'alt', { button: 0, altKey: true }, false ],
	[ 'middle', { button: 1 }, false ],
];

const SURFACES = [
	[
		'Tabs',
		( spy ) =>
			render(
				<Tabs
					label="S"
					items={ [ { id: 'a', label: 'A', href: '?a' } ] }
					onSelect={ spy }
				/>
			),
	],
	[
		'PageHeader',
		( spy ) =>
			render(
				<PageHeader
					title="T"
					back={ { label: 'Back', href: '?b', onClick: spy } }
				/>
			),
	],
];

describe.each( SURFACES )(
	'%s intercepts only a plain left click',
	( _name, mount ) => {
		test.each( CLICKS )( '%s click', ( _click, init, intercepted ) => {
			const spy = jest.fn();
			mount( spy );
			const notPrevented = fireEvent.click(
				screen.getByRole( 'link' ),
				init
			);
			expect( [ notPrevented, spy.mock.calls.length ] ).toEqual( [
				! intercepted,
				intercepted ? 1 : 0,
			] );
		} );
	}
);
