const VARIANTS = [ 'primary', 'secondary', 'danger', 'plain', 'neutral' ];
const SIZES = [ 'md', 'sm' ];

/**
 * Only id, aria-* and data-* reach the element -- no style, no className.
 * @param {Object} rest The props left after the named ones.
 * @return {Object} The allowed subset.
 */
function passThrough( rest ) {
	const out = {};
	for ( const key of Object.keys( rest ) ) {
		if ( key === 'id' || /^(aria|data)-/.test( key ) ) {
			out[ key ] = rest[ key ];
		}
	}
	return out;
}

/**
 * A button or a link drawn as one. Carries no text of its own: the label is
 * `children`, an optional decorative `icon` sits before it.
 *
 * `disabled` is `aria-disabled`, not the native attribute: the element stays
 * in the tab order and keeps focus, the click is swallowed, and a link loses
 * its `href` (it is then a disabled link, not a navigation).
 *
 * @param {Object}   props
 * @param {*}        props.children
 * @param {string}   [props.variant]  primary | secondary | danger | plain | neutral;
 *                                    anything else is secondary.
 * @param {string}   [props.size]     md | sm; anything else is md.
 * @param {string}   [props.href]     Renders an <a> instead of a <button>.
 * @param {*}        [props.icon]     Decorative node drawn before the label.
 * @param {boolean}  [props.disabled]
 * @param {Function} [props.onClick]
 * @param {string}   [props.type]     button type; ignored for a link.
 * @param {Object}   [props.rest]     Only id, aria-* and data-* are used.
 */
export default function Button( {
	children,
	variant = 'secondary',
	size = 'md',
	href,
	icon,
	disabled = false,
	onClick,
	type = 'button',
	...rest
} ) {
	const v = VARIANTS.includes( variant ) ? variant : 'secondary';
	const s = SIZES.includes( size ) ? size : 'md';
	const common = {
		className: `mhmui-button mhmui-button--${ v } mhmui-button--${ s }`,
		'aria-disabled': disabled ? 'true' : undefined,
		onClick: ( event ) => {
			if ( disabled ) {
				event.preventDefault();
				return;
			}
			if ( onClick ) {
				onClick( event );
			}
		},
		...passThrough( rest ),
	};
	const content = (
		<>
			{ icon ? (
				<span className="mhmui-button__icon" aria-hidden="true">
					{ icon }
				</span>
			) : null }
			{ children }
		</>
	);

	if ( href !== undefined && href !== null && href !== '' ) {
		return (
			<a
				href={ disabled ? undefined : href }
				role={ disabled ? 'link' : undefined }
				tabIndex={ disabled ? 0 : undefined }
				{ ...common }
			>
				{ content }
			</a>
		);
	}
	return (
		<button type={ type } { ...common }>
			{ content }
		</button>
	);
}
