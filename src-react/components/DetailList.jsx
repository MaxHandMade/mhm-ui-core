import { TONES } from './StatCard';
import { columnCeiling } from './StatsGrid';

const isEmpty = ( value ) =>
	value === undefined || value === null || value === '';

/**
 * Label/value pairs: a real <dl>, each pair in a <div>.
 *
 * Empty means '' / null / undefined -- 0 and '0' are values (the same
 * presence rule as StatCard). A tone never colours the value text: the
 * warning role's strong colour is 3.5:1 on white, below AA for text. It adds
 * an aria-hidden dot instead (graphic, >= 3:1); the meaning must be in the
 * text itself. That is also why this member declares no tone semantics.
 *
 * `columns` is a ceiling, not a track list: the same --mhmui-columns /
 * --mhmui-card-min formula as StatsGrid, so a narrow container folds to one
 * column on its own. `layout="inline"` is one column, label left, value right.
 *
 * @param {Object} props
 * @param {Array}  props.items     { label, value, tone? }.
 * @param {string} props.emptyText Shown for an empty value (consumer-translated).
 * @param {number} [props.columns] Column ceiling, default 2 (stacked only).
 * @param {string} [props.layout]  "stacked" (default) | "inline".
 */
export default function DetailList( {
	items = [],
	emptyText,
	columns = 2,
	layout = 'stacked',
} ) {
	const inline = layout === 'inline';

	return (
		<dl
			className={
				inline
					? 'mhmui-detail-list mhmui-detail-list--inline'
					: 'mhmui-detail-list'
			}
			style={
				inline
					? undefined
					: { '--mhmui-columns': columnCeiling( columns ) }
			}
		>
			{ items.map( ( item, index ) => {
				const empty = isEmpty( item.value );
				const tone =
					! empty && TONES.includes( item.tone ) ? item.tone : null;
				return (
					<div
						className="mhmui-detail-list__item"
						key={ `${ index }-${ item.label }` }
					>
						<dt className="mhmui-detail-list__label">
							{ item.label }
						</dt>
						<dd
							className={
								empty
									? 'mhmui-detail-list__value mhmui-detail-list__value--empty'
									: 'mhmui-detail-list__value'
							}
						>
							{ tone && (
								<span
									className={ `mhmui-detail-list__mark mhmui-detail-list__mark--${ tone }` }
									aria-hidden="true"
								/>
							) }
							{ empty ? emptyText : item.value }
						</dd>
					</div>
				);
			} ) }
		</dl>
	);
}
