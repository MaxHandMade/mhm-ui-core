import StatusBadge from './StatusBadge';

const LEVELS = [ 1, 2, 3 ];

function isPlainLeftClick( event ) {
	return (
		event.button === 0 &&
		! event.metaKey &&
		! event.ctrlKey &&
		! event.shiftKey &&
		! event.altKey
	);
}

/**
 * The header of a detail view: back link, title, status badge, meta line,
 * actions.
 *
 * The title defaults to an h2: on a WordPress admin screen the page's h1 is
 * printed by PHP and admin notices anchor under it (hr.wp-header-end), so a
 * second h1 would break both. The root is a <div>, not a <header>: wp-admin
 * content sits in no <main>/<article>, where a <header> becomes a second
 * banner landmark.
 *
 * back.onClick, like Tabs' onSelect, intercepts only a plain left click.
 * The badge's tone meaning lives in StatusBadge's own manifest row (its text
 * carries it), so this component declares no tone semantics.
 *
 * @param {Object} props
 * @param {string} props.title
 * @param {Object} [props.back]    { label, href, onClick? }.
 * @param {Object} [props.badge]   { text, tone? } -> StatusBadge.
 * @param {string} [props.meta]
 * @param {*}      [props.actions]
 * @param {number} [props.level]   1 | 2 | 3, default 2.
 */
export default function PageHeader( {
	title,
	back,
	badge,
	meta,
	actions,
	level = 2,
} ) {
	const Heading = `h${ LEVELS.includes( level ) ? level : 2 }`;
	const hasBack = back && typeof back.href === 'string' && back.href !== '';
	const handleBack =
		hasBack && back.onClick
			? ( event ) => {
					if ( isPlainLeftClick( event ) ) {
						event.preventDefault();
						back.onClick( event );
					}
			  }
			: undefined;

	return (
		<div className="mhmui-page-header">
			{ hasBack && (
				<a
					className="mhmui-page-header__back"
					href={ back.href }
					onClick={ handleBack }
				>
					<span aria-hidden="true">← </span>
					{ back.label }
				</a>
			) }
			<div className="mhmui-page-header__title-row">
				<Heading className="mhmui-page-header__title">
					{ title }
				</Heading>
				{ badge && badge.text && (
					<StatusBadge tone={ badge.tone }>
						{ badge.text }
					</StatusBadge>
				) }
				{ actions && (
					<div className="mhmui-page-header__actions">
						{ actions }
					</div>
				) }
			</div>
			{ typeof meta === 'string' && meta !== '' && (
				<p className="mhmui-page-header__meta">{ meta }</p>
			) }
		</div>
	);
}
