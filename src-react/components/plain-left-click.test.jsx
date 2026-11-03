import { render, screen, fireEvent } from '@testing-library/react';
import { PageHeader, Tabs } from '../index';

/**
 * Tabs and PageHeader share one isPlainLeftClick (src-react/plain-left-click.js,
 * 0.18.0; until then each carried its own copy, audit M-7). The table is kept
 * on the two SURFACES, not the helper: a component that stops calling it, or
 * calls something else, goes red here.
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
