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
			self::text( $item->sku ),
			self::text( $item->name ),
			(int) $item->stock,
			(int) $item->low_stock_threshold,
			self::text( $item->location ),
			empty( $item->updated_at ) || '0000-00-00 00:00:00' === $item->updated_at ? '' : $item->updated_at,
		);
	}

	/**
	 * Neutralize spreadsheet formulas in text cells (CSV injection).
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	public static function text( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	/**
	 * RFC 4180 field.
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	public static function field( $value ) {
		$value = (string) $value;
		if ( preg_match( '/[",\r\n]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	/**
	 * One CSV line (CRLF terminated).
	 *
	 * @param array $cells Cells.
	 * @return string
	 */
	public static function line( array $cells ) {
		return implode( ',', array_map( array( __CLASS__, 'field' ), $cells ) ) . "\r\n";
	}

	/**
	 * Build the CSV document.
	 *
	 * @param object[] $items Rows.
	 * @return string
	 */
	public static function build( array $items ) {
		$out = self::line( self::header() );
		foreach ( $items as $item ) {
			$out .= self::line( self::row( $item ) );
		}
		return $out;
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
