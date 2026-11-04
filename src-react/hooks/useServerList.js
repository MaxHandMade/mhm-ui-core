/**
 * Server-paged list state (0.20.0): the pure helpers first, the hook below.
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';

const DROPPED = [ '', null, undefined ];
const isCount = ( n ) => Number.isInteger( n ) && n >= 0;

/**
 * The rows a page shows, for a "41-48 of 48" line. The words are the
 * consumer's own translation; this only does the arithmetic.
 *
 * @param {number} page    1-based page.
 * @param {number} perPage Rows per page.
 * @param {number} total   Rows in the whole list.
 * @return {{from: number, to: number, total: number}} 0/0 when the page holds nothing.
 */
export function pageRange( page, perPage, total ) {
	const from = ( page - 1 ) * perPage + 1;
	if ( total === 0 || from > total ) {
		return { from: 0, to: 0, total };
	}
	return { from, to: Math.min( page * perPage, total ), total };
}

/**
 * Request params: the query without '', null or undefined values, then the
 * page (and the page size when there is one). Nothing else is dropped --
 * what 'all', 0 or false mean is the consumer's server's business.
 *
 * @param {Object} query   The consumer's query keys.
 * @param {number} page    1-based page.
 * @param {number} perPage Rows per page, or undefined to let the server decide.
 * @return {Object} Params for the consumer's fetch function.
 */
export function buildParams( query, page, perPage ) {
	const params = {};
	Object.keys( query ).forEach( ( key ) => {
		if (
			key !== 'page' &&
			key !== 'per_page' &&
			! DROPPED.includes( query[ key ] )
		) {
			params[ key ] = query[ key ];
		}
	} );
	params.page = page;
	if ( Number.isInteger( perPage ) && perPage >= 1 ) {
		params.per_page = perPage;
	}
	return params;
}

/**
 * Totals as whole non-negative numbers; anything else reads as 0.
 *
 * @param {Object} raw What the consumer's readTotals returned.
 * @return {{total: number, pages: number}} Safe totals.
 */
export function normalizeTotals( raw ) {
	const total = raw?.total;
	const pages = raw?.pages;
	return {
		total: isCount( total ) ? total : 0,
		pages: isCount( pages ) ? pages : 0,
	};
}

const RESERVED = [ 'page', 'per_page' ];

const withoutReserved = ( patch ) => {
	const out = {};
	Object.keys( patch || {} ).forEach( ( key ) => {
		if ( ! RESERVED.includes( key ) ) {
			out[ key ] = patch[ key ];
		}
	} );
	return out;
};

const defaultReadTotals = ( res ) => ( {
	total: res?.total,
	pages: res?.pages,
} );

// Every update that sends a request goes through here: a new request
// counter, loading up and the last error cleared in the SAME update, so no
// commit in between shows "not loading" and an answer to an older request
// can never match the counter again (spec v2.2 rules 1 and 3).
const requesting = ( prev, change ) => ( {
	...prev,
	...change,
	nonce: prev.nonce + 1,
	loading: true,
	accepted: { ...prev.accepted, error: null },
} );

/**
 * Server-paged list state (0.20.0): query, page, the last accepted answer
 * and a ready `pagination` for the kit Pagination.
 *
 * - `setQuery( patch )` merges the patch and goes back to page 1 in ONE
 *   update, so a filter change sends exactly one request. A patch whose
 *   values all equal the current ones changes nothing. Query values must be
 *   primitive (an array is a new reference on every render); send several
 *   values as a comma-separated string, e.g. 'failed,retry'.
 * - Only the newest request is accepted; an older answer, success or
 *   failure, is dropped. Unmounting drops everything still in flight.
 * - An accepted answer whose page count is lower than the current page
 *   pulls the page back to the last one (one more request).
 * - `error` is the raw rejection; turning it into words is the consumer's
 *   job, and so is any notice, selection or bulk-action state.
 * - In React StrictMode (development) the mount effect runs twice, so two
 *   mount requests go out and only the second is accepted.
 *
 * @param {Function} fetchPage              Called with the params (the query
 *                                          without '', null and undefined,
 *                                          then page and per_page); returns a Promise.
 * @param {Object}   [options]              Options.
 * @param {Object}   [options.initialQuery] The consumer's query keys.
 * @param {number}   [options.perPage]      Sent as `per_page`; enables `range`.
 * @param {Function} [options.readTotals]   ( res ) => ( { total, pages } ).
 * @return {Object} query, setQuery, page, setPage, data, loading, error,
 *                  refetch, total, totalPages, range and pagination.
 */
