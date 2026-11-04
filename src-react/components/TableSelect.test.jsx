import { render, screen, fireEvent } from '@testing-library/react';
import SelectAll from './SelectAll';
import RowSelect from './RowSelect';

describe( 'SelectAll', () => {
	const box = () => screen.getByRole( 'checkbox', { name: 'Select all' } );

	test( 'its name is its label text', () => {
		render(
			<SelectAll state="none" onChange={ () => {} } label="Select all" />
		);
		expect( box() ).toBeTruthy();
	} );

	test( 'indeterminate follows state after a change', () => {
		const { rerender } = render(
			<SelectAll state="none" onChange={ () => {} } label="Select all" />
		);
		expect( box().indeterminate ).toBe( false );
		rerender(
			<SelectAll state="some" onChange={ () => {} } label="Select all" />
		);
		expect( box().indeterminate ).toBe( true );
		expect( box().checked ).toBe( false );
		rerender(
			<SelectAll state="all" onChange={ () => {} } label="Select all" />
		);
		expect( box().indeterminate ).toBe( false );
		expect( box().checked ).toBe( true );
	} );

	test( 'a declined change keeps the mixed state', () => {
		const onChange = jest.fn();
		render(
			<SelectAll state="some" onChange={ onChange } label="Select all" />
		);
		fireEvent.click( box() );
		expect( onChange ).toHaveBeenCalledWith( true );
		expect( box().indeterminate ).toBe( true );
		expect( box().checked ).toBe( false );
	} );

	test.each( [
		[ 'none', true ],
		[ 'some', true ],
		[ 'all', false ],
	] )( 'a click from %s asks for %s', ( state, on ) => {
		const onChange = jest.fn();
		render(
			<SelectAll
				state={ state }
				onChange={ onChange }
				label="Select all"
			/>
		);
		fireEvent.click( box() );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
		expect( onChange ).toHaveBeenCalledWith( on );
	} );

	test( 'disabled is passed to the box', () => {
		render(
			<SelectAll
				state="none"
				onChange={ () => {} }
				label="Select all"
				disabled
			/>
		);
		expect( box().disabled ).toBe( true );
	} );

	test( 'the box sits in a hit label', () => {
		render(
			<SelectAll state="none" onChange={ () => {} } label="Select all" />
		);
		expect( box().closest( 'label' ).className ).toBe(
			'mhmui-table__select-hit'
		);
		expect( box().className ).toBe( 'mhmui-table__select-all' );
	} );
} );

describe( 'RowSelect', () => {
	test( 'named by its label, checked follows the prop, a click calls onChange once', () => {
		const onChange = jest.fn();
		const { rerender } = render(
			<RowSelect
				checked={ false }
				onChange={ onChange }
				label="Select Burak Demir"
			/>
		);
		const box = screen.getByRole( 'checkbox', {
			name: 'Select Burak Demir',
		} );
		expect( box.checked ).toBe( false );
		fireEvent.click( box );
		expect( onChange ).toHaveBeenCalledTimes( 1 );
		rerender(
			<RowSelect
				checked
				onChange={ onChange }
				label="Select Burak Demir"
			/>
		);
		expect( box.checked ).toBe( true );
		expect( box.className ).toBe( 'mhmui-table__row-select' );
		expect( box.closest( 'label' ).className ).toBe(
			'mhmui-table__select-hit'
		);
	} );

	test( 'disabled is passed to the box', () => {
		render(
			<RowSelect
				checked={ false }
				onChange={ () => {} }
				label="Select A"
				disabled
			/>
		);
		expect(
			screen.getByRole( 'checkbox', { name: 'Select A' } ).disabled
		).toBe( true );
	} );
} );
