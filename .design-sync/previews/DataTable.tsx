import { useState } from 'react';
import { DataTable, SortHeader, SelectAll, RowSelect, Pagination, StatusBadge, nextSort } from '@mhm/ui-core';

const ROWS = [
	{ id: 1, name: 'Ayşe Yılmaz', email: 'ayse@example.com', bookings: 12, spent: '₺18,400', status: [ 'Active', 'success' ] },
	{ id: 2, name: 'Mehmet Demir', email: 'mehmet@example.com', bookings: 4, spent: '₺5,250', status: [ 'Active', 'success' ] },
	{ id: 3, name: 'Elif Kaya', email: 'elif@example.com', bookings: 1, spent: '₺980', status: [ 'Pending', 'warning' ] },
	{ id: 4, name: 'Can Öztürk', email: 'can@example.com', bookings: 0, spent: '₺0', status: [ 'Blocked', 'danger' ] },
];
const LABELS = { previous: 'Previous', next: 'Next', of: 'of', navigation: 'Customer pages', page: 'Page' };

/** The full list: select column, sortable headers, a primary cell, status, a footer pager. */
export const CustomerList = () => {
	const [ sort, setSort ] = useState( { key: 'name', direction: 'asc' as const } );
	const [ picked, setPicked ] = useState< number[] >( [ 2 ] );
	const [ page, setPage ] = useState( 1 );
	const state = picked.length === 0 ? 'none' : picked.length === ROWS.length ? 'all' : 'some';
	const toggle = ( id: number ) => setPicked( ( p ) => ( p.includes( id ) ? p.filter( ( x ) => x !== id ) : [ ...p, id ] ) );
	return (
		<DataTable
			caption="Customers"
			captionMeta="· 48"
			footer={ <Pagination variant="footer" page={ page } totalPages={ 12 } onChange={ setPage } labels={ LABELS } summary="1–4 of 48" /> }
		>
			<thead>
				<tr>
					<th scope="col" className="mhmui-table__select">
						<SelectAll state={ state } label="Select all customers" onChange={ () => setPicked( state === 'all' ? [] : ROWS.map( ( r ) => r.id ) ) } />
					</th>
					<SortHeader label="Customer" sortKey="name" sort={ sort } onSort={ ( k ) => setSort( nextSort( sort, k ) ) } />
					<th scope="col">Status</th>
					<SortHeader label="Bookings" sortKey="bookings" sort={ sort } onSort={ ( k ) => setSort( nextSort( sort, k, 'desc' ) ) } align="end" />
					<SortHeader label="Total spent" sortKey="spent" sort={ sort } onSort={ ( k ) => setSort( nextSort( sort, k, 'desc' ) ) } align="end" />
				</tr>
			</thead>
			<tbody>
				{ ROWS.map( ( r ) => (
					<tr key={ r.id }>
						<td className="mhmui-table__select"><RowSelect checked={ picked.includes( r.id ) } label={ `Select ${ r.name }` } onChange={ () => toggle( r.id ) } /></td>
						<th scope="row" className="mhmui-table__primary">{ r.name }<br /><span style={ { color: 'var(--mhmui-text-muted)', fontWeight: 400 } }>{ r.email }</span></th>
						<td data-label="Status"><StatusBadge tone={ r.status[ 1 ] as any }>{ r.status[ 0 ] }</StatusBadge></td>
						<td className="mhmui-table__num" data-label="Bookings">{ r.bookings }</td>
						<td className="mhmui-table__num" data-label="Total spent">{ r.spent }</td>
					</tr>
				) ) }
			</tbody>
		</DataTable>
	);
};

/** No rows yet: the caption still names the table; an empty-state row says why. */
export const Empty = () => (
	<DataTable caption="Vehicles" captionMeta="· 0">
		<thead><tr><th scope="col">Vehicle</th><th scope="col">Plate</th><th scope="col" className="mhmui-table__num">Daily rate</th></tr></thead>
		<tbody><tr><td colSpan={ 3 } style={ { textAlign: 'center', color: 'var(--mhmui-text-muted)', padding: 24 } }>No vehicles yet. Add your first vehicle to start taking bookings.</td></tr></tbody>
	</DataTable>
);
