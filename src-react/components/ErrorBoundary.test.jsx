import { render, screen } from '@testing-library/react';
import ErrorBoundary from './ErrorBoundary';

const Boom = () => {
	throw new Error( 'component blew up' );
};

describe( 'ErrorBoundary', () => {
	let consoleError;

	beforeEach( () => {
		// React logs caught render errors; silence it so a passing suite stays readable.
		consoleError = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );
	} );

	afterEach( () => consoleError.mockRestore() );

	it( 'renders children while nothing throws', () => {
		render(
			<ErrorBoundary fallback={ <p>fallback</p> }>
				<p>content</p>
			</ErrorBoundary>
		);

		expect( screen.getByText( 'content' ) ).toBeTruthy();
	} );

	it( 'renders the host-supplied fallback when a child throws', () => {
		render(
			<ErrorBoundary fallback={ <p>host wording</p> }>
				<Boom />
			</ErrorBoundary>
		);

		expect( screen.getByText( 'host wording' ) ).toBeTruthy();
	} );

	it( 'renders nothing rather than crashing when no fallback was given', () => {
		const { container } = render(
			<ErrorBoundary>
				<Boom />
			</ErrorBoundary>
		);

		expect( container.innerHTML ).toBe( '' );
	} );

	it( 'carries no translated string of its own — the wording belongs to the host', () => {
		render(
			<ErrorBoundary fallback={ <p>bir hata olustu</p> }>
				<Boom />
			</ErrorBoundary>
		);

		expect( screen.getByText( 'bir hata olustu' ) ).toBeTruthy();
	} );
} );
