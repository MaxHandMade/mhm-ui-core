/**
 * An inline admin notice in WordPress's own notice shape.
 *
 * @param {Object}   props
 * @param {string}   [props.tone]         success | warning | error | info.
 * @param {*}        props.children       Translated message.
 * @param {Function} [props.onDismiss]    When given, a dismiss button is rendered.
 * @param {string}   [props.dismissLabel] Accessible label for the dismiss button.
 */
export default function Notice( {
	tone = 'info',
	children,
	onDismiss,
	dismissLabel,
} ) {
	return (
		<div
			className={ `notice notice-${ tone } mhmui-notice mhmui-notice--${ tone }` }
			role="status"
		>
			<p>{ children }</p>
			{ onDismiss && (
				<button
					type="button"
					className="notice-dismiss"
					onClick={ onDismiss }
				>
					<span className="screen-reader-text">
						{ dismissLabel ?? '' }
					</span>
				</button>
			) }
		</div>
	);
}
