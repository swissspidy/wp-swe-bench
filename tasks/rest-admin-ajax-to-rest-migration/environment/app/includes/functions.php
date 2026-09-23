<?php
/**
 * Helpers.
 *
 * @package Acme\Inventory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability required to use the inventory (screen, adjustments, export).
 *
 * Granted to administrators and shop managers on activation; some sites give
 * it to individual warehouse staff accounts.
 *
 * @return string
 */
function acme_inventory_capability() {
	/**
	 * Filters the inventory capability.
	 *
	 * @param string $capability Default 'manage_acme_inventory'.
	 */
	return (string) apply_filters( 'acme_inventory_capability', 'manage_acme_inventory' );
}

/**
 * Can the current user manage the inventory?
 *
 * @return bool
 */
function acme_inventory_current_user_can_manage() {
	return current_user_can( acme_inventory_capability() );
}
