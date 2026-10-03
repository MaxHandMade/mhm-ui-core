import { render, fireEvent } from '@testing-library/react';
import Pagination from './Pagination';

const labels = {
	navigation: 'Pages',
	previous: 'Previous',
	next: 'Next',
	of: '/',
	page: 'Page',
};

describe( 'Pagination', () => {
	test( 'inline variant keeps the 0.15.2 markup', () => {
		const { container } = render(
			<Pagination
				page={ 2 }
				totalPages={ 3 }
				onChange={ () => {} }
				labels={ {
					navigation: 'P',
					previous: 'Prev',
					next: 'Next',
					of: 'of',
				} }
			/>
		);
		expect( container.innerHTML ).toBe(
			'<nav class="mhmui-pagination" aria-label="P"><button type="button" class="button mhmui-pagination__button">Prev</button><span class="mhmui-pagination__status">2 of 3</span><button type="button" class="button mhmui-pagination__button">Next</button></nav>'
		);
	} );
	test( 'footer: summary, page status, both ends disabled on a single page', () => {
		const { container, getAllByRole } = render(
			<Pagination
				variant="footer"
				page={ 1 }
				totalPages={ 1 }
				summary="1–7 / 7"
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		expect(
			container.querySelector( '.mhmui-pagination__summary' ).textContent
		).toBe( '1–7 / 7' );
		expect(
			container.querySelector( '.mhmui-pagination__status' ).textContent
		).toBe( 'Page 1 / 1' );
		expect(
			getAllByRole( 'button' ).map( ( b ) => [
				b.textContent,
				b.disabled,
			] )
		).toEqual( [
			[ '‹Previous', true ],
			[ 'Next›', true ],
		] );
	} );
	test( 'footer with zero pages reads page 1 of 1, not 1 of 0', () => {
		const { container } = render(
			<Pagination
				variant="footer"
				page={ 1 }
				totalPages={ 0 }
				summary="0"
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		expect(
			container.querySelector( '.mhmui-pagination__status' ).textContent
		).toBe( 'Page 1 / 1' );
	} );
	test( 'footer next calls onChange with page + 1', () => {
		const onChange = jest.fn();
		const { getByRole } = render(
			<Pagination
				variant="footer"
				page={ 1 }
				totalPages={ 3 }
				summary="1–10 / 25"
				onChange={ onChange }
				labels={ labels }
			/>
		);
		fireEvent.click( getByRole( 'button', { name: 'Next' } ) );
		expect( onChange ).toHaveBeenCalledWith( 2 );
	} );
	test( 'footer markup: nav modifier, classes, aria-hidden arrows', () => {
		const { container, getByRole } = render(
			<Pagination
				variant="footer"
				page={ 2 }
				totalPages={ 3 }
				summary="S"
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		const nav = container.querySelector( 'nav' );
		expect( nav.className ).toBe(
			'mhmui-pagination mhmui-pagination--footer'
		);
		expect( nav.getAttribute( 'aria-label' ) ).toBe( 'Pages' );
		const prev = getByRole( 'button', { name: 'Previous' } );
		expect( prev.className ).toBe(
			'mhmui-button mhmui-button--secondary mhmui-button--sm mhmui-pagination__button'
		);
		expect( prev.disabled ).toBe( false );
		expect( prev.querySelector( '[aria-hidden="true"]' ).textContent ).toBe(
			'‹'
		);
	} );
} );