export function useServerList( fetchPage, options = {} ) {
	const { initialQuery, perPage, readTotals } = options;

	const [ state, setState ] = useState( () => ( {
		query: withoutReserved( initialQuery ),
		page: 1,
		nonce: 0,
		loading: true,
		accepted: {
			data: null,
			error: null,
			page: 1,
			perPage: undefined,
			total: 0,
			pages: 0,
		},
	} ) );

	// The last COMMITTED fetchPage and options; written after commit, never
	// during render, and declared before the request effect so it runs first.
	const latest = useRef( { fetchPage, perPage, readTotals } );
	useEffect( () => {
		latest.current = { fetchPage, perPage, readTotals };
	} );

	const { query, page, nonce } = state;

	useEffect( () => {
		let alive = true;
		const cap = {
			nonce,
			page,
			perPage: latest.current.perPage,
			readTotals: latest.current.readTotals || defaultReadTotals,
		};

		const reject = ( error ) =>
			setState( ( prev ) =>
				prev.nonce !== cap.nonce
					? prev
					: {
							...prev,
							loading: false,
							accepted: { ...prev.accepted, error },
					  }
			);

		let request;
		try {
			request = Promise.resolve(
				latest.current.fetchPage(
					buildParams( query, page, cap.perPage )
				)
			);
		} catch ( error ) {
			// A fetchPage that throws instead of rejecting.
			request = Promise.reject( error );
		}

		request.then(
			( res ) => {
				if ( ! alive ) {
					return;
				}
				let totals;
				try {
					totals = normalizeTotals( cap.readTotals( res ) );
				} catch ( error ) {
					reject( error );
					return;
				}
				setState( ( prev ) => {
					if ( prev.nonce !== cap.nonce ) {
						return prev;
					}
					const last = Math.max( 1, totals.pages );
					if ( prev.page > last ) {
						return {
							...prev,
							page: last,
							nonce: prev.nonce + 1,
							loading: true,
						};
					}
					return {
						...prev,
						loading: false,
						accepted: {
							data: res,
							error: null,
							page: cap.page,
							perPage: cap.perPage,
							total: totals.total,
							pages: totals.pages,
						},
					};
				} );
			},
			( error ) => {
				if ( alive ) {
					reject( error );
				}
			}
		);

		return () => {
			alive = false;
		};
	}, [ query, page, nonce ] ); // eslint-disable-line react-hooks/exhaustive-deps -- `latest` is a ref; the three fields are the request's identity (spec v2.2 rule 1).

	const setQuery = useCallback(
		( patch ) =>
			setState( ( prev ) => {
				const next = withoutReserved( patch );
				const same = Object.keys( next ).every( ( key ) =>
					Object.is( prev.query[ key ], next[ key ] )
				);
				return same
					? prev
					: requesting( prev, {
							query: { ...prev.query, ...next },
							page: 1,
					  } );
			} ),
		[]
	);

	const setPage = useCallback(
		( n ) =>
			setState( ( prev ) =>
				! Number.isInteger( n ) || n < 1 || n === prev.page
					? prev
					: requesting( prev, { page: n } )
			),
		[]
	);

	const refetch = useCallback(
		() => setState( ( prev ) => requesting( prev, {} ) ),
		[]
	);

	const { accepted } = state;
	const totalPages = Math.max( 1, accepted.pages );
	const range =
		Number.isInteger( accepted.perPage ) && accepted.perPage >= 1
			? pageRange( accepted.page, accepted.perPage, accepted.total )
			: null;

	return {
		query,
		setQuery,
		page,
		setPage,
		data: accepted.data,
		loading: state.loading,
		error: accepted.error,
		refetch,
		total: accepted.total,
		totalPages,
		range,
		pagination: { page, totalPages, onChange: setPage },
	};
}
