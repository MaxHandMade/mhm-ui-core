/**
 * Previous / page x of y / next.
 *
 * @param {Object}   props
 * @param {number}   props.page       Current page, 1-based.
 * @param {number}   props.totalPages Total pages.
 * @param {Function} props.onChange   Called with the new page number.
 * @param {Object}   props.labels     { previous, next, of } -- translated by the consumer.
 */
export default function Pagination( { page, totalPages, onChange, labels } ) {
	const atStart = page <= 1;
	const atEnd = page >= totalPages;

	return (
		<nav
			className="mhmui-pagination"
			aria-label={ labels.navigation ?? '' }
		>
			<button
				type="button"
				className="button mhmui-pagination__button"
				disabled={ atStart }
				onClick={ () => onChange( page - 1 ) }
			>
				{ labels.previous }
			</button>
			<span className="mhmui-pagination__status">
				{ page } { labels.of } { totalPages }
			</span>
			<button
				type="button"
				className="button mhmui-pagination__button"
				disabled={ atEnd }
				onClick={ () => onChange( page + 1 ) }
			>
				{ labels.next }
			</button>
		</nav>
	);
}
