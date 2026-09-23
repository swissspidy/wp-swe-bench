<?php
/**
 * Template tags and small helpers.
 *
 * @package Acme\Loyalty
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings merged with defaults.
 *
 * @return array{points_per_currency:int, welcome_bonus:int, double_optin:bool, store_names:string[]}
 */
function acme_loyalty_settings() {
	$saved = get_option( 'acme_loyalty_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( acme_loyalty_default_settings(), $saved );
}

/**
 * Default settings.
 *
 * @return array
 */
function acme_loyalty_default_settings() {
	return array(
		'points_per_currency' => 1,
		'welcome_bonus'       => 50,
		'double_optin'        => true,
		'store_names'         => array( 'Downtown', 'Harbour', 'Online' ),
	);
}

/**
 * Registered member tiers (slug => [label, threshold in lifetime points]).
 *
 * @return array<string, array{label:string, threshold:int}>
 */
function acme_loyalty_tiers() {
	$tiers = array(
		'bronze' => array(
			'label'     => __( 'Bronze', 'acme-loyalty' ),
			'threshold' => 0,
		),
		'silver' => array(
			'label'     => __( 'Silver', 'acme-loyalty' ),
			'threshold' => 500,
		),
		'gold'   => array(
			'label'     => __( 'Gold', 'acme-loyalty' ),
			'threshold' => 2000,
		),
	);

	/**
	 * Filters the member tiers.
	 *
	 * @param array $tiers Tier slug => array( 'label' => string, 'threshold' => int ).
	 */
	return apply_filters( 'acme_loyalty_tiers', $tiers );
}

/**
 * Human-readable tier label.
 *
 * @param string $tier Tier slug.
 * @return string
 */
function acme_loyalty_tier_label( $tier ) {
	$tiers = acme_loyalty_tiers();
	return isset( $tiers[ $tier ] ) ? $tiers[ $tier ]['label'] : ucfirst( (string) $tier );
}

/**
 * Contact channels a member can opt into.
 *
 * @return array<string, string>
 */
function acme_loyalty_channels() {
	return array(
		'email' => __( 'Email', 'acme-loyalty' ),
		'sms'   => __( 'SMS', 'acme-loyalty' ),
		'post'  => __( 'Post', 'acme-loyalty' ),
		'phone' => __( 'Phone call', 'acme-loyalty' ),
	);
}

/**
 * Format a points amount ("1,250 points").
 *
 * @param int $points Points.
 * @return string
 */
function acme_loyalty_format_points( $points ) {
	/* translators: %s: formatted number of points */
	return sprintf( _n( '%s point', '%s points', abs( (int) $points ), 'acme-loyalty' ), number_format_i18n( (int) $points ) );
}

/**
 * Format a UTC MySQL datetime for display in the site's date format.
 *
 * @param string|null $datetime UTC datetime (Y-m-d H:i:s).
 * @return string Empty string for empty/zero dates.
 */
function acme_loyalty_format_date( $datetime ) {
	if ( empty( $datetime ) || 0 === strpos( (string) $datetime, '0000-00-00' ) ) {
		return '';
	}
	$ts = strtotime( $datetime . ' UTC' );
	return $ts ? wp_date( get_option( 'date_format' ), $ts ) : '';
}

/**
 * Labels for ledger reasons.
 *
 * @return array<string, string>
 */
function acme_loyalty_reasons() {
	return array(
		'purchase' => __( 'Purchase', 'acme-loyalty' ),
		'welcome'  => __( 'Welcome bonus', 'acme-loyalty' ),
		'referral' => __( 'Referral', 'acme-loyalty' ),
		'redeem'   => __( 'Redeemed', 'acme-loyalty' ),
		'manual'   => __( 'Manual adjustment', 'acme-loyalty' ),
		'expire'   => __( 'Expired', 'acme-loyalty' ),
	);
}

/**
 * Client IP address of the current request (best effort).
 *
 * @return string
 */
function acme_loyalty_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}
