import { useState, useRef, useCallback } from '@wordpress/element';

/**
 * @typedef {Object} TableSelection
 * @property {Array<string|number>}           selected   The selected ids on this page.
 * @property {string}                         state      'none', 'some' or 'all'.
 * @property {(id: string|number) => boolean} isSelected Whether one id is selected.
 * @property {(id: string|number) => void}    toggle     Flips one id.
 * @property {(on: boolean) => void}          setAll     Selects or clears every id on the page.
 * @property {() => void}                     clear      Clears the selection.
 */

/**
 * Page-scoped row selection for a table (0.19.0). Optional: SelectAll and
 * RowSelect take plain props and do not need it.
 *
 * Rules (spec v2.1 §3.3):
 *   1. `ids` are compared by CONTENT, so the fresh `rows.map( r => r.id )`
 *      array a consumer passes on every render prunes nothing.
 *   2. When the content changes, the stored selection is cut down to the new
 *      ids DURING RENDER (React's "store information from previous renders"
 *      pattern) -- no effect, no loop. An id that left does not come back
 *      when it returns: a bulk action can never reach a row its operator did
 *      not see selected. The cost is one restarted render per change.
 *   3. `selected` comes back in `ids` order.
 *   4. `toggle`, `setAll` and `clear` keep their identity for the life of the
 *      table (safe in effect deps). `isSelected` is a fresh closure per
 *      render and promises no identity -- call it while rendering.
 *   5. Equality is `===`: keep the id type consistent (a REST payload that
 *      sends 7 one time and "7" the next silently selects nothing).
 *   6. Locking while a bulk request runs is the consumer's job.
 *
 * @param {Array<string|number>} ids The ids of the rows on this page.
 * @return {TableSelection} Selection.
 */
export function useTableSelection( ids ) {
	const key = ids.join( '\u0000' );
	const [ selection, setSelection ] = useState( () => ( {
		key,
		stored: [],
	} ) );
	const idsRef = useRef( ids );
	idsRef.current = ids;

	let current = selection;
	if ( selection.key !== key ) {
		current = {
			key,
			stored: selection.stored.filter( ( id ) => ids.includes( id ) ),
		};
		setSelection( current );
	}

	const toggle = useCallback( ( id ) => {
		if ( ! idsRef.current.includes( id ) ) {
			return;
		}
		setSelection( ( prev ) => ( {
			key: prev.key,
			stored: prev.stored.includes( id )
				? prev.stored.filter( ( x ) => x !== id )
				: prev.stored.concat( id ),
		} ) );
	}, [] );

	const setAll = useCallback( ( on ) => {
		setSelection( ( prev ) => ( {
			key: prev.key,
			stored: on ? idsRef.current.slice() : [],
		} ) );
	}, [] );

	const clear = useCallback( () => {
		setSelection( ( prev ) =>
			prev.stored.length === 0 ? prev : { key: prev.key, stored: [] }
		);
	}, [] );

	const isSelected = ( id ) => current.stored.includes( id );
	const selected = ids.filter( isSelected );
	let state = 'some';
	if ( selected.length === 0 ) {
		state = 'none';
	} else if ( selected.length === ids.length ) {
		state = 'all';
	}

	return { selected, state, isSelected, toggle, setAll, clear };
}
