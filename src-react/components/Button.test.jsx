import { render, fireEvent } from '@testing-library/react';
import Button from './Button';

describe( 'Button', () => {
	test( 'a button with defaults', () => {
		const { container } = render( <Button>Apply</Button> );
		expect( container.innerHTML ).toBe(
			'<button type="button" class="mhmui-button mhmui-button--secondary mhmui-button--md">Apply</button>'
		);
	} );
	test( "a caller's aria-disabled cannot override the computed state", () => {
		const a = render(
			<Button disabled aria-disabled={ undefined }>
				X
			</Button>
		);
		expect( a.container.firstChild.getAttribute( 'aria-disabled' ) ).toBe(
			'true'
		);
		a.unmount();
		const b = render(
			<Button disabled aria-disabled="false">
				X
			</Button>
		);
		expect( b.container.firstChild.getAttribute( 'aria-disabled' ) ).toBe(
			'true'
		);
		b.unmount();
		const c = render( <Button aria-disabled="true">X</Button> );
		expect( c.container.firstChild.getAttribute( 'aria-disabled' ) ).toBe(
			'true'
		);
	} );
	test( 'href renders a link; unknown variant and size fall back', () => {
		const { container } = render(
			<Button href="?x=1" variant="pink" size="xl">
				Open
			</Button>
		);
		expect( container.innerHTML ).toBe(
			'<a href="?x=1" class="mhmui-button mhmui-button--secondary mhmui-button--md">Open</a>'
		);
	} );
	test( 'disabled keeps focus and swallows the click; a disabled link has no href', () => {
		const onClick = jest.fn();
		const { getByRole, container } = render(
			<>
				<Button disabled onClick={ onClick }>
					Send
				</Button>
				<Button disabled href="?y">
					Go
				</Button>
			</>
		);
		fireEvent.click( getByRole( 'button' ) );
		expect( onClick ).not.toHaveBeenCalled();
		expect( getByRole( 'button' ).getAttribute( 'aria-disabled' ) ).toBe(
			'true'
		);
		expect( container.querySelector( 'a' ).hasAttribute( 'href' ) ).toBe(
			false
		);
	} );
	test( 'a disabled link stays focusable', () => {
		const { container } = render(
			<Button disabled href="?y">
				Go
			</Button>
		);
		const a = container.querySelector( 'a' );
		a.focus();
		expect( a.tabIndex ).toBe( 0 );
		expect( a.ownerDocument.activeElement ).toBe( a );
	} );
	test( 'the neutral variant gets its class', () => {
		const { container } = render( <Button variant="neutral">Copy</Button> );
		expect( container.firstChild.className ).toBe(
			'mhmui-button mhmui-button--neutral mhmui-button--md'
		);
	} );
	test( 'only id, aria-* and data-* pass through', () => {
		const { getByRole } = render(
			<Button
				id="b"
				aria-label="Open: X"
				data-id="7"
				style={ { color: 'red' } }
				className="x"
			>
				Open
			</Button>
		);
		const b = getByRole( 'button' );
		expect( [
			b.id,
			b.getAttribute( 'aria-label' ),
			b.dataset.id,
			b.getAttribute( 'style' ),
			b.className,
		] ).toEqual( [
			'b',
			'Open: X',
			'7',
			null,
			'mhmui-button mhmui-button--secondary mhmui-button--md',
		] );
	} );
	test( 'the icon is decorative', () => {
		const { container } = render(
			<Button variant="primary" icon={ <svg /> }>
				Reply
			</Button>
		);
		expect(
			container
				.querySelector( '.mhmui-button__icon' )
				.getAttribute( 'aria-hidden' )
		).toBe( 'true' );
	} );
} );
