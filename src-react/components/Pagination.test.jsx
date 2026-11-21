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

	// labels had no default: leaving it out or passing null crashed the
	// render on `labels.navigation`, in both variants. The controls still
	// render (unlabelled -- the consumer's bug, but not a blank screen).
	test.each( [
		[ 'inline', undefined ],
		[ 'inline', null ],
		[ 'footer', undefined ],
		[ 'footer', 'labels' ],
	] )( '%s variant renders without labels (%p)', ( variant, value ) => {
		const { container } = render(
			<Pagination
				page={ 1 }
				totalPages={ 2 }
				onChange={ () => {} }
				labels={ value }
				variant={ variant }
			/>
		);
		expect(
			container.querySelectorAll( 'nav.mhmui-pagination button' )
		).toHaveLength( 2 );
	} );

	// 0.23.7: page and totalPages arrive from a REST response or a URL, so
	// they can be missing or strings. A missing total wrote "NaN" and left
	// next enabled; a string page made next send "21" (string + 1); a
	// missing onChange threw on the first click.
	const status = ( container ) =>
		container.querySelector( '.mhmui-pagination__status' ).textContent;
	const buttons = ( container ) =>
		container.querySelectorAll( 'nav.mhmui-pagination button' );

	test.each( [
		[ 'inline', undefined, '1 / 1' ],
		[ 'inline', null, '1 / 1' ],
		[ 'inline', 'abc', '1 / 1' ],
		[ 'inline', Number.NaN, '1 / 1' ],
		[ 'footer', undefined, 'Page 1 / 1' ],
		[ 'footer', 'abc', 'Page 1 / 1' ],
	] )(
		'%s variant with totalPages %p reads one page, not NaN',
		( variant, totalPages, expected ) => {
			const { container } = render(
				<Pagination
					page={ 1 }
					totalPages={ totalPages }
					onChange={ () => {} }
					labels={ labels }
					variant={ variant }
				/>
			);
			expect( status( container ) ).toBe( expected );
			expect( buttons( container )[ 1 ].disabled ).toBe( true );
		}
	);

	test.each( [ 'inline', 'footer' ] )(
		'%s variant: numeric strings are numbers -- next sends 3, not "21"',
		( variant ) => {
			const onChange = jest.fn();
			const { container } = render(
				<Pagination
					page="2"
					totalPages="3"
					onChange={ onChange }
					labels={ labels }
					variant={ variant }
				/>
			);
			const [ prev, next ] = buttons( container );
			expect( next.disabled ).toBe( false );
			fireEvent.click( next );
			fireEvent.click( prev );
			expect( onChange.mock.calls ).toEqual( [ [ 3 ], [ 1 ] ] );
		}
	);

	test( 'a string page on the last page still disables next', () => {
		const { container } = render(
			<Pagination
				page="3"
				totalPages={ 3 }
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		expect( buttons( container )[ 1 ].disabled ).toBe( true );
	} );

	test( 'a missing page reads as page 1', () => {
		const { container } = render(
			<Pagination
				totalPages={ 3 }
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		expect( status( container ) ).toBe( '1 / 3' );
		expect( buttons( container )[ 0 ].disabled ).toBe( true );
	} );

	test.each( [ 'inline', 'footer' ] )(
		'%s variant: a click without onChange does nothing instead of throwing',
		( variant ) => {
			const { container } = render(
				<Pagination
					page={ 2 }
					totalPages={ 3 }
					labels={ labels }
					variant={ variant }
				/>
			);
			for ( const button of buttons( container ) ) {
				expect( () => fireEvent.click( button ) ).not.toThrow();
			}
		}
	);

	test( 'a valid zero total keeps its inline text (only bad input changes)', () => {
		const { container } = render(
			<Pagination
				page={ 1 }
				totalPages={ 0 }
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		expect( status( container ) ).toBe( '1 / 0' );
	} );

	// Fable, 0.23.7 round: these were valid enough before and must stay so.
	test( 'a fractional total rounds up -- the last partial page stays reachable', () => {
		const onChange = jest.fn();
		const { container } = render(
			<Pagination
				page={ 2 }
				totalPages={ 2.5 }
				onChange={ onChange }
				labels={ labels }
			/>
		);
		expect( status( container ) ).toBe( '2 / 3' );
		fireEvent.click( buttons( container )[ 1 ] );
		expect( onChange.mock.calls ).toEqual( [ [ 3 ] ] );
	} );

	test( 'an Infinity total (unknown) leaves next open, as before', () => {
		const { container } = render(
			<Pagination
				page={ 3 }
				totalPages={ Infinity }
				onChange={ () => {} }
				labels={ labels }
			/>
		);
		expect( buttons( container )[ 1 ].disabled ).toBe( false );
	} );

	test( 'a fractional page is cut down, so next sends a whole number', () => {
		const onChange = jest.fn();
		const { container } = render(
			<Pagination
				page={ 2.5 }
				totalPages={ 5 }
				onChange={ onChange }
				labels={ labels }
			/>
		);
		fireEvent.click( buttons( container )[ 1 ] );
		expect( onChange.mock.calls ).toEqual( [ [ 3 ] ] );
	} );

	test.each( [ '', '   ', true, null ] )(
		'a total of %p is not numeric and reads one page',
		( totalPages ) => {
			const { container } = render(
				<Pagination
					page={ 1 }
					totalPages={ totalPages }
					onChange={ () => {} }
					labels={ labels }
				/>
			);
			expect( status( container ) ).toBe( '1 / 1' );
		}
	);

	test( 'an onChange that is not a function is ignored, not called', () => {
		const { container } = render(
			<Pagination
				page={ 2 }
				totalPages={ 3 }
				onChange="setPage"
				labels={ labels }
			/>
		);
		expect( () =>
			fireEvent.click( buttons( container )[ 1 ] )
		).not.toThrow();
	} );
} );
