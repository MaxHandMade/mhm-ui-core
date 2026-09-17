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
 * @param {string}  props.label      Translated label.
 * @param {string}  props.value      Already-formatted value.
 * @param {string}  [props.icon]     Dashicons class suffix, e.g. "calendar-alt".
 * @param {string}  [props.tone]     One of TONES; anything else is dropped.
 * @param {string}  [props.sub]      Secondary line, shown when no delta line is.
 * @param {Object}  [props.delta]    { direction: one of DIRECTIONS, text }.
 * @param {boolean} [props.emphasis] Value in the accent colour; not a fill.
 * @param {Object}  [props.data]     { key: value } -> data-key="value"; keys ^[a-z0-9-]{1,32}$.
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
// through: strip everything outside A-Z a-z 0-9 _ -, don't collapse the gap,
// so "calendar alt" becomes "calendaralt" in both twins rather than two
// space-separated classes here and one merged one there (measured
// 2026-09-17). Call only after present( icon ) -- it stringifies anything.
const sanitizeIconClass = ( v ) => String( v ).replace( /[^A-Za-z0-9_-]/g, '' );

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
		line = (
			<p
				className={ `mhmui-stat-card__delta mhmui-stat-card__delta--${ direction }` }
			>
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
