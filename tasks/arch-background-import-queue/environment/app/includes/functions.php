<?php
/**
 * Helper functions.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings with defaults.
 *
 * @return array{default_status: string}
 */
function settings() {
	$settings = get_option( 'acme_importer_settings', array() );
	return wp_parse_args(
		is_array( $settings ) ? $settings : array(),
		array(
			'default_status' => 'draft',
		)
	);
}

/**
 * Product statuses an import may set.
 *
 * @return array<string,string>
 */
function product_statuses() {
	return array(
		'publish' => __( 'Published', 'acme-importer' ),
		'draft'   => __( 'Draft', 'acme-importer' ),
		'pending' => __( 'Pending review', 'acme-importer' ),
		'private' => __( 'Private', 'acme-importer' ),
	);
}

/**
 * Normalizes a SKU the way 2.x stores it: trimmed and upper-case.
 *
 * Note: 1.x stored SKUs exactly as typed in the spreadsheet.
 *
 * @param string $sku Raw SKU.
 * @return string
 */
function normalize_sku( $sku ) {
	return strtoupper( trim( (string) $sku ) );
}

/**
 * Parses a price as found in supplier spreadsheets into cents.
 *
 * Understands "12.5", "12,50", "1.234,50", "1,234.50", "€ 12,00" and "12". Returns
 * null for anything else.
 *
 * @param string $raw Raw price.
 * @return int|null Price in cents.
 */
function parse_price( $raw ) {
	$value = preg_replace( '/[^\d.,\-]/', '', (string) $raw );
	if ( '' === $value || ! preg_match( '/\d/', $value ) ) {
		return null;
	}

	$negative = 0 === strpos( $value, '-' );
	$value    = ltrim( $value, '-' );

	$last_comma = strrpos( $value, ',' );
	$last_dot   = strrpos( $value, '.' );
	if ( false !== $last_comma && ( false === $last_dot || $last_comma > $last_dot ) ) {
		// Comma is the decimal separator (unless it groups thousands: "1,234").
		if ( preg_match( '/^\d{1,3}(,\d{3})+$/', $value ) ) {
			$value = str_replace( ',', '', $value );
		} else {
			$value = str_replace( '.', '', $value );
			$value = str_replace( ',', '.', $value );
		}
	} else {
		if ( preg_match( '/^\d{1,3}(\.\d{3}){2,}$/', $value ) ) {
			$value = str_replace( '.', '', $value );
		}
		$value = str_replace( ',', '', $value );
	}

	if ( ! preg_match( '/^\d+(\.\d+)?$/', $value ) ) {
		return null;
	}

	$cents = (int) round( (float) $value * 100 );
	return $negative ? -$cents : $cents;
}

/**
 * Formats cents for display.
 *
 * @param int|string $cents Price in cents ('' = no price).
 * @return string
 */
function format_price( $cents ) {
	if ( '' === $cents || null === $cents ) {
		return '—';
	}
	return number_format_i18n( ( (int) $cents ) / 100, 2 );
}
