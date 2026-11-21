/**
 * A number from a number or a numeric string; `null` for anything else
 * (missing, null, NaN, '', 'abc', a boolean). page and totalPages often come
 * straight from a REST response or a query string.
 *
 * @param {unknown} value Raw prop.
 * @return {number|null} The number, or null when the value is not numeric.
 */
function numeric( value ) {
	const n =
		typeof value === 'string' && value.trim() !== ''
			? Number( value )
			: value;
	return typeof n === 'number' && ! Number.isNaN( n ) ? n : null;
}

/**
 * Previous / page x of y / next.
 *
 * @param {Object}                 props
 * @param {number|string}          props.page       Current page, 1-based; a fraction is cut
 *                                                  down. Not numeric -> 1.
 * @param {number|string}          props.totalPages Total pages; a fraction rounds up (an
 *                                                  unrounded total / perPage keeps its last
 *                                                  page), Infinity stays open-ended. Not
 *                                                  numeric -> 1.
 * @param {(page: number) => void} props.onChange   Called with the new page number, always a
 *                                                  number. Missing -> clicks do nothing.
 * @param {Object}                 props.labels     { previous, next, of, navigation, page } -- translated by the consumer.
 * @param {string}                 [props.variant]  'inline' (default) or 'footer' (table-card footer).
 * @param {string}                 [props.summary]  Footer only: range text shown on the left.
 */
export default function Pagination( {
	page: rawPage,
	totalPages: rawTotal,
	onChange,
	labels,
	variant = 'inline',
	summary,
} ) {
	// labels has no default: a missing or null prop must not crash the
	// render. The controls still draw, unlabelled.
	const text = labels && typeof labels === 'object' ? labels : {};
	// A whole number passes through unchanged, so valid input renders the
	// same markup as before; only missing or non-numeric input is replaced.
	// The two round apart on purpose: a fractional total must not hide its
	// last page, and Infinity (an unknown total) must leave next open, as it
	// did before 0.23.7.
	const pageNumber = numeric( rawPage );
	const totalNumber = numeric( rawTotal );
	const page =
		pageNumber !== null && Number.isFinite( pageNumber )
			? Math.trunc( pageNumber )
			: 1;
	const totalPages = totalNumber === null ? 1 : Math.ceil( totalNumber );
	const go = ( next ) => {
		if ( typeof onChange === 'function' ) {
			onChange( next );
		}
	};
	const atStart = page <= 1;
	const atEnd = page >= totalPages;

	if ( variant === 'footer' ) {
		const buttonClass =
			'mhmui-button mhmui-button--secondary mhmui-button--sm mhmui-pagination__button';
		return (
			<nav
				className="mhmui-pagination mhmui-pagination--footer"
				aria-label={ text.navigation ?? '' }
			>
				<span className="mhmui-pagination__summary">{ summary }</span>
				<div className="mhmui-pagination__controls">
					<button
						type="button"
						className={ buttonClass }
						disabled={ atStart }
						onClick={ () => go( page - 1 ) }
					>
						<span aria-hidden="true">‹</span>
						{ text.previous }
					</button>
					<span className="mhmui-pagination__status">
						{ text.page } { page } { text.of }{ ' ' }
						{ Math.max( 1, totalPages ) }
					</span>
					<button
						type="button"
						className={ buttonClass }
						disabled={ atEnd }
						onClick={ () => go( page + 1 ) }
					>
						{ text.next }
						<span aria-hidden="true">›</span>
					</button>
				</div>
			</nav>
		);
	}

	return (
		<nav className="mhmui-pagination" aria-label={ text.navigation ?? '' }>
			<button
				type="button"
				className="button mhmui-pagination__button"
				disabled={ atStart }
				onClick={ () => go( page - 1 ) }
			>
				{ text.previous }
			</button>
			<span className="mhmui-pagination__status">
				{ page } { text.of } { totalPages }
			</span>
			<button
				type="button"
				className="button mhmui-pagination__button"
				disabled={ atEnd }
				onClick={ () => go( page + 1 ) }
			>
				{ text.next }
			</button>
		</nav>
	);
}
