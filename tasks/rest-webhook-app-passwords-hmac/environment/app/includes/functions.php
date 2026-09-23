<?php
/**
 * Small helpers shared by the plugin (and used by the fulfilment glue code on
 * our sites, so keep the signatures stable).
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin singleton.
 */
function plugin(): Plugin {
	return Plugin::instance();
}

/**
 * Formats an amount in minor units (cents) for display, e.g. 12950 + EUR => "129.50 EUR".
 *
 * @param int    $minor    Amount in minor units.
 * @param string $currency ISO 4217 code.
 */
function format_money( int $minor, string $currency ): string {
	$decimals = in_array( strtoupper( $currency ), array( 'JPY', 'KRW' ), true ) ? 0 : 2;
	$amount   = $decimals ? $minor / 100 : $minor;
	return number_format_i18n( $amount, $decimals ) . ' ' . strtoupper( $currency );
}

/**
 * Converts a decimal amount as sent by the shop ("129.5", 129.5, "129,50") to minor units.
 *
 * Shop API v1 sent strings with a comma in some locales; v2 sends numbers.
 *
 * @param mixed $amount Amount.
 */
function to_minor_units( $amount ): int {
	if ( is_string( $amount ) ) {
		$amount = str_replace( array( ' ', ',' ), array( '', '.' ), $amount );
	}
	return (int) round( ( (float) $amount ) * 100 );
}

/**
 * Local order post ID for a shop order, or 0.
 *
 * @param string $source       Source (storefront) ID.
 * @param string $order_number Shop order number.
 */
function find_order( string $source, string $order_number ): int {
	$ids = get_posts(
		array(
			'post_type'              => Order_Post_Type::POST_TYPE,
			'post_status'            => 'any',
			'numberposts'            => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'             => array(
				'relation' => 'AND',
				array(
					'key'   => '_acme_order_number',
					'value' => $order_number,
				),
				array(
					'key'   => '_acme_order_source',
					'value' => $source,
				),
			),
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Current time as an ISO 8601 UTC string (the format the shop uses).
 */
function now_iso(): string {
	return gmdate( 'Y-m-d\TH:i:s\Z' );
}
