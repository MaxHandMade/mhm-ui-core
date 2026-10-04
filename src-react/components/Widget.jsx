import { useId, useLayoutEffect, useRef } from '@wordpress/element';
import { resolveIcon } from '../icons';
import { usePersistentOpen } from '../hooks/usePersistentOpen';

/**
 * A titled panel -- the box most admin screens are made of.
 *
 * Collapsible (0.17.0, opt-in): the heading holds a disclosure button
 * (`aria-expanded`, `aria-controls` = the body's id) whose name is the title
 * and subtitle; a closed card hides its body with `hidden` (it stays mounted,
 * so typed input survives) and drops its `actions`. When a card closes with
 * focus inside its body or its actions, focus moves to the toggle. A call without
 * `collapsible` renders the 0.16.0 markup unchanged.
 *
 * @param {Object}         props
 * @param {string}         props.title         Translated title.
 * @param {string}         [props.subtitle]    Translated subtitle.
 * @param {string}         [props.icon]        Dashicons class suffix, or a registered
 *                                             icon concept (see src-react/icons.js).
 * @param {*}              [props.actions]     Nodes rendered at the right of the header
 *                                             (a collapsible card shows them only while open).
 * @param {2|3}            [props.level]       Heading level; 2 (a page section) or 3
 *                                             (default). Anything else is 3.
 * @param {'card'|'plain'} [props.variant]     `plain` drops the header band and
 *                                             pads the card itself. Anything else is card.
 * @param {boolean}        [props.collapsible] Opt-in disclosure mode. Default false.
 * @param {boolean}        [props.defaultOpen] Uncontrolled start state when nothing is
 *                                             stored. Default true.
 * @param {string}         [props.storageKey]  Uncontrolled only: localStorage key that
 *                                             remembers the state ('1' open, '0' closed).
 * @param {boolean}        [props.open]        Controlled state. When given, `defaultOpen`
 *                                             and `storageKey` are ignored and a click
 *                                             only calls `onToggle( ! open )`.
 * @param {Function}       [props.onToggle]    Called with the next state on every click,
 *                                             in both modes.
 * @param {*}              props.children      Body.
 */
const HIDDEN_STYLE = { display: 'none' };

