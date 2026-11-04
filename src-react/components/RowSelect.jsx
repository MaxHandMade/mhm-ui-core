import { useId } from '@wordpress/element';

/**
 * The box that selects one row (0.19.0). Plain props.
 *
 * The name is the label's visually hidden text (never shown, also stacked).
 * The box sits in a label that is the hit area: at least 24px in the wide
 * layout (WCAG 2.2 SC 2.5.8 -- the row itself can be a click target) and
 * 44px stacked and below 782px. A row whose box is checked is tinted by the
 * kit (`tr:has( .mhmui-table__row-select:checked )`); the consumer adds no
 * class for it.
 *
 * @param {Object}   props
 * @param {boolean}  props.checked
 * @param {Function} props.onChange   Called with no argument.
 * @param {string}   props.label      Names the row: "Select Burak Demir".
 * @param {boolean}  [props.disabled]
 */
export default function RowSelect( {
	checked,
	onChange,
	label,
	disabled = false,
} ) {
	const id = useId();
	return (
		<label className="mhmui-table__select-hit" htmlFor={ id }>
			<input
				id={ id }
				type="checkbox"
				className="mhmui-table__row-select"
				checked={ checked }
				disabled={ disabled }
				onChange={ () => onChange() }
			/>
			<span className="mhmui-table__row-select-label">{ label }</span>
		</label>
	);
}
