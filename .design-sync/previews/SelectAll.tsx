import { DataTable, SelectAll, RowSelect } from '@mhm/ui-core';

const NAMES = [ 'Ayşe Yılmaz', 'Mehmet Demir', 'Elif Kaya' ];
const ignore = () => {};

function Customers( { state, picked }: { state: 'none' | 'some' | 'all'; picked: boolean[] } ) {
	return (
		<DataTable caption="Customers" captionHidden>
			<thead>
				<tr>
					<th scope="col" className="mhmui-table__select"><SelectAll state={ state } label="Select all customers" onChange={ ignore } /></th>
					<th scope="col">Customer</th>
				</tr>
			</thead>
			<tbody>
				{ NAMES.map( ( n, i ) => (
					<tr key={ n }>
						<td className="mhmui-table__select"><RowSelect checked={ picked[ i ] } label={ `Select ${ n }` } onChange={ ignore } /></td>
						<th scope="row" className="mhmui-table__primary">{ n }</th>
					</tr>
				) ) }
			</tbody>
		</DataTable>
	);
}

/** Some rows picked: the header box is indeterminate. */
export const Some = () => <Customers state="some" picked={ [ true, false, false ] } />;

/** Every row picked. */
export const All = () => <Customers state="all" picked={ [ true, true, true ] } />;

/** Nothing picked. */
export const None = () => <Customers state="none" picked={ [ false, false, false ] } />;
