/**
 * A row of StatCards.
 *
 * @param {Object} props
 * @param {Array}  props.cards     StatCard prop objects; `label` doubles as the key.
 * @param {number} [props.columns] Column count, default 4.
 */
import StatCard from './StatCard';

export default function StatsGrid( { cards, columns = 4 } ) {
	return (
		<div
			className="mhmui-stats-grid"
			style={ { gridTemplateColumns: `repeat(${ columns }, 1fr)` } }
		>
			{ cards.map( ( card ) => (
				<StatCard key={ card.label } { ...card } />
			) ) }
		</div>
	);
}
