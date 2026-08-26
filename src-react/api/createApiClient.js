/**
 * Builds a REST client bound to one namespace.
 *
 * ui-core deliberately does NOT ship any product's endpoint map: a shared package that
 * knows `/mhm-rentiva/v1/customers` would grow a second product's routes next to it.
 * The host keeps its own map and builds it on top of this client.
 *
 * The transport is injected rather than imported so that this module carries no
 * WordPress dependency and can be tested without one.
 *
 * @param {string}   namespace - REST namespace, e.g. '/my-plugin/v1'.
 * @param {Function} apiFetch  - Transport, normally @wordpress/api-fetch.
 * @return {{ get: Function, post: Function, del: Function }} Client bound to the namespace.
 */
export function createApiClient( namespace, apiFetch ) {
	if ( typeof apiFetch !== 'function' ) {
		throw new TypeError(
			'createApiClient requires a transport function as its second argument (pass @wordpress/api-fetch).'
		);
	}

	if ( typeof namespace !== 'string' || namespace.trim() === '' ) {
		throw new TypeError(
			'createApiClient requires a non-empty REST namespace, e.g. "/my-plugin/v1".'
		);
	}

	const base = namespace.replace( /\/+$/, '' );

	const buildPath = ( path, params ) => {
		const suffix = path.startsWith( '/' ) ? path : `/${ path }`;
		const query = params ? new URLSearchParams( params ).toString() : '';
		return query
			? `${ base }${ suffix }?${ query }`
			: `${ base }${ suffix }`;
	};

	// `data` is omitted rather than passed as undefined when the caller sends no
	// body. A bodyless POST must reach the transport as { path, method }, exactly
	// as a hand-written apiFetch call would: that keeps this client's request
	// shape independent of how a given transport treats an undefined `data` key.
	const send = ( method, path, data ) =>
		data === undefined
			? apiFetch( { path: buildPath( path ), method } )
			: apiFetch( { path: buildPath( path ), method, data } );

	return {
		get: ( path, params ) =>
			apiFetch( { path: buildPath( path, params ) } ),
		post: ( path, data ) => send( 'POST', path, data ),
		del: ( path, data ) => send( 'DELETE', path, data ),
	};
}
