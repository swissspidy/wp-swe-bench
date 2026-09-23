<?php
/**
 * Maps CSV rows to product data.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a CSV row (column => string) into product data for the repository.
 *
 * Empty cells mean "leave as is" for existing products.
 */
class Row_Mapper {

	/**
	 * Maps a row.
	 *
	 * @param array<string,string> $row        CSV row.
	 * @param int                  $row_number Spreadsheet row number.
	 * @return array|false Product data, or false to skip the row.
	 */
	public function map( array $row, $row_number = 0 ) {
		$sku = normalize_sku( $row['sku'] ?? '' );
		if ( '' === $sku ) {
			return false;
		}

		$data = array(
			'sku'         => $sku,
			'name'        => isset( $row['name'] ) && '' !== $row['name'] ? sanitize_text_field( $row['name'] ) : null,
			'price'       => null,
			'stock'       => null,
			'status'      => null,
			'categories'  => null,
			'description' => isset( $row['description'] ) && '' !== $row['description'] ? wp_kses_post( $row['description'] ) : null,
		);

		if ( isset( $row['price'] ) && '' !== $row['price'] ) {
			// @todo 2.0: unparseable prices are silently ignored, the old price is kept.
			$data['price'] = parse_price( $row['price'] );
		}

		if ( isset( $row['stock'] ) && '' !== $row['stock'] ) {
			$data['stock'] = '-' === $row['stock'] ? '' : max( 0, (int) $row['stock'] );
		}

		if ( isset( $row['status'] ) && '' !== $row['status'] ) {
			$status         = strtolower( $row['status'] );
			$data['status'] = array_key_exists( $status, product_statuses() ) ? $status : null;
		}

		if ( isset( $row['categories'] ) && '' !== $row['categories'] ) {
			$data['categories'] = array_values( array_filter( array_map( 'trim', explode( '|', $row['categories'] ) ) ) );
		}

		/**
		 * Filters the product data of an imported row.
		 *
		 * Return false to skip the row.
		 *
		 * @param array|false $data       Product data.
		 * @param array       $row        Raw CSV row (column => value).
		 * @param int         $row_number Spreadsheet row number.
		 */
		return apply_filters( 'acme_importer_row_data', $data, $row, $row_number );
	}
}
