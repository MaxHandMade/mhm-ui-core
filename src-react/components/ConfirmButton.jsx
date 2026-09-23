import { useState, useRef, useEffect, useId } from '@wordpress/element';

const VARIANT_CLASS = {
	primary: 'mhmui-confirm mhmui-confirm--primary',
	secondary: 'mhmui-confirm mhmui-confirm--secondary',
	danger: 'mhmui-confirm mhmui-confirm--danger',
};

const FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * A two-step confirmation inside the page -- replaces window.confirm (not in
 * screenshots, not testable, off the design).
 *
 * This is the package's own focus contract, not an APG pattern (APG has no
 * inline-confirmation pattern; Escape + focus back to the trigger follows
 * the APG menu-button example):
 *   1. Opening shows the question, the optional body (children) and the two
 *      buttons. Focus goes to the body's first focusable element, else to
 *      confirm. The question is wired to both buttons with aria-describedby.
 *   2. Cancel or Escape closes it; focus back to the trigger.
 *   3. confirmDisabled: confirm is aria-disabled (still focusable); a click
 *      does not confirm and sends focus to the body.
 *   4. Busy (onConfirm returned a promise): both buttons aria-disabled and
 *      guarded; Escape and cancel do nothing -- otherwise cancel + click again
 *      sends a second request. Native `disabled` is not used: it drops the
 *      element from the tab order while it has focus.
 *   5. Settle: Promise.resolve(onConfirm()).finally(reset), with NO catch --
 *      the consumer handles errors inside onConfirm; an unhandled rejection
 *      is not swallowed (it reaches the console). A synchronous throw is
 *      turned into a rejection so the lock still releases. If still mounted,
 *      back to the trigger, focus to it when focus was inside. If the row
 *      unmounted, focus is the consumer's.
 *   6. `disabled` locks the trigger, and both prompt buttons when open --
 *      that is how a sibling in the same row is locked.
 *
 * @param {Object}   props
 * @param {string}   props.label             Trigger text.
 * @param {string}   props.confirmText       The question.
 * @param {string}   props.confirmLabel
 * @param {string}   props.cancelLabel
 * @param {string}   props.busyText          Confirm text while busy.
 * @param {Function} props.onConfirm         () => void | Promise.
 * @param {string}   [props.variant]         primary | secondary | danger.
 * @param {boolean}  [props.confirmDisabled]
 * @param {boolean}  [props.disabled]
 * @param {string}   [props.describedBy]     Id of a hint for the trigger.
 * @param {*}        [props.children]        Prompt body (e.g. a reason field).
 */
export default function ConfirmButton( {
	label,
	confirmText,
	confirmLabel,
	cancelLabel,
	busyText,
	onConfirm,
	variant = 'secondary',
	confirmDisabled = false,
	disabled = false,
	describedBy,
	children,
} ) {
	const [ open, setOpen ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const textId = useId();
	const rootRef = useRef( null );
	const triggerRef = useRef( null );
	const confirmRef = useRef( null );
	const bodyRef = useRef( null );
	const mounted = useRef( true );
	const returnFocus = useRef( false );

	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	useEffect( () => {
		if ( open ) {
			const first = bodyRef.current
				? bodyRef.current.querySelector( FOCUSABLE )
				: null;
			( first || confirmRef.current )?.focus();
			return;
		}
		if ( returnFocus.current && triggerRef.current ) {
			triggerRef.current.focus();
		}
		returnFocus.current = false;
	}, [ open ] );

	const locked = busy || disabled;

	const focusBody = () => {
		const first = bodyRef.current
			? bodyRef.current.querySelector( FOCUSABLE )
			: null;
		if ( first ) {
			first.focus();
		}
	};

	const close = () => {
		returnFocus.current = true;
		setOpen( false );
	};

	const confirm = () => {
		if ( locked ) {
			return;
		}
		if ( confirmDisabled ) {
			focusBody();
			return;
		}
		setBusy( true );
		let result;
		try {
			result = Promise.resolve( onConfirm() );
		} catch ( error ) {
			result = Promise.reject( error );
		}
		result.finally( () => {
			if ( ! mounted.current ) {
				return;
			}
			setBusy( false );
			const root = rootRef.current;
			returnFocus.current = !! (
				root && root.contains( root.ownerDocument.activeElement )
			);
			setOpen( false );
		} );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Escape' && ! locked ) {
			event.stopPropagation();
			close();
		}
	};

	return (
		<div
			className={ VARIANT_CLASS[ variant ] || VARIANT_CLASS.secondary }
			ref={ rootRef }
		>
			{ ! open ? (
				<button
					type="button"
					ref={ triggerRef }
					className="button mhmui-confirm__trigger"
					aria-disabled={ disabled ? 'true' : undefined }
					aria-describedby={ describedBy ? describedBy : undefined }
					onClick={ () => {
						if ( ! disabled ) {
							setOpen( true );
						}
					} }
				>
					{ label }
				</button>
			) : (
				// eslint-disable-next-line jsx-a11y/no-static-element-interactions -- keydown only listens for Escape bubbling from the buttons/body inside.
				<div className="mhmui-confirm__prompt" onKeyDown={ onKeyDown }>
					<p className="mhmui-confirm__text" id={ textId }>
						{ confirmText }
					</p>
					{ children && (
						<div className="mhmui-confirm__body" ref={ bodyRef }>
							{ children }
						</div>
					) }
					<div className="mhmui-confirm__actions">
						<button
							type="button"
							ref={ confirmRef }
							className="button mhmui-confirm__confirm"
							aria-describedby={ textId }
							aria-disabled={
								locked || confirmDisabled ? 'true' : undefined
							}
							onClick={ confirm }
						>
							{ busy ? busyText : confirmLabel }
						</button>
						<button
							type="button"
							className="button mhmui-confirm__cancel"
							aria-describedby={ textId }
							aria-disabled={ locked ? 'true' : undefined }
							onClick={ () => {
								if ( ! locked ) {
									close();
								}
							} }
						>
							{ cancelLabel }
						</button>
					</div>
				</div>
			) }
		</div>
	);
}
