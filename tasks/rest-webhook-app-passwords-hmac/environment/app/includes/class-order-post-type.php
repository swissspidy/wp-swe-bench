<?php
/**
 * The local order copy (`acme_order` post type).
 *
 * Meta:
 *  - _acme_order_number   Shop order number ("EU-10023").
 *  - _acme_order_source   Source (storefront) ID.
 *  - _acme_order_status   pending|paid|shipped|cancelled|refunded.
 *  - _acme_order_total    Total in minor units (int).
 *  - _acme_order_currency ISO 4217.
 *  - _acme_order_email    Customer email.
 *  - _acme_order_items    List of {sku, name, qty, price} (price in minor units).
 *  - _acme_order_refunded Refunded amount in minor units.
 *  - _acme_raw_payload    The last event payload we processed (for support).
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Post type registration + list table columns.
 */
class Order_Post_Type {

	const POST_TYPE = 'acme_order';
	const STATUSES  = array( 'pending', 'paid', 'shipped', 'cancelled', 'refunded' );

	/**
	 * Registers the post type.
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Shop orders', 'acme-orders-sync' ),
					'singular_name' => __( 'Shop order', 'acme-orders-sync' ),
					'menu_name'     => __( 'Shop orders', 'acme-orders-sync' ),
					'all_items'     => __( 'All shop orders', 'acme-orders-sync' ),
					'edit_item'     => __( 'Shop order', 'acme-orders-sync' ),
					'search_items'  => __( 'Search shop orders', 'acme-orders-sync' ),
					'not_found'     => __( 'No shop orders yet.', 'acme-orders-sync' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-cart',
				'supports'        => array( 'title', 'custom-fields' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Admin list table hooks.
	 */
	public static function admin_hooks(): void {
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/**
	 * Columns.
	 *
	 * @param array $columns Columns.
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['acme_source'] = __( 'Storefront', 'acme-orders-sync' );
		$columns['acme_status'] = __( 'Status', 'acme-orders-sync' );
		$columns['acme_total']  = __( 'Total', 'acme-orders-sync' );
		if ( $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	/**
	 * Column output.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'acme_source':
				echo esc_html( (string) get_post_meta( $post_id, '_acme_order_source', true ) );
				break;
			case 'acme_status':
				echo esc_html( (string) get_post_meta( $post_id, '_acme_order_status', true ) );
				break;
			case 'acme_total':
				echo esc_html(
					format_money(
						(int) get_post_meta( $post_id, '_acme_order_total', true ),
						(string) get_post_meta( $post_id, '_acme_order_currency', true )
					)
				);
				break;
		}
	}
}
