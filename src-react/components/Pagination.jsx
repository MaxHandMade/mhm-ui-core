/**
 * Previous / page x of y / next.
 *
 * @param {Object}                 props
 * @param {number}                 props.page       Current page, 1-based.
 * @param {number}                 props.totalPages Total pages.
 * @param {(page: number) => void} props.onChange   Called with the new page number.
 * @param {Object}                 props.labels     { previous, next, of, navigation, page } -- translated by the consumer.
 * @param {string}                 [props.variant]  'inline' (default) or 'footer' (table-card footer).
 * @param {string}                 [props.summary]  Footer only: range text shown on the left.
 */
export default function Pagination( {
	page,
	totalPages,
	onChange,
	labels,
	variant = 'inline',
	summary,
} ) {
	// labels has no default: a missing or null prop must not crash the
	// render. The controls still draw, unlabelled.
	const text = labels && typeof labels === 'object' ? labels : {};
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
						onClick={ () => onChange( page - 1 ) }
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
						onClick={ () => onChange( page + 1 ) }
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
				onClick={ () => onChange( page - 1 ) }
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
				onClick={ () => onChange( page + 1 ) }
			>
				{ text.next }
			</button>
		</nav>
	);
}
