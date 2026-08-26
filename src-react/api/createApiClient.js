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

	return {
		get: ( path, params ) =>
			apiFetch( { path: buildPath( path, params ) } ),
		post: ( path, data ) =>
			apiFetch( { path: buildPath( path ), method: 'POST', data } ),
		del: ( path, data ) =>
			apiFetch( { path: buildPath( path ), method: 'DELETE', data } ),
	};
}
