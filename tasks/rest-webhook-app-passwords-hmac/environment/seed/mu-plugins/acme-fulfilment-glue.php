<?php
/**
 * Plugin Name: Acme fulfilment glue
 * Description: Tells the warehouse about new paid orders (IT team, do not remove).
 */

add_action(
	'acme_orders_order_synced',
	static function ( $post_id, $order, $event, $source ) {
		if ( 'order.created' !== ( $event['type'] ?? '' ) ) {
			return;
		}
		wp_mail(
			'warehouse@acme.example',
			sprintf( 'Pick order %s (%s)', $order['number'], $source ),
			sprintf( "Order %s, %d item(s), local post %d.\n", $order['number'], count( $order['items'] ), $post_id )
		);
	},
	10,
	4
);
