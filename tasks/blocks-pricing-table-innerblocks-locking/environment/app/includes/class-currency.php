<?php
/**
 * Price formatting.
 *
 * Mirrors src/pricing-table/currency.js – both must produce identical output,
 * otherwise saved tables become invalid in the editor.
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Currency helpers.
 */
class Currency {

	/**
	 * Normalize a currency code; unknown codes fall back to USD.
	 *
	 * @param string $code Currency code.
	 * @return string
	 */
	public static function sanitize_code( $code ) {
		$code       = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $code ) );
		$currencies = acme_pricing_currencies();
		return isset( $currencies[ $code ] ) ? $code : 'USD';
	}

	/**
	 * Normalize an amount entered by an editor ("19", "19.5", "19,50", "1'900") to a
	 * decimal string with two decimals ("19.00", "19.50", "1900.00"). Empty/invalid → ''.
	 *
	 * @param mixed $amount Raw amount.
	 * @return string
	 */
	public static function normalize_amount( $amount ) {
		$amount = trim( (string) $amount );
		$amount = str_replace( array( "'", ' ', "\u{00A0}" ), '', $amount );
		// A comma followed by one or two digits at the end is a decimal comma.
		$amount = preg_replace( '/,(\d{1,2})$/', '.$1', $amount );
		$amount = str_replace( ',', '', $amount );
		if ( '' === $amount || ! is_numeric( $amount ) ) {
			return '';
		}
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Format an amount.
	 *
	 * @param mixed  $amount Amount.
	 * @param string $code   Currency code.
	 * @return string
	 */
	public static function format( $amount, $code ) {
		$normalized = self::normalize_amount( $amount );
		if ( '' === $normalized ) {
			return '';
		}
		$currencies = acme_pricing_currencies();
		$code       = self::sanitize_code( $code );
		$rules      = $currencies[ $code ];

		list( $whole, $cents ) = explode( '.', $normalized );
		$whole                 = number_format( (float) $whole, 0, '', $rules['thousands'] );
		if ( '00' === $cents ) {
			$number = $whole . $rules['whole'];
		} else {
			$number = $whole . $rules['decimal'] . $cents;
		}

		$space = $rules['space'] ? ' ' : '';
		return 'before' === $rules['position'] ? $rules['symbol'] . $space . $number : $number . $space . $rules['symbol'];
	}
}
