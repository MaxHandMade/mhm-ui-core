/**
 * The kit's semantic icon vocabulary -- JSX twin of src/Kit/Icons.php.
 *
 * Same seed table, same resolution order, same last-write-wins registration.
 * Gate 6 parses the PHP twin's SEED out of its source and compares it pair by
 * pair with this object.
 *
 * SCOPE, AND HOW IT DIFFERS FROM THE PHP TWIN: this registry lives in the
 * bundle that imports it. Two plugins that each bundle the kit each get their
 * own, so one product's registerIcons() does NOT reach another product's
 * bundle -- while the PHP twin's registry IS shared, because there the winning
 * ui-core copy serves everybody. Each product registers in its own bundle.
 *
 * No key of SEED may be a real Dashicon name: a consumer writing that raw
 * suffix today must keep seeing the same icon tomorrow (see Icons.php).
 */
export const SEED = {
	revenue: 'money-alt',
	total: 'chart-bar',
	count: 'list-view',
	rate: 'chart-line',
	customers: 'admin-users',
	items: 'products',
	pending: 'clock',
	active: 'yes-alt',
	new: 'plus-alt',
	returning: 'update',
	time: 'calendar-alt',
	place: 'location-alt',
};

// Prototype-free: a plain {} answers for 'constructor', 'toString' and
// '__proto__', which the PHP twin never does -- the two would print different
// classes for the same prop. Object.hasOwn below guards SEED, an object
// literal this module does not control the prototype of.
let registered = Object.create( null );

/**
 * Register product concepts. The last registration of a concept wins; entries
 * that are not string => non-empty-string are skipped.
 *
 * DIVERGENCE FROM PHP TWIN: numeric-keyed entries. In PHP, array( 42 =>
 * 'chart-pie' ) stores int 42, which is_string() rejects and the twin skips.
 * In JS, Object.entries() yields '42' (string), so the same entry registers.
 * This divergence could be closed — we could reject numeric-keyed entries
 * to mimic PHP's int/string distinction. We don't, because that rule would
 * bind PHP's array-key behavior (a language artifact) into the vocabulary's
 * design. The vocabulary owns what concept maps to what icon; PHP's array
 * backend is not its concern. In practice, concept names are words
 * (vehicles, revenue, ...), never numeric strings, so the risk is null.
 *
 * @param {Object} map Concept -> Dashicon suffix.
 */
export function registerIcons( map ) {
	if ( ! map || typeof map !== 'object' ) {
		return;
	}

	for ( const [ concept, suffix ] of Object.entries( map ) ) {
		if ( concept === '' || typeof suffix !== 'string' || suffix === '' ) {
			continue;
		}
		registered[ concept ] = suffix;
	}
}

/**
 * Resolve an `icon` value: a concept becomes its suffix, anything else passes
 * through unchanged (K1). A non-string is stringified, never thrown on.
 *
 * @param {string} value Concept or raw Dashicon suffix.
 * @return {string} Dashicon suffix.
 */
export function resolveIcon( value ) {
	if ( value === undefined || value === null ) {
		return '';
	}

	const key = String( value );

	if ( key in registered ) {
		return registered[ key ];
	}

	return Object.hasOwn( SEED, key ) ? SEED[ key ] : key;
}

/**
 * Drop every registration. A TEST SEAM; deliberately not re-exported from
 * index.js -- nothing in a consumer's bundle should reset a shared vocabulary.
 */
export function resetIcons() {
	registered = Object.create( null );
}
