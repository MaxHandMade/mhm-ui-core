import { act, render, screen } from '@testing-library/react';
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

/*
 * 0.28.0: a stack table above the CSS 40rem floor stacks when its rows do
 * not fit. jsdom has no layout, so the two widths are stubbed: the wrap's
 * clientWidth (the space) and the table's offsetWidth, which -- as in a
 * browser -- is the space while the table is stacked (`--no-fit` on the wrap)
 * and its natural width otherwise.
 */
describe( 'DataTable stacks when the rows do not fit (0.28.0)', () => {
	const NO_FIT = 'mhmui-table-wrap--no-fit';
	let space;
	let natural;
	let observers;

	beforeEach( () => {
		space = 800;
		natural = 700;
		observers = [];
		const proto = window.HTMLElement.prototype;
		function wrapWidth() {
			return this.classList.contains( 'mhmui-table-wrap' ) ? space : 0;
		}
		jest.spyOn( proto, 'clientWidth', 'get' ).mockImplementation(
			wrapWidth
		);
		function tableWidth() {
			if ( this.tagName !== 'TABLE' ) {
				return 0;
			}
			const wrap = this.closest( '.mhmui-table-wrap' );
			return wrap && wrap.classList.contains( NO_FIT ) ? space : natural;
		}
		jest.spyOn( proto, 'offsetWidth', 'get' ).mockImplementation(
			tableWidth
		);
		window.ResizeObserver = class {
			constructor( cb ) {
				this.cb = cb;
				this.nodes = [];
				observers.push( this );
			}
			observe( node ) {
				this.nodes.push( node );
			}
			disconnect() {
				this.nodes = [];
			}
		};
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete window.ResizeObserver;
	} );

	const resize = () =>
		act( () => {
			observers.forEach( ( o ) =>
				o.nodes.length ? o.cb( [ { target: o.nodes[ 0 ] } ] ) : null
			);
		} );
	const wrapOf = ( container ) =>
		container.querySelector( '.mhmui-table-wrap' );

	test( 'rows wider than the space stack', () => {
		natural = 1100;
		const { container } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		expect( wrapOf( container ).className ).toBe(
			`mhmui-table-wrap mhmui-table-wrap--stack ${ NO_FIT }`
		);
	} );

	test( 'rows that fit stay a table', () => {
		const { container } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		expect( wrapOf( container ).classList.contains( NO_FIT ) ).toBe(
			false
		);
	} );

	test( 'above 64rem a table that does not fit scrolls', () => {
		space = 1100;
		natural = 1400;
		const { container } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		expect( wrapOf( container ).classList.contains( NO_FIT ) ).toBe(
			false
		);
	} );

	test( 'a wrap that is not laid out (0 wide) is left alone', () => {
		space = 0;
		natural = 1100;
		const { container } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		expect( wrapOf( container ).classList.contains( NO_FIT ) ).toBe(
			false
		);
	} );

	test( 'the scroll layout never stacks', () => {
		natural = 1100;
		const { container } = render(
			<DataTable caption="C">{ body }</DataTable>
		);
		expect( wrapOf( container ).className ).toBe( 'mhmui-table-wrap' );
		expect( observers ).toHaveLength( 0 );
	} );

	test( 'a resize re-measures the natural width, both ways', () => {
		space = 1000;
		natural = 900;
		const { container } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		const wrap = wrapOf( container );
		expect( wrap.classList.contains( NO_FIT ) ).toBe( false );
		space = 800;
		resize();
		expect( wrap.classList.contains( NO_FIT ) ).toBe( true );
		// Stacked, the table is as wide as the space; only a reading taken
		// without the class sees that 900 now fits in 950.
		space = 950;
		resize();
		expect( wrap.classList.contains( NO_FIT ) ).toBe( false );
	} );

	test( 'new rows re-measure without a resize', () => {
		const rows = ( text ) => (
			<tbody>
				<tr>
					<td>{ text }</td>
				</tr>
			</tbody>
		);
		const { container, rerender } = render(
			<DataTable caption="C" layout="stack">
				{ rows( 'a' ) }
			</DataTable>
		);
		expect( wrapOf( container ).classList.contains( NO_FIT ) ).toBe(
			false
		);
		natural = 1100;
		rerender(
			<DataTable caption="C" layout="stack">
				{ rows( 'a much longer value' ) }
			</DataTable>
		);
		expect( wrapOf( container ).classList.contains( NO_FIT ) ).toBe( true );
	} );

	test( 'unmount disconnects the observer', () => {
		const { unmount } = render(
			<DataTable caption="C" layout="stack">
				{ body }
			</DataTable>
		);
		expect( observers[ 0 ].nodes ).toHaveLength( 1 );
		unmount();
		expect( observers[ 0 ].nodes ).toHaveLength( 0 );
	} );
} );
