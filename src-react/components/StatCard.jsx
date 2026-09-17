/**
 * One statistic: label, value, optional icon, delta or sub line.
 *
 * Extracted from Rentiva's dashboard StatsCards. Every string is a prop --
 * this package has no text domain, so the consumer formats the value and
 * translates the label before rendering.
 *
 * This is the kit's ONLY key-figure component, and since 0.11.0 it has a PHP
 * twin (src/Kit/StatCard.php). The two apply the SAME vocabularies below; the
 * class sets they emit are compared by gate 6 (tests/Gate/kit-parity.test.js).
 *
 * @param {Object}  props
 * @param {string}  props.label         Translated label.
 * @param {string}  props.value         Already-formatted value.
 * @param {string}  [props.icon]        Dashicons class suffix, e.g. "calendar-alt".
 * @param {string}  [props.tone]        One of TONES; anything else is dropped.
 * @param {string}  [props.sub]         Secondary line, shown when no delta line is.
 * @param {Object}  [props.delta]       { direction: one of DIRECTIONS, text, label? }. Since
 *                                      0.12.0 `text` must be PLAIN -- no arrow, no sign --
 *                                      because the kit itself renders the direction mark (an
 *                                      aria-hidden ↑/↓ before the text) for `up`/`down`, never
 *                                      for `flat`. This is a breaking change from <=0.11.x: a
 *                                      consumer that still puts an arrow or sign in `text`
 *                                      will show two. Colour alone must never be the only cue
 *                                      (WCAG 1.4.1) -- that is now the kit's job, not the
 *                                      consumer's, because only the kit knows the direction
 *                                      vocabulary and a shared kit cannot rely on every
 *                                      consumer's text agreeing.
 * @param {string}  [props.delta.label] Since 0.13.0: an optional, already-translated
 *                                      accessible name for the delta line (e.g. "artış" /
 *                                      "azalış" / "rose 5% this month"), supplied by the
 *                                      CONSUMER -- this package has no text domain and cannot
 *                                      invent one. Rendered as visually-hidden text inside the
 *                                      delta line, after the aria-hidden mark: without it, up
 *                                      and down still announce identically to a screen reader
 *                                      (the mark is aria-hidden and data-direction is not an
 *                                      accessible name), exactly as in 0.12.0.
 * @param {boolean} [props.emphasis]    Value in the accent colour; not a fill.
 * @param {Object}  [props.data]        { key: value } -> data-key="value"; keys ^[a-z0-9-]{1,32}$.
 */
export const TONES = [ 'success', 'warning', 'danger', 'info', 'neutral' ];
export const DIRECTIONS = [ 'up', 'down', 'flat' ];

const DATA_KEY = /^[a-z0-9-]{1,32}$/;

// Presence test for the string/number props icon and sub take, matching the
// PHP twin's '' !== $icon / '' === $sub check for those same two types: a
// value counts as present once it is a non-empty string or number -- "0" and
// 0 both present, "" and undefined both absent. Plain `icon && ...` /
// `else if ( sub )` treats numeric 0 as absent (0 is JS-falsy), which the PHP
// twin does not. The PHP twin narrows to the same string|int|float range for
// these two props (StatCard.php's presence_text(), not the wider is_scalar()
// text() label/value/delta.text still use), so a boolean is absent on both
// sides too: `icon: true` / `sub: true` render nothing in either twin
// (measured 2026-09-17). The two now agree on the full scalar range icon and
// sub are documented to take.
const present = ( v ) =>
	( typeof v === 'string' || typeof v === 'number' ) && String( v ) !== '';

// Mirrors WordPress's sanitize_html_class(), which the PHP twin runs icon
// through: FIRST strip percent-encoded octets ( %[0-9a-fA-F]{2} ) as whole
// units, THEN strip everything outside A-Z a-z 0-9 _ -, without collapsing
// the gap -- so "calendar alt" becomes "calendaralt" in both twins rather
// than two space-separated classes here and one merged one there, and
// "%41" becomes "" in both rather than "41" here (dropping only the "%"
// and keeping the hex digits behind it, which is not what the octet step
// removes) vs. "" there (measured 2026-09-17). Call only after
// present( icon ) -- it stringifies anything.
const sanitizeIconClass = ( v ) =>
	String( v )
		.replace( /%[0-9a-fA-F]{2}/g, '' )
		.replace( /[^A-Za-z0-9_-]/g, '' );

function dataAttributes( data ) {
	const out = {};
	if ( ! data || typeof data !== 'object' ) {
		return out;
	}
	for ( const [ key, value ] of Object.entries( data ) ) {
		if (
			DATA_KEY.test( key ) &&
			( typeof value === 'string' || typeof value === 'number' )
		) {
			out[ `data-${ key }` ] = String( value );
		}
	}
	return out;
}

export default function StatCard( {
	label,
	value,
	icon,
	tone = '',
	sub,
	delta,
	emphasis = false,
	data,
} ) {
	const classes = [ 'mhmui-stat-card' ];
	if ( TONES.includes( tone ) ) {
		classes.push( `mhmui-stat-card--${ tone }` );
	}
	if ( emphasis === true ) {
		classes.push( 'mhmui-stat-card--emphasis' );
	}

	let line = null;
	const direction = delta && typeof delta === 'object' ? delta.direction : '';
	if ( direction === 'up' || direction === 'down' ) {
		// The kit supplies the direction mark itself (measured 2026-09-17: relying
		// on the consumer's delta.text to carry an arrow or sign left an unsigned
		// text like "3 this month" distinguishable only by colour -- WCAG 1.4.1).
		// aria-hidden on the mark: it is decorative, not a translated word this
		// package (no text domain) could add as an accessible name. See
		// StatCard.php's DIRECTION_MARKS docblock for the a11y reasoning in full.
		const mark = direction === 'up' ? '↑' : '↓';
		// delta.label is optional, consumer-translated (this package has no text
		// domain): when present it becomes the delta line's accessible name,
		// visually hidden and placed right after the aria-hidden mark so a
		// screen reader reads "<label> <text>" (e.g. "artış 3 this month")
		// instead of colour being the only up/down cue (WCAG 1.4.1, measured
		// 2026-09-17: the 0.12.0 mark is aria-hidden and data-direction is not
		// an accessible name either, so up and down announced identically).
		// Absent, behaviour is exactly 0.12.0 -- no fallback string is invented.
		line = (
			<p
				className={ `mhmui-stat-card__delta mhmui-stat-card__delta--${ direction }` }
				data-direction={ direction }
			>
				<span
					className="mhmui-stat-card__delta-mark"
					aria-hidden="true"
				>
					{ mark }
				</span>
				{ present( delta.label ) && (
					<span className="mhmui-stat-card__delta-sr">
						{ delta.label }
					</span>
				) }
				{ delta.text }
			</p>
		);
	} else if ( present( sub ) ) {
		line = <p className="mhmui-stat-card__sub">{ sub }</p>;
	}

	// A sanitised-away icon ( e.g. "%%" ) renders no span, matching the PHP
	// twin's '' !== $icon check on its own sanitize_html_class() output.
	const iconClass = present( icon ) ? sanitizeIconClass( icon ) : '';

	return (
		<div className={ classes.join( ' ' ) } { ...dataAttributes( data ) }>
			{ iconClass !== '' && (
				<span
					className={ `dashicons dashicons-${ iconClass }` }
					aria-hidden="true"
				/>
			) }
			<div className="mhmui-stat-card__body">
				<p className="mhmui-stat-card__label">{ label }</p>
				<p className="mhmui-stat-card__value">{ value }</p>
				{ line }
			</div>
		</div>
	);
}
