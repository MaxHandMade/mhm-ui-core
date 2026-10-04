import { resolveIcon } from '../icons';

/**
 * A titled panel -- the box most admin screens are made of.
 *
 * @param {Object}         props
 * @param {string}         props.title      Translated title.
 * @param {string}         [props.subtitle] Translated subtitle.
 * @param {string}         [props.icon]     Dashicons class suffix, or a registered
 *                                          icon concept (see src-react/icons.js).
 * @param {*}              [props.actions]  Nodes rendered at the right of the header.
 * @param {2|3}            [props.level]    Heading level; 2 (a page section) or 3
 *                                          (default). Anything else is 3.
 * @param {'card'|'plain'} [props.variant]  `plain` drops the header band and
 *                                          pads the card itself. Anything else is card.
 * @param {*}              props.children   Body.
 */
export default function Widget( {
	title,
	subtitle,
	icon,
	actions,
	level,
	variant,
	children,
} ) {
	const Heading = level === 2 ? 'h2' : 'h3';
	const classes = [ 'mhmui-widget' ];
	if ( level === 2 ) {
		classes.push( 'mhmui-widget--level-2' );
	}
	if ( variant === 'plain' ) {
		classes.push( 'mhmui-widget--plain' );
	}
	return (
		<section className={ classes.join( ' ' ) }>
			<header className="mhmui-widget__header">
				<Heading className="mhmui-widget__title">
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
				</Heading>
				{ actions && (
					<div className="mhmui-widget__actions">{ actions }</div>
				) }
			</header>
			<div className="mhmui-widget__body">{ children }</div>
		</section>
	);
}
