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

export function columnCeiling( columns ) {
	const parsed = Number.parseInt( columns, 10 );
	return Number.isNaN( parsed ) ? 4 : Math.max( 1, parsed );
}

/**
 * "4 or 2, never 3" (spec 2026-11-10 §2) -- twin of StatsGrid::pairs(). Only
 * when the cards fill whole rows of an even ceiling >= 4: 3 cards under a 4
 * ceiling would draw 2+1, worse than today, and a ceiling of 2 has nothing to
 * halve to. The CSS modifier then lets the grid pick N or N/2 columns only.
 *
 * @param {number} count   Cards drawn.
 * @param {number} ceiling Column ceiling.
 * @return {boolean} Whether the grid gets the pairs modifier.
 */
export function pairs( count, ceiling ) {
	return (
		ceiling >= 4 && ceiling % 2 === 0 && count > 0 && count % ceiling === 0
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
