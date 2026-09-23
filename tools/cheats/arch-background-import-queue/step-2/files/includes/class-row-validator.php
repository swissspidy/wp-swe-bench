<?php
/**
 * Validation of CSV rows.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a row before anything of it is saved.
 */
class Row_Validator {

	/**
	 * Products (does the SKU exist?).
	 *
	 * @var Product_Repository
	 */
	private $products;

	/**
	 * Constructor.
	 *
	 * @param Product_Repository $products Products.
	 */
	public function __construct( Product_Repository $products ) {
		$this->products = $products;
	}

	/**
	 * Validates a row. Rows without a SKU are not validated (they are skipped).
	 *
	 * @param array $row       CSV row (column => value).
	 * @param int   $import_id Import ID.
	 * @return string[] Error messages (empty = valid).
	 */
	public function validate( array $row, $import_id ) {
		$errors = array();
		$sku    = normalize_sku( $row['sku'] ?? '' );

		if ( ! preg_match( '/^[A-Z0-9](?:[A-Z0-9-]{1,30})[A-Z0-9]$/', $sku ) ) {
			$errors[] = __( 'The SKU must have 3 to 32 characters (letters, digits and dashes, not starting or ending with a dash).', 'acme-importer' );
		}

		$name = trim( (string) ( $row['name'] ?? '' ) );
		if ( '' === $name && ! $errors && ! $this->products->find_by_sku( $sku ) ) {
			$errors[] = __( 'A name is required for new products.', 'acme-importer' );
		} elseif ( mb_strlen( $name ) > 200 ) {
			$errors[] = __( 'The name must not be longer than 200 characters.', 'acme-importer' );
		}

		$price = trim( (string) ( $row['price'] ?? '' ) );
		if ( '' !== $price ) {
			$cents = parse_price( $price );
			if ( null === $cents ) {
				/* translators: %s: price as given */
				$errors[] = sprintf( __( 'The price "%s" is not a valid price.', 'acme-importer' ), $price );
			} elseif ( $cents < 0 ) {
				$errors[] = __( 'The price must not be negative.', 'acme-importer' );
			}
		}

		$stock = trim( (string) ( $row['stock'] ?? '' ) );
		if ( '' !== $stock && '-' !== $stock && ! ctype_digit( $stock ) ) {
			/* translators: %s: stock as given */
			$errors[] = sprintf( __( 'The stock "%s" must be a whole number of 0 or more, or "-".', 'acme-importer' ), $stock );
		}

		$status = strtolower( trim( (string) ( $row['status'] ?? '' ) ) );
		if ( '' !== $status && ! array_key_exists( $status, product_statuses() ) ) {
			/* translators: %s: status as given */
			$errors[] = sprintf( __( 'Unknown status "%s" (use publish, draft, pending or private).', 'acme-importer' ), $status );
		}

		/**
		 * Filters the validation errors of an imported row.
		 *
		 * @param string[] $errors    Error messages.
		 * @param array    $row       CSV row (column => value).
		 * @param int      $import_id Import ID.
		 */
		$errors = apply_filters( 'acme_importer_validate_row', $errors, $row, (int) $import_id );

		return array_values( array_filter( array_map( 'strval', (array) $errors ), 'strlen' ) );
	}
}
