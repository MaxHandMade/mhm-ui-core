/**
 * Page-section navigation as real links.
 *
 * NOT the ARIA tabs pattern: those are panels switched in place with arrow
 * keys (APG Tabs); these are sections of an admin screen, each a URL -- WP
 * core's own nav-tabs are links too. So: <nav aria-label>, real hrefs (deep
 * links, open-in-new-tab and no-JS all keep working), aria-current="page"
 * on the current one.
 *
 * onSelect, when given, intercepts ONLY a plain left click -- a modified or
 * middle click keeps the browser's own behaviour (new tab/window).
 *
 * An item whose href is empty is skipped, before any escaping, so the PHP
 * twin (src/Kit/Tabs.php) and this file drop the same items. This file does
 * NOT sanitise href: a hostile value (javascript:) is the consumer's
 * responsibility here; the PHP twin additionally drops what esc_url() empties.
 *
 * With badgeLabel, the digit is aria-hidden and the visually hidden span
 * carries the whole meaning ("2 pending"), so the link's accessible name
 * reads it once, not "2 2 pending". The markup is pinned byte for byte in
 * Tabs.test.jsx and tests/Kit/TabsTest.php.
 *
 * @param {Object}   props
 * @param {string}   props.label      Accessible name of the <nav>. Known limit:
 *                                    an empty one yields an unnamed nav and no gate
 *                                    catches it.
 * @param {string}   [props.current]  Id of the current item.
 * @param {Array}    props.items      { id, label, href, badge?, badgeLabel?, badgeTone? }.
 * @param {string}   [props.variant]  'pill' (default) or 'underline' -- a
 *                                    transparent row with a 2px underline on the
 *                                    current tab. Unknown falls back to pill.
 * @param {boolean}  [props.showZero] Draw a chip for a count of 0 (when the item
 *                                    carries a numeric badge). Negative never draws.
 *
 *                                    badgeTone (success|warning|danger|info|neutral) colours that item's chip;
 *                                    unknown adds no class. A CSS-only .mhmui-tabs-bar wraps tabs plus a
 *                                    right-hand link under one full-width rule.
 * @param {Function} [props.onSelect] ( id, event ) on a plain left click.
 */

function isPlainLeftClick( event ) {
	return (
		event.button === 0 &&
		! event.metaKey &&
		! event.ctrlKey &&
		! event.shiftKey &&
		! event.altKey
	);
}

const BADGE_TONES = [ 'success', 'warning', 'danger', 'info', 'neutral' ];

// A number, or a numeric string -- mirrors the PHP twin's is_numeric() + (int).
// null when there is no count at all (so showZero never invents a "0").
function badgeCount( badge ) {
	if ( typeof badge === 'number' ) {
		return Number.isFinite( badge ) ? Math.trunc( badge ) : null;
	}
	if ( typeof badge === 'string' && badge.trim() !== '' ) {
		const n = Number( badge );
		return Number.isFinite( n ) ? Math.trunc( n ) : null;
	}
	return null;
}

export default function Tabs( {
	label,
	current,
	items = [],
	variant,
	showZero = false,
	onSelect,
} ) {
	const hasCurrent = typeof current === 'string' && current !== '';

	return (
		<nav
			className={
				variant === 'underline'
					? 'mhmui-tabs mhmui-tabs--underline'
					: 'mhmui-tabs'
			}
			aria-label={ label ? label : undefined }
		>
			{ items
				.filter(
					( item ) =>
						item &&
						typeof item.href === 'string' &&
						item.href !== ''
				)
				.map( ( item, index ) => {
					const isCurrent = hasCurrent && item.id === current;
					const count = badgeCount( item.badge );
					const showChip =
						count !== null &&
						( count > 0 || ( showZero && count === 0 ) );
					const badgeClass = BADGE_TONES.includes( item.badgeTone )
						? `mhmui-tabs__badge mhmui-tabs__badge--${ item.badgeTone }`
						: 'mhmui-tabs__badge';
					const hasBadgeLabel =
						typeof item.badgeLabel === 'string' &&
						item.badgeLabel !== '';
					const handleClick = onSelect
						? ( event ) => {
								if ( isPlainLeftClick( event ) ) {
									event.preventDefault();
									onSelect( item.id, event );
								}
						  }
						: undefined;

					return (
						<a
							key={
								item.id
									? `id:${ item.id }`
									: `at:${ index }:${ item.href }`
							}
							href={ item.href }
							className={
								isCurrent
									? 'mhmui-tabs__tab mhmui-tabs__tab--current'
									: 'mhmui-tabs__tab'
							}
							aria-current={ isCurrent ? 'page' : undefined }
							onClick={ handleClick }
						>
							{ item.label }
							{ showChip && (
								<span className={ badgeClass }>
									{ hasBadgeLabel ? (
										<>
											<span aria-hidden="true">
												{ count }
											</span>
											<span className="mhmui-tabs__badge-sr">
												{ item.badgeLabel }
											</span>
										</>
									) : (
										count
									) }
								</span>
							) }
						</a>
					);
				} ) }
		</nav>
	);
}
