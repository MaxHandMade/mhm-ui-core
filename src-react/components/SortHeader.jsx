const ICON_PATH = {
	none: 'M5 6.5l3-3 3 3M5 9.5l3 3 3-3',
	asc: 'M8 13V3M4 7l4-4 4 4',
	desc: 'M8 3v10M4 9l4 4 4-4',
};

/**
 * The next sort after a click on `key` (0.19.0): the same column turns its
 * direction, another column starts at `firstDirection`. Pure; the consumer
 * decides whether to use it (the data and the order belong to the consumer).
 *
 * @param {?{key: string, direction: string}} sort           Current sort, or null.
 * @param {string}                            key            The clicked column.
 * @param {string}                            firstDirection 'asc' (default) or 'desc'.
 * @return {{key: string, direction: string}} The sort to apply.
 */
export function nextSort( sort, key, firstDirection = 'asc' ) {
	if ( sort && sort.key === key ) {
		return {
			key,
			direction: sort.direction === 'asc' ? 'desc' : 'asc',
		};
	}
	return { key, direction: firstDirection };
}

/**
 * A sortable column header (0.19.0): the whole cell is a native button, so
 * Tab reaches it and Enter / Space sort.
 *
 * aria-sort sits on the SORTED column only -- the other headers carry no
 * aria-sort at all, not "none" (WAI-ARIA: apply it to one header at a time).
 * The arrow is decoration; the state lives in aria-sort.
 *
 * @param {Object}   props
 * @param {string}   props.label       Visible text and the button's name.
 * @param {string}   props.sortKey     This column's key.
 * @param {?Object}  props.sort        { key, direction: 'asc'|'desc' } or null.
 * @param {Function} props.onSort      Called with sortKey. The consumer picks
 *                                     the direction (nextSort helps) and
 *                                     resets its own page.
 * @param {string}   [props.align]     'start' (default) | 'end' (numbers).
 * @param {string}   [props.className] Added to the th, so a screen's own
 *                                     header class reaches every header.
 */
export default function SortHeader( {
	label,
	sortKey,
	sort,
	onSort,
	align = 'start',
	className,
} ) {
	const active = !! sort && sort.key === sortKey;
	let ariaSort;
	if ( active ) {
		ariaSort = sort.direction === 'desc' ? 'descending' : 'ascending';
	}
	let state = 'none';
	if ( active ) {
		state = sort.direction === 'desc' ? 'desc' : 'asc';
	}
	const classes = [ 'mhmui-table__th', 'mhmui-table__th--sortable' ];
	if ( align === 'end' ) {
		classes.push( 'mhmui-table__th--end' );
	}
	if ( className ) {
		classes.push( className );
	}

	return (
		<th
			scope="col"
			className={ classes.join( ' ' ) }
			aria-sort={ ariaSort }
		>
			<button
				type="button"
				className="mhmui-table__sort"
				onClick={ () => onSort( sortKey ) }
			>
				{ label }
				<svg
					aria-hidden="true"
					focusable="false"
					className={ `mhmui-table__sort-icon mhmui-table__sort-icon--${ state }` }
					width="16"
					height="16"
					viewBox="0 0 16 16"
					fill="none"
					stroke="currentColor"
					strokeWidth="1.5"
					strokeLinecap="round"
					strokeLinejoin="round"
				>
					<path d={ ICON_PATH[ state ] } />
				</svg>
			</button>
		</th>
	);
}
