import { render, screen, fireEvent } from '@testing-library/react';
import { PageHeader } from '../index';

describe( 'PageHeader -- back link, title, badge, meta, actions', () => {
	test( 'defaults to an h2 (the page h1 belongs to WordPress)', () => {
		render( <PageHeader title="Marmaris Cars" /> );
		expect(
			screen.getByRole( 'heading', { level: 2, name: 'Marmaris Cars' } )
		).toBeTruthy();
	} );

	test.each( [ [ 1 ], [ 3 ] ] )(
		'level %i renders that heading',
		( level ) => {
			render( <PageHeader title="T" level={ level } /> );
			expect( screen.getByRole( 'heading', { level } ) ).toBeTruthy();
		}
	);

	test( 'an invalid level falls back to h2', () => {
		render( <PageHeader title="T" level={ 7 } /> );
		expect( screen.getByRole( 'heading', { level: 2 } ) ).toBeTruthy();
	} );

	test( 'the root is not a <header> landmark', () => {
		const { container } = render( <PageHeader title="T" /> );
		expect( container.firstChild.tagName ).toBe( 'DIV' );
		expect( screen.queryByRole( 'banner' ) ).toBeNull();
	} );

	test( 'back is a real link; its arrow is aria-hidden', () => {
		render(
			<PageHeader
				title="T"
				back={ { label: 'Pending applications', href: '?tab=pending' } }
			/>
		);
		const link = screen.getByRole( 'link', {
			name: 'Pending applications',
		} );
		expect( link.getAttribute( 'href' ) ).toBe( '?tab=pending' );
		expect( link.querySelector( '[aria-hidden="true"]' ).textContent ).toBe(
			'← '
		);
	} );

	test( 'back.onClick intercepts a plain left click only', () => {
		const onClick = jest.fn();
		render(
			<PageHeader
				title="T"
				back={ { label: 'Back', href: '?b', onClick } }
			/>
		);
		const link = screen.getByRole( 'link', { name: 'Back' } );
		expect( fireEvent.click( link, { button: 0, ctrlKey: true } ) ).toBe(
			true
		);
		expect( onClick ).not.toHaveBeenCalled();
		expect( fireEvent.click( link, { button: 0 } ) ).toBe( false );
		expect( onClick ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'no back link without an href', () => {
		render( <PageHeader title="T" back={ { label: 'Back', href: '' } } /> );
		expect( screen.queryByRole( 'link' ) ).toBeNull();
	} );

	test( 'badge renders the kit StatusBadge with its tone', () => {
		const { container } = render(
			<PageHeader
				title="T"
				badge={ { text: 'Pending', tone: 'warning' } }
			/>
		);
		const badge = container.querySelector( '.mhmui-status' );
		expect( badge.className ).toBe( 'mhmui-status mhmui-status--warning' );
		expect( badge.textContent ).toBe( 'Pending' );
	} );

	test( 'meta and actions render when given, nothing when not', () => {
		const { container, rerender } = render( <PageHeader title="T" /> );
		expect(
			container.querySelector( '.mhmui-page-header__meta' )
		).toBeNull();
		expect(
			container.querySelector( '.mhmui-page-header__actions' )
		).toBeNull();
		rerender(
			<PageHeader
				title="T"
				meta="#9292 · 23/09/2026"
				actions={ <button type="button">Do</button> }
			/>
		);
		expect(
			container.querySelector( '.mhmui-page-header__meta' ).textContent
		).toBe( '#9292 · 23/09/2026' );
		expect(
			container.querySelector( '.mhmui-page-header__actions button' )
		).toBeTruthy();
	} );
} );
