/**
 * @typedef {Object} Formatters
 * @property {(n: (number|string|null|undefined), decimals?: number) => string}                 fmtAmount The number alone; null/undefined read as 0.
 * @property {(n: (number|string|null|undefined), symbol: string, decimals?: number) => string} fmtMoney  The number with its symbol.
 */

/**
 * Currency formatting for MHM admin screens.
 *
 * The host owns the settings. In a WooCommerce-backed plugin they come from
 * wc_get_price_decimal_separator(), wc_get_price_thousand_separator(),
 * wc_get_price_decimals() and woocommerce_currency_pos, handed to the browser through
 * wp_localize_script(); this module never reads a global of its own, so two plugins on
 * one screen cannot format each other's money.
 *
 * @param {Object} config                  - Host formatting settings; every field is optional.
 * @param {string} config.decimalSep       - Decimal separator.
 * @param {string} config.thousandSep      - Thousands separator.
 * @param {number} config.numDecimals      - Default decimal places.
 * @param {string} config.currencyPosition - left | left_space | right | right_space.
 * @return {Formatters} Formatters bound to that config.
 */
export function createFormatter( config = {} ) {
	const decimalSep = config.decimalSep ?? ',';
	const thousandSep = config.thousandSep ?? '.';
	const numDecimals = config.numDecimals ?? 2;
	// Last-resort default matches CurrencyHelper's: `right_space`. Keep the two in step —
	// a different default here would be a second placement rule.
	const currencyPosition = config.currencyPosition ?? 'right_space';

	function fmtAmount( n, decimals ) {
		const dec = decimals ?? numDecimals;
		const fixed = Number( n ?? 0 ).toFixed( dec );
		const [ int, decPart ] = fixed.split( '.' );
		const intFormatted = int.replace(
			/\B(?=(\d{3})+(?!\d))/g,
			thousandSep
		);
		return dec > 0
			? `${ intFormatted }${ decimalSep }${ decPart }`
			: intFormatted;
	}

	// A no-break space (U+00A0) between symbol and number, as WooCommerce's own
	// price format puts `&nbsp;` there (get_woocommerce_price_format()). With a
	// plain space a narrow card wrapped the symbol onto its own line (0.27.5).
	const NBSP = '\u00a0';

	function fmtMoney( n, symbol, decimals ) {
		const amount = fmtAmount( n, decimals );
		// No symbol, no separator (Fable 0.27.5): `currency ?? ''` call sites
		// left a no-break space that does not collapse, unlike the plain one.
		if ( symbol === undefined || symbol === null || symbol === '' ) {
			return amount;
		}
		switch ( currencyPosition ) {
			case 'left':
				return `${ symbol }${ amount }`;
			case 'left_space':
				return `${ symbol }${ NBSP }${ amount }`;
			case 'right':
				return `${ amount }${ symbol }`;
			case 'right_space':
			default:
				return `${ amount }${ NBSP }${ symbol }`;
		}
	}

	return { fmtAmount, fmtMoney };
}
