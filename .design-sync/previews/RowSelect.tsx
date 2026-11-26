import { useState } from 'react';
import { DataTable, RowSelect } from '@mhm/ui-core';

const ROWS = [
	[ 'a', 'Fiat Egea · 48 ABC 123' ],
	[ 'b', 'Renault Clio · 48 XYZ 987' ],
	[ 'c', 'Toyota Corolla · 48 KLM 456 (in service)' ],
];

/** The per-row checkbox in the table's select column (44px hit area); the last row is disabled. */
export const InATable = () => {
	const [ on, setOn ] = useState< Record< string, boolean > >( { a: true } );
	return (
		<DataTable caption="Vehicles" captionHidden>
			<thead><tr><th scope="col" className="mhmui-table__select" /><th scope="col">Vehicle</th></tr></thead>
			<tbody>
				{ ROWS.map( ( [ id, name ] ) => (
					<tr key={ id }>
						<td className="mhmui-table__select"><RowSelect checked={ !! on[ id ] } label={ `Select ${ name }` } onChange={ () => setOn( { ...on, [ id ]: ! on[ id ] } ) } disabled={ id === 'c' } /></td>
						<th scope="row" className="mhmui-table__primary">{ name }</th>
					</tr>
				) ) }
			</tbody>
		</DataTable>
	);
};
