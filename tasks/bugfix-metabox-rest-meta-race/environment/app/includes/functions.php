<?php
/**
 * Template tags (used by the shop theme).
 *
 * @package Acme\ProductFields
 */

use Acme\ProductFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Formatted price ("$129.00"), or an empty string when the product has no price.
 *
 * @param int|null $post_id Product ID (default: current post).
 * @return string
 */
function acme_pf_get_price_html( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$price   = Fields::get( $post_id, 'price' );
	if ( '' === $price ) {
		return '';
	}
	$settings = get_option( 'acme_pf_settings', array() );
	$currency = isset( $settings['currency'] ) ? $settings['currency'] : '$';
	return $currency . number_format_i18n( (float) $price, 2 );
}

/**
 * Whether a product is in stock.
 *
 * @param int|null $post_id Product ID (default: current post).
 * @return bool
 */
function acme_pf_is_in_stock( $post_id = null ) {
	return (bool) Fields::get( $post_id ? (int) $post_id : get_the_ID(), 'in_stock' );
}

/**
 * Whether a product is featured.
 *
 * @param int|null $post_id Product ID (default: current post).
 * @return bool
 */
function acme_pf_is_featured( $post_id = null ) {
	return (bool) Fields::get( $post_id ? (int) $post_id : get_the_ID(), 'featured' );
}

/**
 * Badge text.
 *
 * @param int|null $post_id Product ID (default: current post).
 * @return string
 */
function acme_pf_get_badge( $post_id = null ) {
	return (string) Fields::get( $post_id ? (int) $post_id : get_the_ID(), 'badge' );
}
