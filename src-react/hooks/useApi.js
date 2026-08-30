import { useState, useEffect, useRef } from '@wordpress/element';

/**
 * Fetch hook with loading state, error handling, and a refetch trigger.
 *
 * @param {Function} fetchFn     - Stable function that returns a Promise (use module-scope functions from the host's API map).
 * @param {*}        initialData - Pre-populated data from wp_localize_script. When supplied, the
 *                               first fetch runs WITHOUT raising `loading`: there is already
 *                               something on screen, so a spinner would be the very thing this
 *                               argument exists to prevent. Refetches and dependency changes
 *                               raise it as normal.
 * @param {Array}    deps        - Additional effect dependencies (e.g. filter values that should re-trigger fetch).
 * @return {{ data, loading, error, refetch }} Fetch state plus a refetch trigger.
 */
export function useApi( fetchFn, initialData = null, deps = [] ) {
	const [ data, setData ] = useState( initialData );
	const [ loading, setLoading ] = useState( initialData === null );
	const [ error, setError ] = useState( null );
	const [ trigger, setTrigger ] = useState( 0 );

	/*
	 * True only until the first effect has run, and only when the caller primed us
	 * with data.
	 *
	 * useState() above already starts `loading` at false in that case, but the mount
	 * effect used to raise it unconditionally -- so the screen painted its content,
	 * then flashed a spinner one frame later, then painted the same content again.
	 * The doc block promised the opposite for four Lite screens and five Pro ones.
	 *
	 * A ref rather than state on purpose: this is bookkeeping about the render
	 * sequence, not something the UI reads, and writing it must not schedule a
	 * render of its own.
	 */
	const primed = useRef( initialData !== null );

	useEffect( () => {
		if ( primed.current ) {
			// Fetch anyway -- pre-populated data can be stale. Just do it quietly.
			primed.current = false;
		} else {
			setLoading( true );
		}

		fetchFn()
			.then( setData )
			.catch( setError )
			.finally( () => setLoading( false ) );
	}, [ ...deps, trigger ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const refetch = () => setTrigger( ( t ) => t + 1 );

	return { data, loading, error, refetch };
}
