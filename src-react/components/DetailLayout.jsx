/**
 * Two-column detail layout: main column and a sticky aside that drops below
 * the main column when the container gets narrow (flex-wrap -- the admin
 * stylesheet has no container queries; the same wrap-by-basis idea as the
 * StatsGrid formula). Both columns stack their children with a gap, and a kit
 * Widget inside loses its own top margin here (the gap already spaces it).
 *
 * @param {Object} props
 * @param {*}      props.children     Main column.
 * @param {*}      [props.aside]      Side column.
 * @param {string} [props.asideLabel] Accessible name of the <aside>.
 */
export default function DetailLayout( { children, aside, asideLabel } ) {
	return (
		<div className="mhmui-detail-layout">
			<div className="mhmui-detail-layout__main">{ children }</div>
			{ aside && (
				<aside
					className="mhmui-detail-layout__aside"
					aria-label={ asideLabel ? asideLabel : undefined }
				>
					{ aside }
				</aside>
			) }
		</div>
	);
}
