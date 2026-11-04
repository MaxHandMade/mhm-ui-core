import { render, screen } from '@testing-library/react';
import DataTable from './DataTable';

const body = (
	<>
		<thead>
			<tr>
				<th>Customer</th>
				<th>Phone</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th>Ada</th>
				<td>+90</td>
			</tr>
		</tbody>
	</>
);

describe( 'DataTable', () => {
	test( 'the caption names the table; meta sits inside it', () => {
		render(
			<DataTable caption="Customers" captionMeta="· 48">
				{ body }
			</DataTable>
		);
		const table = screen.getByRole( 'table', { name: 'Customers · 48' } );
		const caption = table.querySelector( 'caption' );
		expect( caption.className ).toBe( 'mhmui-table__caption' );
		expect(
			caption.querySelector( '.mhmui-table__caption-meta' ).textContent
		).toBe( '· 48' );
	} );

	test( 'captionHidden keeps the element', () => {
		const { container } = render(
			<DataTable caption="Customers" captionHidden>
				{ body }
			</DataTable>
		);
		const caption = container.querySelector( 'caption' );
		expect( caption.textContent ).toBe( 'Customers' );
		expect( caption.className ).toBe(
			'mhmui-table__caption mhmui-table__caption--hidden'
		);
	} );

	test( 'className joins mhmui-table', () => {
		const { container } = render(
			<DataTable caption="C" className="rv-cust-table">
				{ body }
			</DataTable>
		);
		expect( container.querySelector( 'table' ).className ).toBe(
			'mhmui-table rv-cust-table'
		);
	} );

	test( 'footer renders inside the card, outside the table', () => {
		const { container } = render(
			<DataTable caption="C" footer={ <nav className="foot">p</nav> }>
				{ body }
			</DataTable>
		);
		const foot = container.querySelector( '.foot' );
		expect( foot.closest( '.mhmui-table-card' ) ).not.toBeNull();
		expect( foot.closest( 'table' ) ).toBeNull();
		expect( foot.closest( '.mhmui-table-wrap' ) ).toBeNull();
	} );

	test.each( [ undefined, 'scroll', 'grid' ] )(
		'layout %s is the scroll layout and writes no roles',
		( layout ) => {
			const { container } = render(
				<DataTable caption="C" layout={ layout }>
					{ body }
				</DataTable>
			);
			expect(
				container.querySelector( '.mhmui-table-wrap' ).className
			).toBe( 'mhmui-table-wrap' );
			expect( container.querySelector( 'table' ).className ).toBe(
				'mhmui-table'
			);
			expect( container.querySelector( '[role]' ) ).toBeNull();
		}
	);

	test( 'stack writes roles by position, not by scope', () => {
		const { container } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		expect( container.querySelector( '.mhmui-table-wrap' ).className ).toBe(
			'mhmui-table-wrap mhmui-table-wrap--stack'
		);
		const table = container.querySelector( 'table' );
		expect( table.className ).toBe( 'mhmui-table mhmui-table--stack' );
		expect( table.getAttribute( 'role' ) ).toBe( 'table' );
		expect( table.querySelector( 'thead' ).getAttribute( 'role' ) ).toBe(
			'rowgroup'
		);
		expect( table.querySelector( 'tbody' ).getAttribute( 'role' ) ).toBe(
			'rowgroup'
		);
		expect( table.querySelector( 'thead th' ).getAttribute( 'role' ) ).toBe(
			'columnheader'
		);
		expect( table.querySelector( 'tbody th' ).getAttribute( 'role' ) ).toBe(
			'rowheader'
		);
		expect( table.querySelector( 'tbody tr' ).getAttribute( 'role' ) ).toBe(
			'row'
		);
		expect( table.querySelector( 'tbody td' ).getAttribute( 'role' ) ).toBe(
			'cell'
		);
	} );

	test( 'stack never overwrites a consumer role', () => {
		const { container } = render(
			<DataTable caption="C" layout="stack">
				<tbody>
					<tr>
						{ /* eslint-disable-next-line jsx-a11y/no-interactive-element-to-noninteractive-role -- the fixture IS a consumer-set role the kit must not overwrite. */ }
						<td role="presentation">x</td>
						<td>y</td>
					</tr>
				</tbody>
			</DataTable>
		);
		const cells = container.querySelectorAll( 'td' );
		expect( cells[ 0 ].getAttribute( 'role' ) ).toBe( 'presentation' );
		expect( cells[ 1 ].getAttribute( 'role' ) ).toBe( 'cell' );
	} );

	test( 'a nested table in a cell is not relabelled', () => {
		const { container } = render(
			<DataTable caption="C" layout="stack">
				<tbody>
					<tr>
						<td>
							<table className="inner">
								<tbody>
									<tr>
										<td>z</td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
				</tbody>
			</DataTable>
		);
		const inner = container.querySelector( '.inner' );
		expect( inner.hasAttribute( 'role' ) ).toBe( false );
		expect( inner.querySelector( 'tbody' ).hasAttribute( 'role' ) ).toBe(
			false
		);
		expect( inner.querySelector( 'tr' ).hasAttribute( 'role' ) ).toBe(
			false
		);
		expect( inner.querySelector( 'td' ).hasAttribute( 'role' ) ).toBe(
			false
		);
	} );

	test( 'rows added later get roles too', () => {
		const rows = ( n ) => (
			<tbody>
				{ Array.from( { length: n }, ( _, i ) => (
					<tr key={ i }>
						<td>{ i }</td>
					</tr>
				) ) }
			</tbody>
		);
		const { container, rerender } = render(
			<DataTable caption="C" layout="stack">
				{ rows( 1 ) }
			</DataTable>
		);
		rerender(
			<DataTable caption="C" layout="stack">
				{ rows( 3 ) }
			</DataTable>
		);
		const missing = [ ...container.querySelectorAll( 'tr, td' ) ].filter(
			( el ) => ! el.hasAttribute( 'role' )
		);
		expect( missing ).toHaveLength( 0 );
	} );
} );
