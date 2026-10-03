import { render, screen, fireEvent } from '@testing-library/react';
import { Tabs } from '../index';

const ITEMS = [
	{
		id: 'pending',
		label: 'Pending',
		href: '?tab=pending',
		badge: 2,
		badgeLabel: '2 pending',
	},
	{ id: 'vendors', label: 'Vendors', href: '?tab=vendors', badge: 3 },
	{ id: 'iban', label: 'IBAN', href: '?tab=iban', badge: 0 },
];

describe( 'Tabs -- page sections as real links', () => {
	test( 'names the nav and marks only the current tab', () => {
		render( <Tabs label="Sections" current="pending" items={ ITEMS } /> );
		expect(
			screen.getByRole( 'navigation', { name: 'Sections' } )
		).toBeTruthy();
		const links = screen.getAllByRole( 'link' );
		expect(
			links.map( ( a ) => a.getAttribute( 'aria-current' ) )
		).toEqual( [ 'page', null, null ] );
		expect( links[ 0 ].className ).toBe(
			'mhmui-tabs__tab mhmui-tabs__tab--current'
		);
		expect( links[ 1 ].className ).toBe( 'mhmui-tabs__tab' );
	} );

	test( 'no current tab when current matches nothing or is absent', () => {
		render( <Tabs label="Sections" items={ ITEMS } /> );
		expect(
			screen
				.getAllByRole( 'link' )
				.every( ( a ) => ! a.hasAttribute( 'aria-current' ) )
		).toBe( true );
	} );

	test( 'a badge of 0 draws no chip; a number draws one', () => {
		const { container } = render(
			<Tabs label="Sections" current="x" items={ ITEMS } />
		);
		expect(
			container.querySelectorAll( '.mhmui-tabs__badge' )
		).toHaveLength( 2 );
	} );

	test( 'with badgeLabel the digit is aria-hidden and the link reads the meaning once', () => {
		const { container } = render(
			<Tabs label="Sections" current="x" items={ ITEMS } />
		);
		const badge = container.querySelector( '.mhmui-tabs__badge' );
		// Pinned byte for byte -- tests/Kit/TabsTest.php asserts the same string.
		expect( badge.innerHTML ).toBe(
			'<span aria-hidden="true">2</span><span class="mhmui-tabs__badge-sr">2 pending</span>'
		);
		expect( screen.getAllByRole( 'link' )[ 0 ].textContent ).toBe(
			'Pending22 pending'
		);
		// Accessible name: aria-hidden text is excluded.
		expect(
			screen.getByRole( 'link', { name: 'Pending 2 pending' } )
		).toBeTruthy();
	} );

	test( 'without badgeLabel the digit is plain text', () => {
		const { container } = render(
			<Tabs label="Sections" current="x" items={ ITEMS } />
		);
		expect(
			container.querySelectorAll( '.mhmui-tabs__badge' )[ 1 ].innerHTML
		).toBe( '3' );
	} );

	test( 'a numeric string badge draws a chip too (Review Focus 4)', () => {
		const { container } = render(
			<Tabs
				label="Sections"
				items={ [ { id: 'a', label: 'A', href: '?a', badge: '3' } ] }
			/>
		);
		expect(
			container.querySelector( '.mhmui-tabs__badge' ).textContent
		).toBe( '3' );
	} );

	test( 'an item with an empty href is skipped', () => {
		render(
			<Tabs
				label="Sections"
				items={ [
					{ id: 'a', label: 'A', href: '' },
					{ id: 'b', label: 'B', href: '?b' },
				] }
			/>
		);
		expect(
			screen.getAllByRole( 'link' ).map( ( a ) => a.textContent )
		).toEqual( [ 'B' ] );
	} );

	test( 'items without an id render without a React key warning, even on a shared href (M-3)', () => {
		render(
			<Tabs
				label="Sections"
				items={ [
					{ label: 'A', href: '?a' },
					{ label: 'B', href: '?b' },
					{ label: 'B again', href: '?b' },
				] }
			/>
		);
		expect( screen.getAllByRole( 'link' ) ).toHaveLength( 3 );
		expect( console ).not.toHaveErrored();
	} );

	test( 'an explicit id never collides with a fallback key (plan audit, Codex F-A)', () => {
		render(
			<Tabs
				label="Sections"
				items={ [
					{ id: '1:?b', label: 'A', href: '?a' },
					{ label: 'B', href: '?b' },
				] }
			/>
		);
		expect( screen.getAllByRole( 'link' ) ).toHaveLength( 2 );
		expect( console ).not.toHaveErrored();
	} );

	test( 'onSelect intercepts a plain left click', () => {
		const onSelect = jest.fn();
		render(
			<Tabs label="Sections" items={ ITEMS } onSelect={ onSelect } />
		);
		const link = screen.getAllByRole( 'link' )[ 1 ];
		const notPrevented = fireEvent.click( link, { button: 0 } );
		expect( notPrevented ).toBe( false );
		expect( onSelect ).toHaveBeenCalledWith( 'vendors', expect.anything() );
	} );

	test.each( [ [ 'ctrlKey' ], [ 'metaKey' ], [ 'shiftKey' ], [ 'altKey' ] ] )(
		'onSelect leaves a %s click to the browser (Review Focus 1)',
		( key ) => {
			const onSelect = jest.fn();
			render(
				<Tabs label="Sections" items={ ITEMS } onSelect={ onSelect } />
			);
			const notPrevented = fireEvent.click(
				screen.getAllByRole( 'link' )[ 1 ],
				{ button: 0, [ key ]: true }
			);
			expect( notPrevented ).toBe( true );
			expect( onSelect ).not.toHaveBeenCalled();
		}
	);

	test( 'onSelect leaves a middle click to the browser', () => {
		const onSelect = jest.fn();
		render(
			<Tabs label="Sections" items={ ITEMS } onSelect={ onSelect } />
		);
		expect(
			fireEvent.click( screen.getAllByRole( 'link' )[ 1 ], { button: 1 } )
		).toBe( true );
		expect( onSelect ).not.toHaveBeenCalled();
	} );

	test( 'without onSelect a click is never prevented', () => {
		render( <Tabs label="Sections" items={ ITEMS } /> );
		expect(
			fireEvent.click( screen.getAllByRole( 'link' )[ 1 ], { button: 0 } )
		).toBe( true );
	} );
} );

