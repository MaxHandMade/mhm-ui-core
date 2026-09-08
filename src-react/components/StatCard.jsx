/**
 * One statistic: label, value, optional icon, delta or sub line.
 *
 * Extracted from Rentiva's dashboard StatsCards. Every string is a prop --
 * this package has no text domain, so the consumer formats the value and
 * translates the label before rendering.
 *
 * This is the kit's ONLY key-figure component. It absorbed KpiBox (unreleased
 * at the time of writing; the merge landed after v0.9.6):
 * that component was the quiet bordered box and this one the filled card,
 * and nothing stated which a screen should pick -- so the two would have
 * drifted apart screen by screen. Both looks survive here, as one prop.
 *
 * @param {Object} props
 * @param {string} props.label   Translated label.
 * @param {string} props.value   Already-formatted value.
 * @param {string} [props.icon]  Dashicons class suffix, e.g. "calendar-alt".
 * @param {string} [props.tone]  blue | green | amber | grey | red. Omit it for
 *                               the quiet bordered box, which is the default:
 *                               a key figure carries no colour until the
 *                               meaning asks for one. A tone fills the card.
 * @param {string} [props.sub]   Secondary line, shown when there is no delta.
 * @param {Object} [props.delta] { direction: 'up'|'down'|'flat', text: string }.
 */
export default function StatCard( {
	label,
	value,
	icon,
	tone = '',
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
		<div
			className={
				tone
					? `mhmui-stat-card mhmui-stat-card--${ tone }`
					: 'mhmui-stat-card'
			}
		>
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
