import { render, screen, fireEvent, act } from '@testing-library/react';
import { Widget } from '../index';

test( 'default Widget keeps the 0.15.2 markup', () => {
	const { container } = render( <Widget title="Recent">Body</Widget> );
	expect( container.innerHTML ).toBe(
		'<section class="mhmui-widget"><header class="mhmui-widget__header"><h3 class="mhmui-widget__title">Recent</h3></header><div class="mhmui-widget__body">Body</div></section>'
	);
} );

test( 'level 2 plain section', () => {
	const { container } = render(
		<Widget title="Customer" level={ 2 } variant="plain">
			Body
		</Widget>
	);
	expect( container.firstChild.className ).toBe(
		'mhmui-widget mhmui-widget--level-2 mhmui-widget--plain'
	);
	expect(
		container.querySelector( 'h2.mhmui-widget__title' ).textContent
	).toBe( 'Customer' );
} );

test( 'level 2 banded card keeps its header and actions', () => {
	const { container } = render(
		<Widget title="Message" level={ 2 } actions={ <a href="#a">Go</a> }>
			Body
		</Widget>
	);
	expect( container.firstChild.className ).toBe(
		'mhmui-widget mhmui-widget--level-2'
	);
	expect(
		container.querySelector( 'header .mhmui-widget__actions a' )
	).not.toBeNull();
} );

test( 'level 3 plain card is valid on its own', () => {
	const { container } = render(
		<Widget title="Side" variant="plain">
			B
		</Widget>
	);
	expect( container.firstChild.className ).toBe(
		'mhmui-widget mhmui-widget--plain'
	);
	expect( container.querySelector( 'h3' ) ).not.toBeNull();
} );

test( 'unknown level and variant fall back', () => {
	const { container } = render(
		<Widget title="X" level={ 5 } variant="x">
			B
		</Widget>
	);
	expect( [
		container.firstChild.className,
		container.querySelector( 'h3' ) !== null,
	] ).toEqual( [ 'mhmui-widget', true ] );
} );

// 0.16.0 byte pins, captured once by rendering these calls on the UNCHANGED
// component at 629fb8c (clean tree) and pasted here. A call without
// `collapsible` -- or with `collapsible={ false }` -- must keep them.
const PIN_LEVEL2_PLAIN =
	'<section class="mhmui-widget mhmui-widget--level-2 mhmui-widget--plain"><header class="mhmui-widget__header"><h2 class="mhmui-widget__title">Customer</h2></header><div class="mhmui-widget__body">Body</div></section>';
const PIN_ACTIONS =
	'<section class="mhmui-widget mhmui-widget--level-2"><header class="mhmui-widget__header"><h2 class="mhmui-widget__title"><span class="dashicons dashicons-chart-line" aria-hidden="true"></span>Message<span class="mhmui-widget__subtitle">Two</span></h2><div class="mhmui-widget__actions"><a href="#a">Go</a></div></header><div class="mhmui-widget__body">Body</div></section>';

describe( 'Widget without collapsible keeps the 0.16.0 markup byte for byte', () => {
	const plain = ( extra ) => (
		<Widget title="Customer" level={ 2 } variant="plain" { ...extra }>
			Body
		</Widget>
	);
	const withActions = ( extra ) => (
		<Widget
			title="Message"
			subtitle="Two"
			icon="rate"
			level={ 2 }
			actions={ <a href="#a">Go</a> }
			{ ...extra }
		>
			Body
		</Widget>
	);

	test.each( [
		[ 'omitted', {} ],
		[ 'false', { collapsible: false } ],
		[
			'false, the other new props ignored',
			{
				collapsible: false,
				defaultOpen: false,
				storageKey: 'k',
				open: false,
			},
		],
	] )( 'collapsible %s', ( _, extra ) => {
		expect( render( plain( extra ) ).container.innerHTML ).toBe(
			PIN_LEVEL2_PLAIN
		);
		expect( render( withActions( extra ) ).container.innerHTML ).toBe(
			PIN_ACTIONS
		);
	} );
} );

