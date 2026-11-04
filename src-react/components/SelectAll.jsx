import { useRef, useLayoutEffect, useId } from '@wordpress/element';

/**
 * The header box that selects every row on the page (0.19.0).
 *
 * Plain props -- it does not need useTableSelection. The mixed state is a DOM
 * property (there is no HTML attribute for it), written after EVERY render and
 * again in the change handler: a click on a mixed box clears `indeterminate`
 * in the browser, and when the consumer declines the change (a bulk request is
 * running) nothing re-renders, so an effect keyed on `state` alone would leave
 * a plain empty box behind.
 *
 * A click asks for "all" from none or some (the native box does the same from
 * mixed) and for "none" from all. The label text is visually hidden in the
 * wide layout and visible in the stacked one, so the visible text and the
 * accessible name are the same string (WCAG 2.5.3) -- pass a short one.
 *
 * @param {Object}   props
 * @param {string}   props.state      'none' | 'some' | 'all'.
 * @param {Function} props.onChange   Called with true (select all) or false.
 * @param {string}   props.label      The box's name, e.g. "Select all".
 * @param {boolean}  [props.disabled] An empty page, for one.
 */
export default function SelectAll( {
	state,
	onChange,
	label,
	disabled = false,
} ) {
	const box = useRef( null );
	const id = useId();
	const mixed = state === 'some';

	// A layout effect (0.20.1): written before paint, so no frame shows the
	// previous box. Admin screens render client-side only (createRoot).
	useLayoutEffect( () => {
		if ( box.current ) {
			box.current.indeterminate = mixed;
		}
	} );

	return (
		<label className="mhmui-table__select-hit" htmlFor={ id }>
			<input
				ref={ box }
				id={ id }
				type="checkbox"
				className="mhmui-table__select-all"
				checked={ state === 'all' }
				disabled={ disabled }
				onChange={ ( event ) => {
					event.target.indeterminate = mixed;
					onChange( state !== 'all' );
				} }
			/>
			<span className="mhmui-table__select-label">{ label }</span>
		</label>
	);
}
