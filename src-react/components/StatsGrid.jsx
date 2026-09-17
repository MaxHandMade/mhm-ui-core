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

function ceiling( columns ) {
	const parsed = Number.parseInt( columns, 10 );
	return Number.isNaN( parsed ) ? 4 : Math.max( 1, parsed );
}

export default function StatsGrid( { cards, columns = 4 } ) {
	return (
		<div
			className="mhmui-stats-grid"
			style={ { '--mhmui-columns': ceiling( columns ) } }
		>
			{ cards.map( ( card ) => (
				<StatCard key={ card.label } { ...card } />
			) ) }
		</div>
	);
}
