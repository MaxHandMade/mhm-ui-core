/**
 * One statistic on a coloured card: label, value, optional delta or sub line.
 *
 * Extracted from Rentiva's dashboard StatsCards. Every string is a prop --
 * this package has no text domain, so the consumer formats the value and
 * translates the label before rendering.
 *
 * @param {Object} props
 * @param {string} props.label   Translated label.
 * @param {string} props.value   Already-formatted value.
 * @param {string} [props.icon]  Dashicons class suffix, e.g. "calendar-alt".
 * @param {string} [props.tone]  blue | green | amber | grey | red.
 * @param {string} [props.sub]   Secondary line, shown when there is no delta.
 * @param {Object} [props.delta] { direction: 'up'|'down'|'flat', text: string }.
 */
export default function StatCard( {
	label,
	value,
	icon,
	tone = 'blue',
	sub,
	delta,
} ) {
	let line = null;
	if ( delta && delta.direction !== 'flat' ) {
		line = (
			<p
				className={ `mhmui-stat-card__delta mhmui-stat-card__delta--${ delta.direction }` }
			>
				{ delta.text }
			</p>
		);
	} else if ( sub ) {
		line = <p className="mhmui-stat-card__sub">{ sub }</p>;
	}

	return (
		<div className={ `mhmui-stat-card mhmui-stat-card--${ tone }` }>
			{ icon && (
				<span
					className={ `dashicons dashicons-${ icon }` }
					aria-hidden="true"
				/>
			) }
			<div className="mhmui-stat-card__body">
				<p className="mhmui-stat-card__label">{ label }</p>
				<p className="mhmui-stat-card__value">{ value }</p>
				{ line }
			</div>
		</div>
	);
}
