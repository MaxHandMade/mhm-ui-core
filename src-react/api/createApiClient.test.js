/**
 * The factory exists so that a product keeps its own endpoint map. ui-core must never
 * learn a product's REST namespace, so these tests assert shape, not routes.
 */
import { createApiClient } from './createApiClient';

describe( 'createApiClient', () => {
	let calls;
	let apiFetch;

	beforeEach( () => {
		calls = [];
		apiFetch = ( args ) => {
			calls.push( args );
			return Promise.resolve( 'ok' );
		};
	} );

	it( 'builds a GET path under the namespace it was given', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.get( '/customers' );

		expect( calls[ 0 ] ).toEqual( { path: '/mhm-rentiva/v1/customers' } );
	} );

	it( 'serves a different namespace from the same factory', async () => {
		const api = createApiClient( '/some-other/v2', apiFetch );

		await api.get( '/things' );

		expect( calls[ 0 ].path ).toBe( '/some-other/v2/things' );
	} );

	it( 'tolerates a trailing slash on the namespace without doubling it', async () => {
		const api = createApiClient( '/mhm-rentiva/v1/', apiFetch );

		await api.get( '/customers' );

		expect( calls[ 0 ].path ).toBe( '/mhm-rentiva/v1/customers' );
	} );

	it( 'tolerates a path given without a leading slash', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.get( 'customers' );

		expect( calls[ 0 ].path ).toBe( '/mhm-rentiva/v1/customers' );
	} );

	it( 'appends a query string built from params', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.get( '/customers', { page: 2, search: 'a b' } );

		expect( calls[ 0 ].path ).toBe(
			'/mhm-rentiva/v1/customers?page=2&search=a+b'
		);
	} );

	it( 'omits the question mark when params are empty', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.get( '/customers', {} );

		expect( calls[ 0 ].path ).toBe( '/mhm-rentiva/v1/customers' );
	} );

	it( 'sends POST with a data body', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.post( '/shortcode-pages/home/create', { force: true } );

		expect( calls[ 0 ] ).toEqual( {
			path: '/mhm-rentiva/v1/shortcode-pages/home/create',
			method: 'POST',
			data: { force: true },
		} );
	} );

	it( 'sends DELETE with a data body, which bulk deletes rely on', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.del( '/customers/bulk', { ids: [ 1, 2 ] } );

		expect( calls[ 0 ] ).toEqual( {
			path: '/mhm-rentiva/v1/customers/bulk',
			method: 'DELETE',
			data: { ids: [ 1, 2 ] },
		} );
	} );

	it( 'omits the data key entirely on a bodyless POST', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.post( '/shortcode-pages/clear-cache' );

		expect( calls[ 0 ] ).toEqual( {
			path: '/mhm-rentiva/v1/shortcode-pages/clear-cache',
			method: 'POST',
		} );
		expect( 'data' in calls[ 0 ] ).toBe( false );
	} );

	it( 'omits the data key entirely on a bodyless DELETE', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await api.del( '/shortcode-pages/home' );

		expect( 'data' in calls[ 0 ] ).toBe( false );
	} );

	it( 'returns whatever the injected transport resolves to', async () => {
		const api = createApiClient( '/mhm-rentiva/v1', apiFetch );

		await expect( api.get( '/customers' ) ).resolves.toBe( 'ok' );
	} );

	it( 'refuses to build a client without a transport, rather than failing at call time', () => {
		expect( () => createApiClient( '/mhm-rentiva/v1' ) ).toThrow();
	} );

	it( 'refuses an empty namespace, which would silently hit the site root', () => {
		expect( () => createApiClient( '', apiFetch ) ).toThrow();
	} );
} );
