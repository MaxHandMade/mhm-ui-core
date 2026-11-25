import { useEffect, useLayoutEffect } from '@wordpress/element';

/**
 * useLayoutEffect in a browser, useEffect where there is no DOM.
 *
 * The kit's layout effects (DataTable cell roles, SelectAll's indeterminate
 * box, Widget's focus return) must run before paint in the browser. Rendered
 * to a string on a server (renderToString, a test, a static export) a layout
 * effect does nothing, and React 18 logs a warning for each one; useEffect
 * does nothing there either, silently. Decided once, at import: `window` does
 * not appear or disappear while a page runs.
 *
 * Internal to the kit, not exported from src-react/index.js.
 */
const useBrowserLayoutEffect =
	typeof window !== 'undefined' && typeof window.document !== 'undefined'
		? useLayoutEffect
		: useEffect;

export default useBrowserLayoutEffect;
