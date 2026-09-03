/**
 * A small key-figure box: value on top, label underneath.
 *
 * @param {Object} props
 * @param {string} props.value  Already-formatted value.
 * @param {string} props.label  Translated label.
 * @param {string} [props.tone] blue | green | amber | grey | red.
 */
export default function KpiBox( { value, label, tone = 'grey' } ) {
	return (
		<div className={ `mhmui-kpi-box mhmui-kpi-box--${ tone }` }>
			<p className="mhmui-kpi-box__value">{ value }</p>
			<p className="mhmui-kpi-box__label">{ label }</p>
		</div>
	);
}
