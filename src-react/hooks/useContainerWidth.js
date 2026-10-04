import { useState, useRef, useCallback } from '@wordpress/element';

/**
 * Chooses a layout from the width of a container, not the viewport: the
 * admin screen can be narrow beside an expanded WP menu, or wide on a phone
 * in landscape.
 *
 * Returns a CALLBACK ref. A container that renders only after its data has
 * loaded mounts late; a callback ref attaches the observer the moment the
 * node arrives, and disconnects the old node when it changes or unmounts.
 * The first value is the node's content width (clientWidth minus its
 * horizontal padding), the later ones come from ResizeObserver's content box. A width of 0 means "not laid out" (hidden or detached) and
 * keeps the current layout. Without ResizeObserver the layout stays 'wide'
 * (the layout that needs no measuring).
 *
 * The third value, `measured`, is false until the first non-zero width has
 * been seen (or until it is known that ResizeObserver is missing; the layout
 * is then 'wide' for good). A consumer whose layout picks a remembered state
 * can wait for it instead of rendering the 'wide' guess first. Callers that
 * read only the first two values are unaffected.
 *
 * Ported from Rentiva Pro's messages screen (0.17.0); the first reading
 * now measures the content box too.
 *
 * @param {number} [threshold=600] Widths up to and including this are 'narrow'.
 * @return {Array} [ setRef, 'wide' | 'narrow', measured: boolean ]
 */
export function useContainerWidth( threshold = 600 ) {
	const [ layout, setLayout ] = useState( 'wide' );
	const [ measured, setMeasured ] = useState( false );
	const observer = useRef( null );

	const setRef = useCallback(
		( node ) => {
			if ( observer.current ) {
				observer.current.disconnect();
				observer.current = null;
			}
			if ( ! node ) {
				return;
			}
			const RO = node.ownerDocument.defaultView.ResizeObserver;
			if ( ! RO ) {
				// Nothing will ever measure: 'wide' is the final answer.
				setMeasured( true );
				return;
			}
			const pick = ( width ) => {
				// 0 means "not laid out" (hidden or detached): keep what we have.
				if ( width > 0 ) {
					setLayout( width <= threshold ? 'narrow' : 'wide' );
					setMeasured( true );
				}
			};
			// clientWidth is the padding box; ResizeObserver reports the
			// content box. Measure the content box both times, or a padded
			// container can flip layouts between the two readings.
			const style =
				node.ownerDocument.defaultView.getComputedStyle( node );
			const padding =
				( parseFloat( style.paddingLeft ) || 0 ) +
				( parseFloat( style.paddingRight ) || 0 );
			pick( node.clientWidth > 0 ? node.clientWidth - padding : 0 );
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

	return [ setRef, layout, measured ];
}

export default useContainerWidth;