export default function Widget( {
	title,
	subtitle,
	icon,
	actions,
	level,
	variant,
	collapsible = false,
	defaultOpen = true,
	storageKey,
	open,
	onToggle,
	children,
} ) {
	const bodyId = useId();
	const toggleRef = useRef( null );
	const bodyRef = useRef( null );
	const controlled = open !== undefined;
	const [ stored, setStored ] = usePersistentOpen(
		collapsible && ! controlled ? storageKey : undefined,
		defaultOpen
	);
	let isOpen = stored;
	if ( ! collapsible ) {
		isOpen = true;
	} else if ( controlled ) {
		isOpen = !! open;
	}

	// A closing card must not strand focus inside a hidden body or on an
	// action that unmounts with it: hand it to the toggle. Runs before paint.
	// An action is already gone by then (and browsers do not reliably fire
	// blur on removal), so the last focus inside the body or the actions is
	// tracked from focus events instead of read from the active element.
	// Focus the user already gave to the page (a click on empty space) is
	// forgotten, so a later close does not pull it back or scroll the page.
	const actionsRef = useRef( null );
	const lastInside = useRef( null );
	const togglePressed = useRef( false );
	const wasOpen = useRef( isOpen );
	useLayoutEffect( () => {
		const closed = wasOpen.current && ! isOpen;
		wasOpen.current = isOpen;
		const body = bodyRef.current;
		// React clears a removed inline style to `style=""`; an open body
		// carries no style attribute at all.
		if ( isOpen && body && body.getAttribute( 'style' ) === '' ) {
			body.removeAttribute( 'style' );
		}
		const doc = body && body.ownerDocument;
		if ( ! closed || ! toggleRef.current || ! doc ) {
			return;
		}
		const last = lastInside.current;
		const active = doc.activeElement;
		// Stranded: focus sits in the hidden body, or it was last in the body
		// or on a now-removed action and has not gone anywhere else since.
		const stranded =
			body.contains( active ) ||
			( last &&
				( ! active || active === doc.body || ! active.isConnected ) &&
				( ! last.isConnected || body.contains( last ) ) );
		lastInside.current = null;
		if ( stranded ) {
			toggleRef.current.focus();
		}
	}, [ isOpen ] );

	const Heading = level === 2 ? 'h2' : 'h3';
	const classes = [ 'mhmui-widget' ];
	if ( level === 2 ) {
		classes.push( 'mhmui-widget--level-2' );
	}
	if ( variant === 'plain' ) {
		classes.push( 'mhmui-widget--plain' );
	}
	if ( collapsible ) {
		classes.push( 'mhmui-widget--collapsible' );
		if ( ! isOpen ) {
			classes.push( 'mhmui-widget--collapsed' );
		}
	}

	const iconNode = icon && (
		<span
			className={ `dashicons dashicons-${ resolveIcon( icon ) }` }
			aria-hidden="true"
		/>
	);
	const subtitleNode = subtitle && (
		<span className="mhmui-widget__subtitle">{ subtitle }</span>
	);

	if ( ! collapsible ) {
		return (
			<section className={ classes.join( ' ' ) }>
				<header className="mhmui-widget__header">
					<Heading className="mhmui-widget__title">
						{ iconNode }
						{ title }
						{ subtitleNode }
					</Heading>
					{ actions && (
						<div className="mhmui-widget__actions">{ actions }</div>
					) }
				</header>
				<div className="mhmui-widget__body">{ children }</div>
			</section>
		);
	}

	const toggle = () => {
		togglePressed.current = false;
		const next = ! isOpen;
		if ( ! controlled ) {
			setStored( next );
		}
		if ( onToggle ) {
			onToggle( next );
		}
	};

	const onFocus = ( event ) => {
		const target = event.target;
		const inside =
			( bodyRef.current && bodyRef.current.contains( target ) ) ||
			( actionsRef.current && actionsRef.current.contains( target ) );
		lastInside.current = inside ? target : null;
		togglePressed.current = false;
	};
	const onBlur = ( event ) => {
		const next = event.relatedTarget;
		const pressed = togglePressed.current;
		togglePressed.current = false;
		if ( next ) {
			// Focus moved to a known place outside the card: forget it.
			if ( ! event.currentTarget.contains( next ) ) {
				lastInside.current = null;
			}
		} else if ( ! pressed ) {
			// Focus went nowhere (a click on empty page space): the page
			// holds it now, and a later close must not pull it back. A press
			// on the toggle in a browser that does not focus a clicked button
			// lands here too, and its click closes the card: keep it for that.
			lastInside.current = null;
		}
	};

	return (
		<section
			className={ classes.join( ' ' ) }
			onFocus={ onFocus }
			onBlur={ onBlur }
		>
			<header className="mhmui-widget__header">
				<Heading className="mhmui-widget__title">
					<button
						ref={ toggleRef }
						type="button"
						className="mhmui-widget__toggle"
						aria-expanded={ isOpen ? 'true' : 'false' }
						aria-controls={ bodyId }
						onClick={ toggle }
						onMouseDown={ () => {
							togglePressed.current = true;
						} }
					>
						{ iconNode }
						{ title }
						{ /* A space, so the accessible name reads "Title Subtitle". */ }
						{ subtitleNode && ' ' }
						{ subtitleNode }
						<span
							className="mhmui-widget__chevron"
							aria-hidden="true"
						>
							<svg
								width="16"
								height="16"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
								aria-hidden="true"
								focusable="false"
							>
								<path d="M6 9l6 6 6-6" />
							</svg>
						</span>
					</button>
				</Heading>
				{ isOpen && actions && (
					<div ref={ actionsRef } className="mhmui-widget__actions">
						{ actions }
					</div>
				) }
			</header>
			<div
				ref={ bodyRef }
				className="mhmui-widget__body"
				id={ bodyId }
				hidden={ ! isOpen }
				// Inline, so a consumer's own `display` rule on the body cannot
				// show a closed card (the kit writes no !important).
				style={ isOpen ? undefined : HIDDEN_STYLE }
			>
				{ children }
			</div>
		</section>
	);
}
