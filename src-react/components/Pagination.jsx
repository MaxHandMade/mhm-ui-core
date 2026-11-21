/**
 * A whole number from a number or a numeric string; `fallback` for
 * anything else (missing, null, NaN, Infinity, '', 'abc'). page and
 * totalPages often come straight from a REST response or a query string.
 *
 * @param {unknown} value    Raw prop.
 * @param {number}  fallback Used when the value is not numeric.
 * @return {number} The truncated number, or the fallback.
 */
function wholeNumber( value, fallback ) {
	const n =
		typeof value === 'string' && value.trim() !== ''
			? Number( value )
			: value;
	return typeof n === 'number' && Number.isFinite( n )
		? Math.trunc( n )
		: fallback;
}

/**
 * Previous / page x of y / next.
 *
 * @param {Object}                 props
 * @param {number|string}          props.page       Current page, 1-based. Not numeric -> 1.
 * @param {number|string}          props.totalPages Total pages. Not numeric -> 1.
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
	// A valid number passes through unchanged, so valid input renders the
	// same markup as before; only missing or non-numeric input is replaced.
	const page = wholeNumber( rawPage, 1 );
	const totalPages = wholeNumber( rawTotal, 1 );
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
