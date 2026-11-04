import { useRef, useLayoutEffect } from '@wordpress/element';

const LAYOUTS = [ 'scroll', 'stack' ];

const childrenByTag = ( parent, tags ) =>
	Array.from( parent.children ).filter( ( el ) =>
		tags.includes( el.tagName )
	);

const giveRole = ( el, role ) => {
	if ( ! el.hasAttribute( 'role' ) ) {
		el.setAttribute( 'role', role );
	}
};

/**
 * Some browsers drop the table / row / cell roles once CSS changes a table
 * element's display, which the stacked layout does. Write them back
 * explicitly -- by POSITION (a thead th is a column header, a tbody th a row
 * header) because consumers often leave out `scope`; only where no role is
 * set, so a consumer's own role always wins; and only on this table's own
 * rows, never on a table nested in a cell.
 *
 * @param {HTMLTableElement} table
 */
function writeRoles( table ) {
	giveRole( table, 'table' );
	childrenByTag( table, [ 'THEAD', 'TBODY', 'TFOOT' ] ).forEach(
		( group ) => {
			giveRole( group, 'rowgroup' );
			const inHead = group.tagName === 'THEAD';
			childrenByTag( group, [ 'TR' ] ).forEach( ( row ) => {
				giveRole( row, 'row' );
				childrenByTag( row, [ 'TH', 'TD' ] ).forEach( ( cell ) => {
					if ( cell.tagName === 'TD' ) {
						giveRole( cell, 'cell' );
					} else {
						giveRole( cell, inHead ? 'columnheader' : 'rowheader' );
					}
				} );
			} );
		}
	);
}

/**
 * A thin table wrapper (0.19.0): the card, the caption, and what the table
 * does in a narrow container. Rows, cells and the head stay the consumer's.
 *
 * The caption names the table for assistive technology. It is required; an
 * empty one is drawn empty and no gate catches it -- the consumer's own test
 * should assert its text. `captionHidden` hides it visually (the page title
 * already names the table), never removes it.
 *
 * layout 'scroll' (default) scrolls sideways when the container is too narrow
 * -- right for every table. 'stack' turns rows into cards below the kit's
 * container threshold; it needs `data-label` on the cells and writes explicit
 * table roles (see writeRoles). Widths come from a consumer `<colgroup>`.
 *
 * @param {Object}  props
 * @param {string}  props.caption         Names the table.
 * @param {boolean} [props.captionHidden] Visually hidden caption.
 * @param {*}       [props.captionMeta]   A muted aside inside the caption ("· 48").
 * @param {string}  [props.layout]        'scroll' | 'stack'.
 * @param {string}  [props.className]     Added to the table (a screen's own class).
 * @param {*}       [props.footer]        Below the table, inside the card.
 * @param {*}       props.children        colgroup / thead / tbody.
 */
export default function DataTable( {
	caption,
	captionHidden = false,
	captionMeta,
	layout = 'scroll',
	className,
	footer,
	children,
} ) {
	const table = useRef( null );
	const stack =
		( LAYOUTS.includes( layout ) ? layout : 'scroll' ) === 'stack';

	// No deps on purpose: a consumer's new rows re-render this component too,
	// and after the first pass every element already has its role (cheap).
	useLayoutEffect( () => {
		if ( stack && table.current ) {
			writeRoles( table.current );
		}
	} );

	return (
		<div className="mhmui-table-card">
			<div
				className={
					stack
						? 'mhmui-table-wrap mhmui-table-wrap--stack'
						: 'mhmui-table-wrap'
				}
			>
				<table
					ref={ table }
					className={ [
						'mhmui-table',
						stack ? 'mhmui-table--stack' : '',
						className || '',
					]
						.filter( Boolean )
						.join( ' ' ) }
				>
					<caption
						className={
							captionHidden
								? 'mhmui-table__caption mhmui-table__caption--hidden'
								: 'mhmui-table__caption'
						}
					>
						{ caption }
						{ captionMeta ? (
							<>
								{ ' ' }
								<span className="mhmui-table__caption-meta">
									{ captionMeta }
								</span>
							</>
						) : null }
					</caption>
					{ children }
				</table>
			</div>
			{ footer }
		</div>
	);
}
