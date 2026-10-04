import { render } from '@testing-library/react';
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
