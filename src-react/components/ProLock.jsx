/**
 * Shows its children when a capability is granted; otherwise a lock message.
 *
 * This is the React face of the tier seam. It is a DISPLAY choice, not a
 * gate: the free core never withholds something it could do (see
 * MHMUiCore\Seam\Capabilities). What this hides is the Pro-only screen or
 * control that does not exist without the add-on, and what it shows in its
 * place is whatever the consumer passes -- the package has no text.
 *
 * @param {Object}  props
 * @param {boolean} props.unlocked Whether the Pro capability is present.
 * @param {*}       props.children Pro-only content.
 * @param {*}       props.fallback What to show when locked (translated by the consumer).
 */
export default function ProLock( { unlocked, children, fallback } ) {
	if ( unlocked ) {
		return children;
	}
	return <div className="mhmui-pro-lock">{ fallback ?? null }</div>;
}
