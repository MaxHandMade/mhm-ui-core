import { useState } from 'react';
import { DataTable, SortHeader, nextSort } from '@mhm/ui-core';

/** Sortable headers: the whole cell is a button; the sorted one shows its direction. */
export const SortableColumns = () => {
	const [ sort, setSort ] = useState< { key: string; direction: 'asc' | 'desc' } >( { key: 'date', direction: 'desc' } );
	const onSort = ( key: string ) => setSort( nextSort( sort, key, 'desc' ) );
	return (
		<DataTable caption="Bookings" captionMeta="· 236">
			<thead>
				<tr>
					<SortHeader label="Customer" sortKey="customer" sort={ sort } onSort={ onSort } />
					<SortHeader label="Pick-up date" sortKey="date" sort={ sort } onSort={ onSort } />
					<SortHeader label="Total" sortKey="total" sort={ sort } onSort={ onSort } align="end" />
				</tr>
			</thead>
			<tbody>
				<tr><th scope="row" className="mhmui-table__primary">Ayşe Yılmaz</th><td>02/12/2026</td><td className="mhmui-table__num">₺4,200</td></tr>
				<tr><th scope="row" className="mhmui-table__primary">Mehmet Demir</th><td>28/11/2026</td><td className="mhmui-table__num">₺1,750</td></tr>
			</tbody>
		</DataTable>
	);
};
