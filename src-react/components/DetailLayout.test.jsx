import { render, screen } from '@testing-library/react';
import { DetailLayout } from '../index';

describe( 'DetailLayout -- main column and a named aside', () => {
	test( 'renders main content and a labelled complementary aside', () => {
		const { container } = render(
			<DetailLayout aside={ <p>Decide</p> } asideLabel="Decision">
				<p>Details</p>
			</DetailLayout>
		);
		expect(
			container.querySelector(
				'.mhmui-detail-layout > .mhmui-detail-layout__main'
			).textContent
		).toBe( 'Details' );
		const aside = screen.getByRole( 'complementary', { name: 'Decision' } );
		expect( aside.className ).toBe( 'mhmui-detail-layout__aside' );
		expect( aside.textContent ).toBe( 'Decide' );
	} );

	test( 'without aside only the main column renders', () => {
		const { container } = render(
			<DetailLayout>
				<p>Only</p>
			</DetailLayout>
		);
		expect( container.querySelector( 'aside' ) ).toBeNull();
		expect(
			container.querySelector( '.mhmui-detail-layout__main' ).textContent
		).toBe( 'Only' );
	} );

	test( 'an aside without a label renders unnamed rather than with an empty name', () => {
		const { container } = render(
			<DetailLayout aside={ <p>x</p> }>
				<p>y</p>
			</DetailLayout>
		);
		expect(
			container.querySelector( 'aside' ).hasAttribute( 'aria-label' )
		).toBe( false );
	} );
} );
