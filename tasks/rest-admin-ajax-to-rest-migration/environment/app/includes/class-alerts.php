<?php
/**
 * Low-stock e-mail alerts.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Alerts.
 */
class Alerts {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'acme_inventory_stock_changed', array( $this, 'maybe_alert' ), 10, 4 );
	}

	/**
	 * Alert the warehouse when an item drops to (or below) its threshold.
	 *
	 * @param int    $id     Item ID.
	 * @param int    $old    Previous stock.
	 * @param int    $stock  New stock.
	 * @param string $source Source.
	 */
	public function maybe_alert( $id, $old, $stock, $source ) {
		$item = Items::find( $id );
		if ( ! $item ) {
			return;
		}
		$threshold = (int) $item->low_stock_threshold;
		if ( $stock > $threshold || $old <= $threshold ) {
			return; // Not crossing the threshold.
		}
		$to = get_option( 'acme_inventory_alert_email' );
		$to = is_email( $to ) ? $to : get_option( 'admin_email' );
		/* translators: 1: SKU, 2: product name. */
		$subject = sprintf( __( 'Low stock: %1$s (%2$s)', 'acme-inventory' ), $item->sku, $item->name );
		/* translators: 1: stock, 2: threshold, 3: location. */
		$message = sprintf( __( "Stock is now %1\$d (threshold %2\$d).\nLocation: %3\$s", 'acme-inventory' ), $stock, $threshold, $item->location );
		wp_mail( $to, $subject, $message );
	}
}
