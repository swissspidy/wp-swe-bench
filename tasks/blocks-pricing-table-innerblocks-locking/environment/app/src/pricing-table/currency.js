/**
 * Price formatting – mirrors includes/class-currency.php.
 *
 * Keep both implementations in sync: the formatted price is part of the saved
 * block markup, so any difference makes existing tables invalid in the editor.
 */

const FALLBACK_CURRENCIES = {
	USD: { label: 'US dollar', symbol: '$', position: 'before', space: false, decimal: '.', thousands: ',', whole: '' },
};

/**
 * Currencies (with formatting rules) passed from PHP.
 *
 * @return {Object} Currency code => rules.
 */
export function getCurrencies() {
	return window.acmePricing?.currencies || FALLBACK_CURRENCIES;
}

/**
 * The site's default currency (Settings → Pricing).
 *
 * @return {string} Currency code.
 */
export function getDefaultCurrency() {
	return window.acmePricing?.defaultCurrency || 'USD';
}

/**
 * Normalize an amount ("19", "19.5", "19,50", "1'900") to "19.00", "19.50", "1900.00".
 *
 * @param {string|number} amount Raw amount.
 * @return {string} Normalized amount or ''.
 */
export function normalizeAmount( amount ) {
	let value = String( amount ?? '' ).trim();
	value = value.replace( /['\s ]/g, '' );
	value = value.replace( /,(\d{1,2})$/, '.$1' );
	value = value.replace( /,/g, '' );
	if ( value === '' || isNaN( Number( value ) ) ) {
		return '';
	}
	return Number( value ).toFixed( 2 );
}

function groupThousands( whole, separator ) {
	return whole.replace( /\B(?=(\d{3})+(?!\d))/g, separator );
}

/**
 * Format an amount in a currency, e.g. "$19", "19,50 €", "CHF 49.–".
 *
 * @param {string|number} amount Amount.
 * @param {string}        code   Currency code.
 * @return {string} Formatted price or ''.
 */
export function formatPrice( amount, code ) {
	const normalized = normalizeAmount( amount );
	if ( normalized === '' ) {
		return '';
	}
	const currencies = getCurrencies();
	const rules = currencies[ code ] || currencies.USD || FALLBACK_CURRENCIES.USD;

	const [ wholeRaw, cents ] = normalized.split( '.' );
	const whole = groupThousands( String( Number( wholeRaw ) ), rules.thousands );
	const number = cents === '00' ? whole + rules.whole : whole + rules.decimal + cents;
	const space = rules.space ? ' ' : '';

	return rules.position === 'before'
		? rules.symbol + space + number
		: number + space + rules.symbol;
}
