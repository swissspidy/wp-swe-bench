<?php
/**
 * Lifecycle stages.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Stages replaced the old free-form `status` in 1.4.0.
 */
class Stages {

	/**
	 * Registered stages: slug => label.
	 *
	 * @return array<string,string>
	 */
	public static function all() {
		/**
		 * Filters the lifecycle stages.
		 *
		 * @param array<string,string> $stages Slug => label.
		 */
		return apply_filters(
			'acme_crm_stages',
			array(
				'lead'     => __( 'Lead', 'acme-crm' ),
				'prospect' => __( 'Prospect', 'acme-crm' ),
				'customer' => __( 'Customer', 'acme-crm' ),
				'churned'  => __( 'Churned', 'acme-crm' ),
			)
		);
	}

	/**
	 * Whether a stage exists.
	 *
	 * @param string $stage Slug.
	 * @return bool
	 */
	public static function exists( $stage ) {
		return is_string( $stage ) && array_key_exists( $stage, self::all() );
	}

	/**
	 * Map a pre-1.4 `status` value to a stage.
	 *
	 * @param string $status Legacy status ('lead', 'customer', 'inactive').
	 * @return string
	 */
	public static function from_legacy_status( $status ) {
		switch ( $status ) {
			case 'customer':
				return 'customer';
			case 'inactive':
				return 'churned';
			default:
				return 'lead';
		}
	}
}
