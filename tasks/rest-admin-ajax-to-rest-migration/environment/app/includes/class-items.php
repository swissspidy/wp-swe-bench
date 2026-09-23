<?php
/**
 * Inventory items: data access, stock changes and the adjustment log.
 *
 * @package Acme\Inventory
 */

namespace Acme\Inventory;

defined( 'ABSPATH' ) || exit;

/**
 * Items.
 */
class Items {

	/** Sortable columns (public name => column). */
	const ORDERBY = array(
		'name'    => 'name',
		'sku'     => 'sku',
		'stock'   => 'stock',
		'updated' => 'updated_at',
	);

	/**
	 * Items table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_inventory_items';
	}

	/**
	 * Adjustment log table.
	 *
	 * @return string
	 */
	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_inventory_log';
	}

	/**
	 * One item.
	 *
	 * @param int $id Item ID.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ? $row : null;
	}

	/**
	 * WHERE clause.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	protected static function where( array $args ) {
		global $wpdb;
		$where = array( '1=1' );
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = $wpdb->prepare( '(name LIKE %s OR sku LIKE %s)', $like, $like );
		}
		if ( ! empty( $args['low_stock'] ) ) {
			$where[] = 'stock <= low_stock_threshold';
		}
		if ( ! empty( $args['include'] ) ) {
			$where[] = 'id IN (' . implode( ',', array_map( 'absint', (array) $args['include'] ) ) . ')';
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Query items.
	 *
	 * @param array $args {
	 *     @type string $search    Substring of SKU or name.
	 *     @type bool   $low_stock Only items at or below their threshold.
	 *     @type string $orderby   name|sku|stock|updated. Default name.
	 *     @type string $order     ASC|DESC. Default ASC.
	 *     @type int    $limit     Default 20. -1 for all.
	 *     @type int    $offset    Default 0.
	 * }
	 * @return object[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args    = wp_parse_args(
			$args,
			array(
				'orderby' => 'name',
				'order'   => 'ASC',
				'limit'   => 20,
				'offset'  => 0,
			)
		);
		$orderby = isset( self::ORDERBY[ $args['orderby'] ] ) ? self::ORDERBY[ $args['orderby'] ] : 'name';
		$order   = 'DESC' === strtoupper( (string) $args['order'] ) ? 'DESC' : 'ASC';
		$table   = self::table();
		$sql     = "SELECT * FROM {$table} WHERE " . self::where( $args ) . " ORDER BY {$orderby} {$order}, id ASC";
		if ( (int) $args['limit'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], max( 0, (int) $args['offset'] ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Count items.
	 *
	 * @param array $args See query().
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE " . self::where( $args ) );
	}

	/**
	 * Set the stock of an item (logs the change, fires acme_inventory_stock_changed).
	 *
	 * @param int    $id     Item ID.
	 * @param int    $stock  New stock.
	 * @param string $reason Reason for the log.
	 * @param string $source ajax|rest|cli|import.
	 * @return bool
	 */
	public static function set_stock( $id, $stock, $reason = '', $source = 'ajax' ) {
		global $wpdb;
		$item = self::find( $id );
		if ( ! $item ) {
			return false;
		}
		$old   = (int) $item->stock;
		$stock = (int) $stock;
		$ok    = false !== $wpdb->update(
			self::table(),
			array(
				'stock'      => $stock,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_by' => get_current_user_id(),
			),
			array( 'id' => (int) $id )
		);
		if ( ! $ok ) {
			return false;
		}
		$wpdb->insert(
			self::log_table(),
			array(
				'item_id'     => (int) $id,
				'delta'       => $stock - $old,
				'stock_after' => $stock,
				'reason'      => substr( (string) $reason, 0, 191 ),
				'source'      => $source,
				'user_id'     => get_current_user_id(),
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			)
		);

		/**
		 * Fires after the stock of an item changed.
		 *
		 * @param int    $id     Item ID.
		 * @param int    $old    Previous stock.
		 * @param int    $stock  New stock.
		 * @param string $source Where the change came from.
		 */
		do_action( 'acme_inventory_stock_changed', (int) $id, $old, $stock, $source );
		return true;
	}

	/**
	 * Adjust the stock of an item by a delta.
	 *
	 * @param int    $id     Item ID.
	 * @param int    $delta  Change.
	 * @param string $reason Reason.
	 * @param string $source Source.
	 * @return bool
	 */
	public static function adjust( $id, $delta, $reason = '', $source = 'ajax' ) {
		$item = self::find( $id );
		if ( ! $item ) {
			return false;
		}
		return self::set_stock( $id, (int) $item->stock + (int) $delta, $reason, $source );
	}

	/**
	 * Update non-stock fields.
	 *
	 * @param int   $id     Item ID.
	 * @param array $fields name|location|low_stock_threshold.
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$fields = array_intersect_key( $fields, array_flip( array( 'name', 'location', 'low_stock_threshold' ) ) );
		if ( ! $fields ) {
			return true;
		}
		$fields['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$fields['updated_by'] = get_current_user_id();
		return false !== $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) );
	}

	/**
	 * Delete an item (its log stays for the audit trail).
	 *
	 * @param int $id Item ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$item = self::find( $id );
		if ( ! $item ) {
			return false;
		}
		$ok = (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
		if ( $ok ) {
			/**
			 * Fires after an item was deleted.
			 *
			 * @param int    $id   Item ID.
			 * @param object $item The deleted row.
			 */
			do_action( 'acme_inventory_item_deleted', (int) $id, $item );
		}
		return $ok;
	}
}