describe( 'collapsible Widget', () => {
	beforeEach( () => window.localStorage.clear() );

	const card = ( props = {} ) => (
		<Widget
			title="Customer"
			collapsible
			actions={ <a href="#e">Edit</a> }
			{ ...props }
		>
			<p>Body</p>
			<button type="button">Inner</button>
			<input aria-label="Note" />
		</Widget>
	);
	const toggleOf = () => screen.getByRole( 'button', { name: 'Customer' } );

	test( 'a collapsible widget is a disclosure', () => {
		const { container } = render( card() );
		const toggle = toggleOf();
		const body = container.querySelector( '.mhmui-widget__body' );
		const root = container.firstChild;
		expect( toggle.getAttribute( 'type' ) ).toBe( 'button' );
		expect( toggle.className ).toBe( 'mhmui-widget__toggle' );
		expect( toggle.closest( 'h3.mhmui-widget__title' ) ).not.toBeNull();
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( body.id ).not.toBe( '' );
		expect( toggle.getAttribute( 'aria-controls' ) ).toBe( body.id );
		expect( body.hidden ).toBe( false );
		expect( root.className ).toBe(
			'mhmui-widget mhmui-widget--collapsible'
		);
		expect(
			container.querySelector( '.mhmui-widget__actions a' )
		).not.toBeNull();
		const chevron = toggle.querySelector( 'span.mhmui-widget__chevron' );
		expect( chevron.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		const svg = chevron.querySelector( 'svg' );
		expect( [
			svg.getAttribute( 'aria-hidden' ),
			svg.getAttribute( 'focusable' ),
		] ).toEqual( [ 'true', 'false' ] );

		fireEvent.click( toggle );
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( body.hidden ).toBe( true );
		expect(
			container.querySelector( '.mhmui-widget__actions' )
		).toBeNull();
		expect( root.className ).toBe(
			'mhmui-widget mhmui-widget--collapsible mhmui-widget--collapsed'
		);

		fireEvent.click( toggle );
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( body.hidden ).toBe( false );
		expect(
			container.querySelector( '.mhmui-widget__actions a' )
		).not.toBeNull();
		expect( root.className ).toBe(
			'mhmui-widget mhmui-widget--collapsible'
		);
	} );

	test( 'the toggle is named by title and subtitle, not by the icon or chevron', () => {
		render( card( { subtitle: 'Booking', icon: 'rate' } ) );
		expect(
			screen.getByRole( 'button', { name: 'Customer Booking' } )
		).toBeTruthy();
	} );

	test( 'defaultOpen false starts closed', () => {
		const { container } = render( card( { defaultOpen: false } ) );
		expect( toggleOf().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( container.querySelector( '.mhmui-widget__body' ).hidden ).toBe(
			true
		);
	} );

	test( 'the open state is remembered under storageKey', () => {
		const first = render( card( { storageKey: 'mhmui-test-card' } ) );
		fireEvent.click( toggleOf() );
		expect( window.localStorage.getItem( 'mhmui-test-card' ) ).toBe( '0' );
		first.unmount();
		render( card( { storageKey: 'mhmui-test-card' } ) );
		expect( toggleOf().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	test( 'controlled open/onToggle wins over defaultOpen and storage', () => {
		window.localStorage.setItem( 'mhmui-test-card', '0' );
		const onToggle = jest.fn();
		const { container } = render(
			card( {
				open: true,
				defaultOpen: false,
				storageKey: 'mhmui-test-card',
				onToggle,
			} )
		);
		const toggle = toggleOf();
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		fireEvent.click( toggle );
		expect( onToggle.mock.calls ).toEqual( [ [ false ] ] );
		// The prop did not change, so the card did not either -- and storage
		// is neither read nor written in controlled mode.
		expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( container.querySelector( '.mhmui-widget__body' ).hidden ).toBe(
			false
		);
		expect( window.localStorage.getItem( 'mhmui-test-card' ) ).toBe( '0' );
	} );

	test( 'onToggle fires in uncontrolled mode too', () => {
		const onToggle = jest.fn();
		render( card( { onToggle } ) );
		fireEvent.click( toggleOf() );
		fireEvent.click( toggleOf() );
		expect( onToggle.mock.calls ).toEqual( [ [ false ], [ true ] ] );
	} );

	test( 'closing a card with focus inside moves focus to its toggle', () => {
		// Controlled: the parent closes the card (no click at all).
		const { rerender, unmount } = render( card( { open: true } ) );
		const inner = screen.getByRole( 'button', { name: 'Inner' } );
		inner.focus();
		expect( toggleOf().ownerDocument.activeElement ).toBe( inner );
		rerender( card( { open: false } ) );
		expect( toggleOf().ownerDocument.activeElement ).toBe( toggleOf() );
		unmount();

		// Uncontrolled: fireEvent.click does not move focus (userEvent.click
		// would, and the assertion would then prove nothing).
		render( card() );
		const inner2 = screen.getByRole( 'button', { name: 'Inner' } );
		inner2.focus();
		fireEvent.click( toggleOf() );
		expect( toggleOf().ownerDocument.activeElement ).toBe( toggleOf() );
	} );

	test( 'closing a card with focus outside leaves the focus alone', () => {
		const tree = ( open ) => (
			<>
				<button type="button">Outside</button>
				{ card( { open } ) }
			</>
		);
		const { rerender } = render( tree( true ) );
		const outside = screen.getByRole( 'button', { name: 'Outside' } );
		outside.focus();
		rerender( tree( false ) );
		expect( toggleOf().ownerDocument.activeElement ).toBe( outside );
	} );

	test( 'closing a card with focus on a header action moves focus to its toggle', () => {
		// The actions unmount when the card closes, so by the time the card
		// reacts the focused node is gone and the document's active element
		// is already <body>.
		const withAction = ( props ) =>
			card( {
				actions: <button type="button">Act</button>,
				...props,
			} );
		const { rerender, unmount } = render( withAction( { open: true } ) );
		screen.getByRole( 'button', { name: 'Act' } ).focus();
		rerender( withAction( { open: false } ) );
		expect( toggleOf().ownerDocument.activeElement ).toBe( toggleOf() );
		unmount();

		// Uncontrolled click path.
		render( withAction() );
		screen.getByRole( 'button', { name: 'Act' } ).focus();
		fireEvent.click( toggleOf() );
		expect( toggleOf().ownerDocument.activeElement ).toBe( toggleOf() );
	} );

	test( 'focus that left a header action for outside the card is not taken back', () => {
		const tree = ( open ) => (
			<>
				<button type="button">Outside</button>
				{ card( {
					open,
					actions: <button type="button">Act</button>,
				} ) }
			</>
		);
		const { rerender } = render( tree( true ) );
		screen.getByRole( 'button', { name: 'Act' } ).focus();
		const outside = screen.getByRole( 'button', { name: 'Outside' } );
		outside.focus();
		rerender( tree( false ) );
		expect( toggleOf().ownerDocument.activeElement ).toBe( outside );
	} );

	describe( 'focus released to the page before the card closes', () => {
		// A click on empty page space blurs the focused node with no
		// relatedTarget and leaves <body> as the active element. jsdom's own
		// blur() passes the Document as relatedTarget instead, so the browser
		// event is dispatched by hand and the active element is stubbed.
		let activeSpy;
		const releaseToPage = ( el ) => {
			fireEvent.focusOut( el, { relatedTarget: null } );
			activeSpy = jest
				.spyOn( el.ownerDocument, 'activeElement', 'get' )
				.mockReturnValue( el.ownerDocument.body );
		};
		// Lets the click on empty space finish before anything else happens.
		const later = () => act( async () => {} );
		afterEach( () => {
			if ( activeSpy ) {
				activeSpy.mockRestore();
				activeSpy = null;
			}
		} );
		const withAction = ( props ) =>
			card( {
				actions: <button type="button">Act</button>,
				...props,
			} );

		test.each( [
			[ 'the body', 'Inner' ],
			[ 'a header action', 'Act' ],
		] )(
			'focus left %s for the page, then a controlled close does not take it',
			async ( _where, name ) => {
				const { rerender } = render( withAction( { open: true } ) );
				const from = screen.getByRole( 'button', { name } );
				from.focus();
				const spy = jest.spyOn( toggleOf(), 'focus' );
				releaseToPage( from );
				await later();
				rerender( withAction( { open: false } ) );
				expect( spy ).not.toHaveBeenCalled();
			}
		);

		test( 'focus left the body for the page, then a storage key change closes the card without taking it', async () => {
			window.localStorage.setItem( 'card-b', '0' );
			const { rerender } = render( card( { storageKey: 'card-a' } ) );
			const from = screen.getByRole( 'button', { name: 'Inner' } );
			from.focus();
			const spy = jest.spyOn( toggleOf(), 'focus' );
			releaseToPage( from );
			await later();
			rerender( card( { storageKey: 'card-b' } ) );
			expect( toggleOf().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
			expect( spy ).not.toHaveBeenCalled();
		} );

		test.each( [
			[ 'the body', 'Inner' ],
			[ 'a header action', 'Act' ],
		] )(
			'a toggle click that does not focus the button still hands focus from %s to the toggle',
			async ( _where, name ) => {
				// Safari: pressing a button does not focus it; the press blurs the
				// focused node to nowhere and the click closes the card after.
				render( withAction() );
				const from = screen.getByRole( 'button', { name } );
				from.focus();
				const spy = jest.spyOn( toggleOf(), 'focus' );
				fireEvent.mouseDown( toggleOf() );
				releaseToPage( from );
				await later();
				fireEvent.mouseUp( toggleOf() );
				fireEvent.click( toggleOf() );
				expect( spy ).toHaveBeenCalled();
			}
		);
	} );

	test( 'a closed collapsible body carries an inline display:none and the open one carries no style', () => {
		// The inline declaration beats a consumer's own page-scoped
		// `display` rule on the body, which the plain [hidden] guard in
		// admin.css does not (and the kit writes no !important).
		const { container } = render( card() );
		const body = container.querySelector( '.mhmui-widget__body' );
		expect( body.hasAttribute( 'style' ) ).toBe( false );
		fireEvent.click( toggleOf() );
		expect( [ body.hidden, body.getAttribute( 'style' ) ] ).toEqual( [
			true,
			'display: none;',
		] );
		fireEvent.click( toggleOf() );
		expect( [ body.hidden, body.hasAttribute( 'style' ) ] ).toEqual( [
			false,
			false,
		] );
	} );

	test( 'the body stays mounted while collapsed', () => {
		const { container } = render( card() );
		const input = screen.getByRole( 'textbox', { name: 'Note' } );
		fireEvent.change( input, { target: { value: 'kept' } } );
		fireEvent.click( toggleOf() );
		expect( container.querySelector( 'input' ) ).toBe( input );
		fireEvent.click( toggleOf() );
		expect( screen.getByRole( 'textbox', { name: 'Note' } ).value ).toBe(
			'kept'
		);
	} );
} );
