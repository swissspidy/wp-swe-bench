<?php
/**
 * Applies a shop event to the local order copy.
 *
 * Supported events (Acme Shop webhook API v2):
 *
 *     {
 *       "id": "evt_01J8…",            // unique per event, stable across re-deliveries
 *       "type": "order.created",      // order.created|order.updated|order.cancelled|order.refunded
 *       "created_at": "2026-09-01T10:00:00Z",
 *       "data": { "order": { "number": "EU-10023", "status": "paid", "total": 129.5,
 *                            "currency": "EUR", "email": "…", "items": [ … ],
 *                            "payment": { … }, "refund": { "amount": 10 } } }
 *     }
 *
 * Processing is slow on production (the warehouse glue code hooked to
 * `acme_orders_order_synced` talks to the ERP), so webhook deliveries are only
 * stored by the controller and applied later by the Queue (WP-Cron).
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Order processor.
 */
class Order_Processor {

	const EVENT_TYPES = array( 'order.created', 'order.updated', 'order.cancelled', 'order.refunded' );

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Logger $logger Logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Validates the envelope of an event (not the order details).
	 *
	 * @param mixed $event Decoded event.
	 * @return true|WP_Error
	 */
	public function validate( $event ) {
		if ( ! is_array( $event ) ) {
			return new WP_Error( 'acme_orders_invalid_event', __( 'The event must be a JSON object.', 'acme-orders-sync' ) );
		}
		if ( empty( $event['id'] ) || ! is_string( $event['id'] ) ) {
			return new WP_Error( 'acme_orders_invalid_event', __( 'The event has no ID.', 'acme-orders-sync' ) );
		}
		if ( empty( $event['type'] ) || ! in_array( $event['type'], self::EVENT_TYPES, true ) ) {
			return new WP_Error( 'acme_orders_invalid_event', __( 'Unsupported event type.', 'acme-orders-sync' ) );
		}
		if ( empty( $event['data']['order']['number'] ) ) {
			return new WP_Error( 'acme_orders_invalid_event', __( 'The event has no order number.', 'acme-orders-sync' ) );
		}
		return true;
	}

	/**
	 * Processes an event.
	 *
	 * @param array  $event  Decoded event.
	 * @param string $source Source (storefront) ID.
	 * @return int|WP_Error Local order post ID.
	 */
	public function process( array $event, string $source ) {
		$valid = $this->validate( $event );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$order = $this->normalize_order( $event['data']['order'] );

		/**
		 * Filters the normalized order before it is written. Return a WP_Error to
		 * abort (the fulfilment glue code does this while the ERP is in maintenance).
		 *
		 * @param array|WP_Error $order  Normalized order.
		 * @param array          $event  The event.
		 * @param string         $source Source ID.
		 */
		$order = apply_filters( 'acme_orders_pre_sync_order', $order, $event, $source );
		if ( is_wp_error( $order ) ) {
			$this->logger->error(
				'Order sync aborted',
				array(
					'event'  => $event['id'],
					'error'  => $order->get_error_message(),
					'source' => $source,
				)
			);
			return $order;
		}

		$post_id = find_order( $source, $order['number'] );

		switch ( $event['type'] ) {
			case 'order.cancelled':
				$order['status'] = 'cancelled';
				break;
			case 'order.refunded':
				$order['status'] = 'refunded';
				break;
		}

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => Order_Post_Type::POST_TYPE,
					'post_status' => 'publish',
					/* translators: %s: shop order number. */
					'post_title'  => sprintf( __( 'Order %s', 'acme-orders-sync' ), $order['number'] ),
					'post_author' => 0,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			update_post_meta( $post_id, '_acme_order_number', $order['number'] );
			update_post_meta( $post_id, '_acme_order_source', $source );
		}

		update_post_meta( $post_id, '_acme_order_status', $order['status'] );
		update_post_meta( $post_id, '_acme_order_total', $order['total'] );
		update_post_meta( $post_id, '_acme_order_currency', $order['currency'] );
		update_post_meta( $post_id, '_acme_order_email', $order['email'] );
		update_post_meta( $post_id, '_acme_order_items', $order['items'] );
		if ( 'order.refunded' === $event['type'] ) {
			$previous = (int) get_post_meta( $post_id, '_acme_order_refunded', true );
			update_post_meta( $post_id, '_acme_order_refunded', $previous + $order['refunded'] );
		}
		update_post_meta( $post_id, '_acme_raw_payload', wp_slash( wp_json_encode( $this->logger->redactor()->redact( $event ) ) ) );

		$this->logger->info(
			'Order synced',
			array(
				'event'   => $event['id'],
				'type'    => $event['type'],
				'order'   => $order['number'],
				'post_id' => $post_id,
				'source'  => $source,
			)
		);

		/**
		 * Fires after an event was applied to the local order.
		 *
		 * @param int    $post_id Local order post ID.
		 * @param array  $order   Normalized order.
		 * @param array  $event   The event.
		 * @param string $source  Source ID.
		 */
		do_action( 'acme_orders_order_synced', $post_id, $order, $event, $source );

		return (int) $post_id;
	}

	/**
	 * Normalizes the order part of an event.
	 *
	 * @param array $raw Raw order.
	 * @return array{number: string, status: string, total: int, currency: string, email: string, items: array, refunded: int}
	 */
	private function normalize_order( array $raw ): array {
		$items = array();
		foreach ( (array) ( $raw['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$items[] = array(
				'sku'   => sanitize_text_field( (string) ( $item['sku'] ?? '' ) ),
				'name'  => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
				'qty'   => max( 1, (int) ( $item['qty'] ?? 1 ) ),
				'price' => to_minor_units( $item['price'] ?? 0 ),
			);
		}

		$status = (string) ( $raw['status'] ?? 'pending' );
		if ( ! in_array( $status, Order_Post_Type::STATUSES, true ) ) {
			$status = 'pending';
		}

		return array(
			'number'   => sanitize_text_field( (string) $raw['number'] ),
			'status'   => $status,
			'total'    => to_minor_units( $raw['total'] ?? 0 ),
			'currency' => strtoupper( substr( sanitize_text_field( (string) ( $raw['currency'] ?? 'EUR' ) ), 0, 3 ) ),
			'email'    => sanitize_email( (string) ( $raw['email'] ?? '' ) ),
			'items'    => $items,
			'refunded' => to_minor_units( $raw['refund']['amount'] ?? 0 ),
		);
	}
}
