import { render, screen, fireEvent } from '@testing-library/react';
import { PageHeader } from '../index';
import manifest from '../components.json';

// Captured once from the unchanged 0.15.2 component (base d20ee9d) for the
// components.json PageHeader fixture row 2 (F-I1): a call that passes only
// `badge` must keep this markup byte for byte.
const BASE_0152_PAGE_HEADER =
	'<div class="mhmui-page-header"><a class="mhmui-page-header__back" href="?tab=pending"><span aria-hidden="true">← </span>Pending applications</a><div class="mhmui-page-header__title-row"><h2 class="mhmui-page-header__title">Marmaris Cars</h2><span class="mhmui-status mhmui-status--warning">Pending</span></div><p class="mhmui-page-header__meta">#9292 · 23/09/2026 07:26</p></div>';

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

	test.each( [
		[ '' ],
		[ '   ' ],
		[ null ],
		[ undefined ],
		[ false ],
		[ true ],
	] )(
		'no back link when its label is %p -- a link with no accessible name is worse than none (M-2)',
		( label ) => {
			render( <PageHeader title="T" back={ { label, href: '?b' } } /> );
			expect( screen.queryByRole( 'link' ) ).toBeNull();
		}
	);

	test( 'a node label still draws the back link (R-12: no tightening beyond M-2)', () => {
		render(
			<PageHeader
				title="T"
				back={ { label: <span>Back to vendors</span>, href: '?b' } }
			/>
		);
		expect(
			screen.getByRole( 'link', { name: 'Back to vendors' } )
		).toBeTruthy();
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

	test( 'status and priority badges, in order, skipping empty text', () => {
		const { container } = render(
			<PageHeader
				title="T"
				level={ 1 }
				badge={ { text: 'Pending', tone: 'warning' } }
				badges={ [ { text: 'Urgent', tone: 'danger' }, { text: '' } ] }
			/>
		);
		expect(
			[
				...container.querySelectorAll(
					'.mhmui-page-header__badges .mhmui-status'
				),
			].map( ( b ) => b.textContent )
		).toEqual( [ 'Pending', 'Urgent' ] );
		expect(
			container.querySelector( 'h1.mhmui-page-header__title' ).textContent
		).toBe( 'T' );
	} );

	test( 'no badges draws no badges wrapper', () => {
		const { container } = render( <PageHeader title="T" /> );
		expect(
			container.querySelector( '.mhmui-page-header__badges' )
		).toBeNull();
	} );

	test( 'badges alone draw the wrapper; an empty badges list draws none', () => {
		const { container, rerender } = render(
			<PageHeader title="T" badges={ [ { text: 'Urgent' } ] } />
		);
		expect(
			container.querySelectorAll(
				'.mhmui-page-header__badges .mhmui-status'
			).length
		).toBe( 1 );
		rerender( <PageHeader title="T" badges={ [] } /> );
		expect(
			container.querySelector( '.mhmui-page-header__badges' )
		).toBeNull();
	} );

	test( 'PageHeader with only badge keeps the 0.15.2 markup', () => {
		expect(
			render( <PageHeader { ...manifest.PageHeader.fixtures[ 1 ] } /> )
				.container.innerHTML
		).toBe( BASE_0152_PAGE_HEADER );
	} );
} );
