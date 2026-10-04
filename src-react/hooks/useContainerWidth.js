import { useState, useRef, useCallback } from '@wordpress/element';

/**
 * Chooses a layout from the width of a container, not the viewport: the
 * admin screen can be narrow beside an expanded WP menu, or wide on a phone
 * in landscape.
 *
 * Returns a CALLBACK ref. A container that renders only after its data has
 * loaded mounts late; a callback ref attaches the observer the moment the
 * node arrives, and disconnects the old node when it changes or unmounts.
 * The first value is the node's clientWidth, the later ones come from
 * ResizeObserver. A width of 0 means "not laid out" (hidden or detached) and
 * keeps the current layout. Without ResizeObserver the layout stays 'wide'
 * (the layout that needs no measuring).
 *
 * Ported unchanged from Rentiva Pro's messages screen (0.17.0).
 *
 * @param {number} [threshold=600] Widths up to and including this are 'narrow'.
 * @return {Array} [ setRef, 'wide' | 'narrow' ]
 */
export function useContainerWidth( threshold = 600 ) {
	const [ layout, setLayout ] = useState( 'wide' );
	const observer = useRef( null );

	const setRef = useCallback(
		( node ) => {
			if ( observer.current ) {
				observer.current.disconnect();
				observer.current = null;
			}
			const RO = node && node.ownerDocument.defaultView.ResizeObserver;
			if ( ! RO ) {
				return;
			}
			const pick = ( width ) =>
				// 0 means "not laid out" (hidden or detached): keep what we have.
				width > 0 &&
				setLayout( width <= threshold ? 'narrow' : 'wide' );
			pick( node.clientWidth );
			observer.current = new RO( ( entries ) => {
				const entry = entries[ entries.length - 1 ];
				if ( entry ) {
					pick( entry.contentRect.width );
				}
			} );
			observer.current.observe( node );
		},
		[ threshold ]
	);

	return [ setRef, layout ];
}

export default useContainerWidth;
