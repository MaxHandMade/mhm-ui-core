import { resolveIcon } from '../icons';

/**
 * A titled panel -- the box most admin screens are made of.
 *
 * @param {Object} props
 * @param {string} props.title      Translated title.
 * @param {string} [props.subtitle] Translated subtitle.
 * @param {string} [props.icon]     Dashicons class suffix, or a registered
 *                                  icon concept (see src-react/icons.js).
 * @param {*}      [props.actions]  Nodes rendered at the right of the header.
 * @param {*}      props.children   Body.
 */
export default function Widget( { title, subtitle, icon, actions, children } ) {
	return (
		<section className="mhmui-widget">
			<header className="mhmui-widget__header">
				<h3 className="mhmui-widget__title">
					{ icon && (
						<span
							className={ `dashicons dashicons-${ resolveIcon(
								icon
							) }` }
							aria-hidden="true"
						/>
					) }
					{ title }
					{ subtitle && (
						<span className="mhmui-widget__subtitle">
							{ subtitle }
						</span>
					) }
				</h3>
				{ actions && (
					<div className="mhmui-widget__actions">{ actions }</div>
				) }
			</header>
			<div className="mhmui-widget__body">{ children }</div>
		</section>
	);
}
