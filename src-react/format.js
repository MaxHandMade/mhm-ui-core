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
 * @return {{ fmtAmount: Function, fmtMoney: Function }} Formatters bound to that config.
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

	function fmtMoney( n, symbol, decimals ) {
		const amount = fmtAmount( n, decimals );
		switch ( currencyPosition ) {
			case 'left':
				return `${ symbol }${ amount }`;
			case 'left_space':
				return `${ symbol } ${ amount }`;
			case 'right':
				return `${ amount }${ symbol }`;
			case 'right_space':
			default:
				return `${ amount } ${ symbol }`;
		}
	}

	return { fmtAmount, fmtMoney };
}
