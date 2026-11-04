import { useState, useEffect } from '@wordpress/element';

/**
 * The value, `delay` ms after it last changed (0.20.0).
 *
 * For a search box that feeds a server request: the box keeps its own state
 * so typing stays instant, and the request reads this value. 300 ms is the
 * figure four consumer screens had each written by hand.
 *
 * The first render returns the value itself -- there is nothing to wait for
 * on mount. Unmounting clears a pending timer.
 *
 * @param {*}      value   The value to follow.
 * @param {number} [delay] Milliseconds of quiet before the value is passed on.
 * @return {*} The last value that stood still for `delay` ms.
 */
export function useDebouncedValue( value, delay = 300 ) {
	const [ settled, setSettled ] = useState( value );

	useEffect( () => {
		const timer = setTimeout( () => setSettled( value ), delay );
		return () => clearTimeout( timer );
	}, [ value, delay ] );

	return settled;
}