describe( 'Tabs -- underline variant (0.16.0)', () => {
	test( 'Tabs without variant renders the 0.15.2 markup byte for byte', () => {
		const { container } = render(
			<Tabs
				label="S"
				current="a"
				items={ [
					{ id: 'a', label: 'A', href: '?a', badge: 2 },
					{ id: 'b', label: 'B', href: '?b', badge: 0 },
				] }
			/>
		);
		expect( container.innerHTML ).toBe(
			'<nav class="mhmui-tabs" aria-label="S"><a href="?a" class="mhmui-tabs__tab mhmui-tabs__tab--current" aria-current="page">A<span class="mhmui-tabs__badge">2</span></a><a href="?b" class="mhmui-tabs__tab">B</a></nav>'
		);
	} );
	test( 'underline variant, a zero count shown on request, a warning chip', () => {
		const { container } = render(
			<Tabs
				label="S"
				variant="underline"
				showZero
				current="all"
				items={ [
					{ id: 'all', label: 'All', href: '?', badge: 7 },
					{
						id: 'p',
						label: 'Pending',
						href: '?p',
						badge: 2,
						badgeTone: 'warning',
					},
					{ id: 'c', label: 'Closed', href: '?c', badge: 0 },
				] }
			/>
		);
		expect( container.querySelector( 'nav' ).className ).toBe(
			'mhmui-tabs mhmui-tabs--underline'
		);
		expect(
			[ ...container.querySelectorAll( '.mhmui-tabs__badge' ) ].map(
				( b ) => [ b.textContent, b.className ]
			)
		).toEqual( [
			[ '7', 'mhmui-tabs__badge' ],
			[ '2', 'mhmui-tabs__badge mhmui-tabs__badge--warning' ],
			[ '0', 'mhmui-tabs__badge' ],
		] );
	} );
	test( 'an unknown variant or tone falls back silently', () => {
		const { container } = render(
			<Tabs
				label="S"
				variant="x"
				items={ [
					{
						id: 'a',
						label: 'A',
						href: '?a',
						badge: 1,
						badgeTone: 'purple',
					},
				] }
			/>
		);
		expect( container.querySelector( 'nav' ).className ).toBe(
			'mhmui-tabs'
		);
		expect(
			container.querySelector( '.mhmui-tabs__badge' ).className
		).toBe( 'mhmui-tabs__badge' );
	} );
	test( 'a negative count never draws a chip, even with showZero', () => {
		const { container } = render(
			<Tabs
				label="S"
				showZero
				items={ [ { id: 'a', label: 'A', href: '?a', badge: -3 } ] }
			/>
		);
		expect( container.querySelector( '.mhmui-tabs__badge' ) ).toBeNull();
	} );
} );
