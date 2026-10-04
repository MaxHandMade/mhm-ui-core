import { useState, useCallback } from '@wordpress/element';

/**
 * Reads a remembered open state: '1' is open, '0' is closed, anything else
 * -- a missing key, another value, or storage that throws (a private window,
 * blocked site data) -- is `fallback`. Never logs.
 *
 * @param {?string} key      localStorage key, or nothing for no storage.
 * @param {boolean} fallback The value when nothing usable is stored.
 * @return {boolean} The open state.
 */
function readOpen( key, fallback ) {
	if ( ! key ) {
		return fallback;
	}
	try {
		const value = window.localStorage.getItem( key );
		if ( value === '1' ) {
			return true;
		}
		if ( value === '0' ) {
			return false;
		}
	} catch ( e ) {
		// Storage is unavailable: the default is the answer.
	}
	return fallback;
}

/**
 * An open/closed state that survives a reload when it is given a key.
 *
 * Without `storageKey` it is plain component state. With one, the first
 * value is read from localStorage once, and read again whenever the key
 * changes (a consumer that keys by layout gets the new layout's state on the
 * same render); every `setOpen` writes '1' or '0' under the current key.
 * Storage that throws is treated as empty -- the default wins, nothing is
 * logged.
 *
 * @param {?string} storageKey  localStorage key; omit for no storage.
 * @param {boolean} defaultOpen The state when nothing is stored.
 * @return {Array} [ open, setOpen( next ) ]
 */
export function usePersistentOpen( storageKey, defaultOpen ) {
	const [ state, setState ] = useState( () => ( {
		key: storageKey,
		open: readOpen( storageKey, defaultOpen ),
	} ) );

	let current = state;
	if ( state.key !== storageKey ) {
		// The key changed: re-read during render (React's "adjust state when
		// a prop changes" pattern), so no frame shows the old key's state.
		current = {
			key: storageKey,
			open: readOpen( storageKey, defaultOpen ),
		};
		setState( current );
	}

	const setOpen = useCallback(
		( next ) => {
			const value = !! next;
			setState( { key: storageKey, open: value } );
			if ( ! storageKey ) {
				return;
			}
			try {
				window.localStorage.setItem( storageKey, value ? '1' : '0' );
			} catch ( e ) {
				// Storage is unavailable: the state still changes for this view.
			}
		},
		[ storageKey ]
	);

	return [ current.open, setOpen ];
}

export default usePersistentOpen;
