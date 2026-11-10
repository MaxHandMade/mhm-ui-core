/**
 * A row of StatCards.
 *
 * Emits a column CEILING (--mhmui-columns), not a track list: an inline
 * grid-template-columns beats every stylesheet rule and the kit bans
 * !important, so a four-card row could never wrap in a narrow theme column
 * (measured 2026-09-17: 392px). The wrapping formula lives in admin.css and
 * front.css. PHP twin: src/Kit/StatsGrid.php.
 *
 * 0.11.0 components need the 0.11.0 stylesheet -- enqueue through
 * mhmuicore_enqueue_kit() so the loader serves the winning copy, because an
 * older (0.10.x) stylesheet has no grid-template-columns fallback for
 * --mhmui-columns and so leaves the grid with no columns at all.
 *
 * @param {Object} props
 * @param {Array}  props.cards     StatCard prop objects; `label` doubles as the key.
 * @param {number} [props.columns] Most columns on a wide container, default 4.
 */
import StatCard from './StatCard';

// PHP is_numeric() for a string: optional sign, digits with an optional point
// or a leading point, optional exponent, surrounding whitespace allowed. Not
// hex, not '6abc', not ''.
const NUMERIC_STRING = /^\s*[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?\s*$/;

/**
 * Twin of StatsGrid::render_html()'s `is_numeric( $columns ) ? max( 1, (int) $columns )`.
 * Numeric (a finite number, or a string PHP's is_numeric() accepts) ->
 * max( 1, trunc( n ) ); anything else -> `fallback`. So '1e1' is 10, '.5' is 1,
 * '6abc' / '0x10' / true / null fall back -- the same in both languages.
 *
 * @param {*}      columns  Raw prop.
 * @param {number} fallback Ceiling for a non-numeric value (4 here, 2 in DetailList).
 */
export function numericCeiling( columns, fallback ) {
	const numeric =
		typeof columns === 'number' ||
		( typeof columns === 'string' && NUMERIC_STRING.test( columns ) );
	const n = numeric ? Math.trunc( Number( columns ) ) : NaN;
	return Number.isFinite( n ) ? Math.max( 1, n ) : fallback;
}

export function columnCeiling( columns ) {
	return numericCeiling( columns, 4 );
}

/**
 * "4 or 2, never 3" (spec 2026-11-10 §2) -- twin of StatsGrid::pairs(). Only
 * when the cards fill whole rows of a ceiling of 4 or 6: 3 cards under a 4
 * ceiling would draw 2+1, worse than today; a ceiling of 2 has nothing to
 * halve to; at 8+ the CSS cannot keep its promise once half the row stops
 * fitting (8 of 8 -> 3+3+2). The CSS modifier then lets the grid pick N or
 * N/2 columns.
 *
 * @param {number} count   Cards drawn.
 * @param {number} ceiling Column ceiling.
 * @return {boolean} Whether the grid gets the pairs modifier.
 */
export function pairs( count, ceiling ) {
	return (
		( ceiling === 4 || ceiling === 6 ) && count > 0 && count % ceiling === 0
	);
}

export default function StatsGrid( { cards, columns = 4 } ) {
	const ceiling = columnCeiling( columns );
	return (
		<div
			className={
				pairs( cards.length, ceiling )
					? 'mhmui-stats-grid mhmui-stats-grid--pairs'
					: 'mhmui-stats-grid'
			}
			style={ { '--mhmui-columns': ceiling } }
		>
			{ cards.map( ( card ) => (
				<StatCard key={ card.label } { ...card } />
			) ) }
		</div>
	);
}
