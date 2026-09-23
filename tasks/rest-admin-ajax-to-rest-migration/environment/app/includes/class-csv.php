<?php
/**
 * CSV export of the inventory.
 *
 * The warehouse imports this file into their spreadsheet every Monday, so the
 * columns and their order are fixed.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * CSV.
 */
class Csv {

	/**
	 * Header row.
	 *
	 * @return string[]
	 */
	public static function header() {
		return array( 'SKU', 'Name', 'Stock', 'Low stock threshold', 'Location', 'Updated' );
	}

	/**
	 * One row.
	 *
	 * @param object $item Item row.
	 * @return array
	 */
	public static function row( $item ) {
		return array(
			$item->sku,
			$item->name,
			(int) $item->stock,
			(int) $item->low_stock_threshold,
			$item->location,
			'0000-00-00 00:00:00' === $item->updated_at ? '' : $item->updated_at,
		);
	}

	/**
	 * Build the CSV document.
	 *
	 * @param object[] $items Rows.
	 * @return string
	 */
	public static function build( array $items ) {
		// TODO: quoting. Nobody has commas in product names, right?
		$lines = array( implode( ',', self::header() ) );
		foreach ( $items as $item ) {
			$lines[] = implode( ',', self::row( $item ) );
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Download file name.
	 *
	 * @return string
	 */
	public static function filename() {
		return 'inventory-' . gmdate( 'Y-m-d' ) . '.csv';
	}
}
