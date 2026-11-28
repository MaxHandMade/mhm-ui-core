import { useEffect, useRef, useState } from '@wordpress/element';
import useBrowserLayoutEffect from '../hooks/useBrowserLayoutEffect';

/** @typedef {import('react').ReactNode} ReactNode */

const LAYOUTS = [ 'scroll', 'stack' ];

// Set on the wrap when a stack table's rows do not fit (0.28.0). admin.css
// draws the stacked layout for it exactly as below its 40rem container floor.
const NO_FIT = 'mhmui-table-wrap--no-fit';
// Above this container width a table that does not fit scrolls: cards wider
// than ~1024px spread a label and its value a whole screen apart.
const NO_FIT_MAX_REM = 64;

/**
 * Do this table's rows fit in their wrap? Read with `--no-fit` taken off for
 * the moment -- stacked, the table is as wide as the space and would always
 * "fit" -- and put back before returning, so nothing is painted in between
 * and the DOM is left as React wrote it.
 *
 * @param {HTMLElement}      wrap
 * @param {HTMLTableElement} table
 * @return {boolean|null} true: stack; false: a table; null: not laid out
 *                        (0 wide, hidden or detached), keep what is shown.
 */
function rowsDoNotFit( wrap, table ) {
	const had = wrap.classList.contains( NO_FIT );
	if ( had ) {
		wrap.classList.remove( NO_FIT );
	}
	const space = wrap.clientWidth;
	const need = table.offsetWidth;
	if ( had ) {
		wrap.classList.add( NO_FIT );
	}
	if ( ! space ) {
		return null;
	}
	const view = wrap.ownerDocument.defaultView;
	const rem =
		parseFloat(
			view.getComputedStyle( wrap.ownerDocument.documentElement ).fontSize
		) || 16;
	return space <= NO_FIT_MAX_REM * rem && need > space;
}

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
 * 40rem container threshold, and up to a 64rem container whenever the rows
 * do not fit (0.28.0, measured here: on mount, on every render and on a
 * resize of the wrap). It needs `data-label` on the cells and writes explicit
 * table roles (see writeRoles). Widths come from a consumer `<colgroup>`.
 *
 * @param {Object}    props
 * @param {string}    props.caption         Names the table.
 * @param {boolean}   [props.captionHidden] Visually hidden caption.
 * @param {ReactNode} [props.captionMeta]   A muted aside inside the caption ("· 48").
 * @param {string}    [props.layout]        'scroll' | 'stack'.
 * @param {string}    [props.className]     Added to the table (a screen's own class).
 * @param {ReactNode} [props.footer]        Below the table, inside the card.
 * @param {ReactNode} props.children        colgroup / thead / tbody.
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
	const wrap = useRef( null );
	const [ noFit, setNoFit ] = useState( false );
	const stack =
		( LAYOUTS.includes( layout ) ? layout : 'scroll' ) === 'stack';

	// No deps on purpose: a consumer's new rows re-render this component too,
	// and after the first pass every element already has its role (cheap).
	// The fit is read on every render for the same reason; a change re-renders
	// once, before paint, and the next reading agrees (it never sees the class).
	useBrowserLayoutEffect( () => {
		if ( stack && table.current ) {
			writeRoles( table.current );
			const fit = rowsDoNotFit( wrap.current, table.current );
			if ( fit !== null && fit !== noFit ) {
				setNoFit( fit );
			}
		}
	} );

	// A resize changes the space, not the rows. Only a change of width is
	// acted on: stacking changes the wrap's height, which reports here too.
	useEffect( () => {
		const node = wrap.current;
		const RO = node && node.ownerDocument.defaultView.ResizeObserver;
		if ( ! stack || ! RO ) {
			return undefined;
		}
		let width = node.clientWidth;
		const observer = new RO( () => {
			if ( node.clientWidth === width || ! table.current ) {
				return;
			}
			width = node.clientWidth;
			const fit = rowsDoNotFit( node, table.current );
			if ( fit !== null ) {
				setNoFit( fit );
			}
		} );
		observer.observe( node );
		return () => observer.disconnect();
	}, [ stack ] );

	return (
		<div className="mhmui-table-card">
			<div
				ref={ wrap }
				className={ [
					'mhmui-table-wrap',
					stack ? 'mhmui-table-wrap--stack' : '',
					stack && noFit ? NO_FIT : '',
				]
					.filter( Boolean )
					.join( ' ' ) }
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
