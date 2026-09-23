<?php
/**
 * Inventory operations shared by the REST API and the (deprecated) admin-ajax
 * actions: validation, all-or-nothing bulk adjustments, CSV export.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Service.
 */
class Service {

	const MAX_BULK = 100;

	/**
	 * A 400 validation error naming a field.
	 *
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	public static function invalid( $field, $message ) {
		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: parameter name. */
			sprintf( __( 'Invalid parameter(s): %s', 'acme-inventory' ), $field ),
			array(
				'status' => 400,
				'params' => array( $field => $message ),
			)
		);
	}

	/**
	 * 404 error.
	 *
	 * @return \WP_Error
	 */
	public static function not_found() {
		return new \WP_Error( 'acme_inventory_not_found', __( 'Unknown item.', 'acme-inventory' ), array( 'status' => 404 ) );
	}

	/**
	 * Normalize list/export arguments.
	 *
	 * @param array $args Raw args (search, low_stock, orderby, order).
	 * @return array
	 */
	public static function query_args( array $args ) {
		$orderby = isset( $args['orderby'] ) && isset( Items::ORDERBY[ $args['orderby'] ] ) ? $args['orderby'] : 'name';
		$order   = isset( $args['order'] ) && 'desc' === strtolower( (string) $args['order'] ) ? 'DESC' : 'ASC';
		return array(
			'search'    => isset( $args['search'] ) ? trim( (string) $args['search'] ) : '',
			'low_stock' => ! empty( $args['low_stock'] ) && 'false' !== $args['low_stock'],
			'orderby'   => $orderby,
			'order'     => $order,
		);
	}

	/**
	 * Is the value an integer (int or integer string)?
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_int_like( $value ) {
		return is_int( $value ) || ( is_string( $value ) && preg_match( '/^-?\d+$/', trim( $value ) ) );
	}

	/**
	 * Validate and apply an update.
	 *
	 * @param int    $id     Item ID.
	 * @param array  $fields Any of name, stock, low_stock_threshold, location.
	 * @param string $source rest|ajax.
	 * @return object|\WP_Error Updated row.
	 */
	public static function update( $id, array $fields, $source ) {
		$item = Items::find( $id );
		if ( ! $item ) {
			return self::not_found();
		}
		$clean = array();
		if ( array_key_exists( 'name', $fields ) ) {
			$name = is_string( $fields['name'] ) ? sanitize_text_field( $fields['name'] ) : '';
			if ( '' === $name || mb_strlen( $name ) > 191 ) {
				return self::invalid( 'name', __( 'The name must be 1 to 191 characters long.', 'acme-inventory' ) );
			}
			$clean['name'] = $name;
		}
		foreach ( array( 'stock', 'low_stock_threshold' ) as $int_field ) {
			if ( array_key_exists( $int_field, $fields ) ) {
				if ( ! self::is_int_like( $fields[ $int_field ] ) || (int) $fields[ $int_field ] < 0 ) {
					return self::invalid( $int_field, __( 'Must be a whole number of at least 0.', 'acme-inventory' ) );
				}
				$clean[ $int_field ] = (int) $fields[ $int_field ];
			}
		}
		if ( array_key_exists( 'location', $fields ) ) {
			$location = is_string( $fields['location'] ) ? sanitize_text_field( $fields['location'] ) : null;
			if ( null === $location || mb_strlen( $location ) > 100 ) {
				return self::invalid( 'location', __( 'The location must be at most 100 characters long.', 'acme-inventory' ) );
			}
			$clean['location'] = $location;
		}

		$other = array_diff_key( $clean, array( 'stock' => true ) );
		if ( $other && ! Items::update( $id, $other ) ) {
			return new \WP_Error( 'acme_inventory_db_error', __( 'Could not save the item.', 'acme-inventory' ), array( 'status' => 500 ) );
		}
		if ( isset( $clean['stock'] ) && (int) $item->stock !== $clean['stock'] ) {
			$reason = 'rest' === $source ? __( 'Edited via API', 'acme-inventory' ) : __( 'Inline edit', 'acme-inventory' );
			Items::set_stock( $id, $clean['stock'], $reason, $source );
		}
		return Items::find( $id );
	}

	/**
	 * All-or-nothing bulk adjustment.
	 *
	 * @param mixed  $ids    Item IDs.
	 * @param mixed  $delta  Non-zero integer.
	 * @param string $reason Reason.
	 * @param string $source rest|ajax.
	 * @return object[]|\WP_Error Updated rows.
	 */
	public static function bulk_adjust( $ids, $delta, $reason, $source ) {
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > self::MAX_BULK ) {
			return self::invalid( 'ids', __( 'Select between 1 and 100 items.', 'acme-inventory' ) );
		}
		$clean_ids = array();
		foreach ( $ids as $id ) {
			if ( ! self::is_int_like( $id ) || (int) $id <= 0 ) {
				return self::invalid( 'ids', __( 'Invalid item ID.', 'acme-inventory' ) );
			}
			$clean_ids[ (int) $id ] = (int) $id;
		}
		if ( ! self::is_int_like( $delta ) || 0 === (int) $delta ) {
			return self::invalid( 'delta', __( 'The adjustment must be a non-zero whole number.', 'acme-inventory' ) );
		}
		$delta = (int) $delta;

		// Validate everything before changing anything.
		$items = array();
		foreach ( $clean_ids as $id ) {
			$item = Items::find( $id );
			if ( ! $item ) {
				/* translators: %d: item ID. */
				return self::invalid( 'ids', sprintf( __( 'Unknown item %d.', 'acme-inventory' ), $id ) );
			}
			if ( (int) $item->stock + $delta < 0 ) {
				/* translators: %s: SKU. */
				return self::invalid( 'delta', sprintf( __( 'The stock of %s would drop below 0.', 'acme-inventory' ), $item->sku ) );
			}
			$items[ $id ] = $item;
		}

		$reason  = sanitize_text_field( (string) $reason );
		$updated = array();
		foreach ( $items as $id => $item ) {
			Items::set_stock( $id, (int) $item->stock + $delta, $reason, $source );
			$updated[] = Items::find( $id );
		}
		return $updated;
	}

	/**
	 * Delete.
	 *
	 * @param int $id Item ID.
	 * @return object|\WP_Error The deleted row.
	 */
	public static function delete( $id ) {
		$item = Items::find( $id );
		if ( ! $item ) {
			return self::not_found();
		}
		if ( ! Items::delete( $id ) ) {
			return new \WP_Error( 'acme_inventory_db_error', __( 'Could not delete the item.', 'acme-inventory' ), array( 'status' => 500 ) );
		}
		return $item;
	}

	/**
	 * CSV of all items matching the args.
	 *
	 * @param array $args See query_args().
	 * @return string
	 */
	public static function export_csv( array $args ) {
		return Csv::build( Items::query( self::query_args( $args ) + array( 'limit' => -1 ) ) );
	}
}
