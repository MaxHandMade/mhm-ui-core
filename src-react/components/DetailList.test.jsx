import { render } from '@testing-library/react';
import { DetailList } from '../index';

const values = ( container ) =>
	[ ...container.querySelectorAll( '.mhmui-detail-list__value' ) ].map(
		( d ) => [ d.textContent, d.className ]
	);

describe( 'DetailList -- label/value pairs', () => {
	test( 'renders a dl of div-wrapped dt/dd pairs', () => {
		const { container } = render(
			<DetailList
				emptyText="—"
				items={ [ { label: 'City', value: 'Muğla' } ] }
			/>
		);
		const dl = container.querySelector( 'dl.mhmui-detail-list' );
		expect(
			dl.querySelector(
				'div.mhmui-detail-list__item > dt.mhmui-detail-list__label'
			).textContent
		).toBe( 'City' );
		expect(
			dl.querySelector(
				'div.mhmui-detail-list__item > dd.mhmui-detail-list__value'
			).textContent
		).toBe( 'Muğla' );
	} );

	test( "'' null undefined show emptyText, muted", () => {
		const { container } = render(
			<DetailList
				emptyText="Not entered"
				items={ [
					{ label: 'a', value: '' },
					{ label: 'b', value: null },
					{ label: 'c' },
				] }
			/>
		);
		expect( values( container ) ).toEqual( [
			[
				'Not entered',
				'mhmui-detail-list__value mhmui-detail-list__value--empty',
			],
			[
				'Not entered',
				'mhmui-detail-list__value mhmui-detail-list__value--empty',
			],
			[
				'Not entered',
				'mhmui-detail-list__value mhmui-detail-list__value--empty',
			],
		] );
	} );

	test( '0 and "0" are values, not empty (Review Focus 5)', () => {
		const { container } = render(
			<DetailList
				emptyText="—"
				items={ [
					{ label: 'a', value: 0 },
					{ label: 'b', value: '0' },
				] }
			/>
		);
		expect( values( container ) ).toEqual( [
			[ '0', 'mhmui-detail-list__value' ],
			[ '0', 'mhmui-detail-list__value' ],
		] );
	} );

	test( 'a known tone adds an aria-hidden mark and leaves the value text uncoloured', () => {
		const { container } = render(
			<DetailList
				emptyText="—"
				items={ [
					{ label: 'Docs', value: '2 missing', tone: 'warning' },
				] }
			/>
		);
		const dd = container.querySelector( '.mhmui-detail-list__value' );
		expect( dd.className ).toBe( 'mhmui-detail-list__value' );
		const mark = dd.querySelector( '.mhmui-detail-list__mark' );
		expect( mark.className ).toBe(
			'mhmui-detail-list__mark mhmui-detail-list__mark--warning'
		);
		expect( mark.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( dd.textContent ).toBe( '2 missing' );
	} );

	test( 'an unknown tone draws no mark; an empty value draws no mark', () => {
		const { container } = render(
			<DetailList
				emptyText="—"
				items={ [
					{ label: 'a', value: 'x', tone: 'purple' },
					{ label: 'b', value: '', tone: 'warning' },
				] }
			/>
		);
		expect(
			container.querySelectorAll( '.mhmui-detail-list__mark' )
		).toHaveLength( 0 );
	} );

	test( 'stacked sets a column ceiling variable, never an inline track list', () => {
		const { container } = render(
			<DetailList emptyText="—" columns={ 3 } items={ [] } />
		);
		const dl = container.querySelector( 'dl' );
		expect( dl.style.getPropertyValue( '--mhmui-columns' ) ).toBe( '3' );
		expect( dl.style.gridTemplateColumns ).toBe( '' );
	} );

	test( 'inline layout has its modifier and no column variable', () => {
		const { container } = render(
			<DetailList emptyText="—" layout="inline" items={ [] } />
		);
		const dl = container.querySelector( 'dl' );
		expect( dl.className ).toBe(
			'mhmui-detail-list mhmui-detail-list--inline'
		);
		expect( dl.style.getPropertyValue( '--mhmui-columns' ) ).toBe( '' );
	} );

	test( 'a node value renders as given', () => {
		const { container } = render(
			<DetailList
				emptyText="—"
				items={ [ { label: 'IBAN', value: <code>TR**</code> } ] }
			/>
		);
		expect(
			container.querySelector( '.mhmui-detail-list__value code' )
				.textContent
		).toBe( 'TR**' );
	} );
} );
