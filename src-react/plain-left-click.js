/**
 * True for a plain left click: no modifier, primary button. Anything else
 * (ctrl/meta/shift/alt, middle button) is the browser's -- a new tab, a new
 * window, a download -- and a router must let it through.
 *
 * One copy for Tabs' onSelect and PageHeader's back.onClick (0.18.0; until
 * then each carried its own, audit M-7). Internal: not re-exported from
 * index.js. plain-left-click.test.jsx holds both surfaces to one behaviour.
 *
 * @param {MouseEvent} event
 * @return {boolean} Whether the click is the kit's to intercept.
 */
export function isPlainLeftClick( event ) {
	return (
		event.button === 0 &&
		! event.metaKey &&
		! event.ctrlKey &&
		! event.shiftKey &&
		! event.altKey
	);
}
