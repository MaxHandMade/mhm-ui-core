import { render, screen, fireEvent } from '@testing-library/react';
import SortHeader, { nextSort } from './SortHeader';

const head = ( sort, onSort = () => {}, extra = {} ) =>
	render(
		<table>
			<thead>
				<tr>
					<SortHeader
						label="Customer"
						sortKey="name"
						sort={ sort }
						onSort={ onSort }
						{ ...extra }
					/>
					<SortHeader
						label="Total spent"
						sortKey="spent"
						sort={ sort }
						onSort={ onSort }
						align="end"
					/>
				</tr>
			</thead>
		</table>
	);

const th = ( name ) => screen.getByRole( 'button', { name } ).closest( 'th' );

describe( 'SortHeader', () => {
	test( 'aria-sort only on the active column', () => {
		head( { key: 'name', direction: 'asc' } );
		expect( th( 'Customer' ).getAttribute( 'aria-sort' ) ).toBe(
			'ascending'
		);
		expect( th( 'Total spent' ).hasAttribute( 'aria-sort' ) ).toBe( false );
	} );

	test( 'descending is announced as descending', () => {
		head( { key: 'spent', direction: 'desc' } );
		expect( th( 'Total spent' ).getAttribute( 'aria-sort' ) ).toBe(
			'descending'
		);
		expect( th( 'Customer' ).hasAttribute( 'aria-sort' ) ).toBe( false );
	} );

	test( 'null sort: no header carries aria-sort', () => {
		head( null );
		expect( th( 'Customer' ).hasAttribute( 'aria-sort' ) ).toBe( false );
		expect( th( 'Total spent' ).hasAttribute( 'aria-sort' ) ).toBe( false );
	} );

	test( 'click calls onSort with sortKey', () => {
		const onSort = jest.fn();
		head( null, onSort );
		fireEvent.click( screen.getByRole( 'button', { name: 'Customer' } ) );
		expect( onSort ).toHaveBeenCalledTimes( 1 );
		expect( onSort ).toHaveBeenCalledWith( 'name' );
	} );

	test( 'button is type=button, header is scope=col', () => {
		head( null );
		const button = screen.getByRole( 'button', { name: 'Customer' } );
		expect( button.getAttribute( 'type' ) ).toBe( 'button' );
		expect( th( 'Customer' ).getAttribute( 'scope' ) ).toBe( 'col' );
	} );

	test( 'className and align land on the th', () => {
		head( null, () => {}, { className: 'rv-cust-th', align: 'end' } );
		const cls = th( 'Customer' ).className.split( ' ' );
		expect( cls ).toEqual(
			expect.arrayContaining( [
				'mhmui-table__th',
				'mhmui-table__th--sortable',
				'mhmui-table__th--end',
				'rv-cust-th',
			] )
		);
	} );

	test( 'the icon is decoration only', () => {
		head( { key: 'name', direction: 'asc' } );
		const svg = th( 'Customer' ).querySelector( 'svg' );
		expect( svg.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( svg.getAttribute( 'focusable' ) ).toBe( 'false' );
	} );
} );

describe( 'nextSort', () => {
	test.each( [
		[ null, 'a', undefined, { key: 'a', direction: 'asc' } ],
		[ null, 'a', 'desc', { key: 'a', direction: 'desc' } ],
		[
			{ key: 'a', direction: 'asc' },
			'a',
			undefined,
			{ key: 'a', direction: 'desc' },
		],
		[
			{ key: 'a', direction: 'desc' },
			'a',
			'desc',
			{ key: 'a', direction: 'asc' },
		],
		[
			{ key: 'a', direction: 'asc' },
			'b',
			'desc',
			{ key: 'b', direction: 'desc' },
		],
	] )( '%j + %s (first %s) -> %j', ( sort, key, first, expected ) => {
		expect( nextSort( sort, key, first ) ).toEqual( expected );
	} );
} );
