/**
 * Server-paged list state (0.20.0). This file holds the pure helpers; the
 * hook itself follows below them.
 */

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
