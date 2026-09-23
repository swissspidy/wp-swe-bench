<?php
/**
 * REST endpoints the Acme Shop talks to.
 *
 *  - GET  /acme-orders/v1/ping     Connectivity check from the shop's webhook settings screen.
 *  - POST /acme-orders/v1/webhook  Event deliveries.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Webhook controller.
 */
class Webhook_Controller {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Processor.
	 *
	 * @var Order_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings  Settings.
	 * @param Logger          $logger    Logger.
	 * @param Order_Processor $processor Processor.
	 */
	public function __construct( Settings $settings, Logger $logger, Order_Processor $processor ) {
		$this->settings  = $settings;
		$this->logger    = $logger;
		$this->processor = $processor;
	}

	/**
	 * Registers the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			REST_NAMESPACE,
			'/ping',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'ping' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			REST_NAMESPACE,
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive' ),
				// @todo ACME-212: verify the shop's signature. The shop only started signing in API v2.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /ping.
	 */
	public function ping(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'plugin'  => 'acme-orders-sync',
				'version' => VERSION,
			)
		);
	}

	/**
	 * POST /webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive( WP_REST_Request $request ) {
		$source = (string) $request->get_header( 'x_acme_source' );
		if ( '' === $source ) {
			$source = 'default';
		}
		$event = json_decode( $request->get_body(), true );

		// Log everything we got so support can reconstruct lost deliveries.
		$this->logger->info(
			'Webhook received',
			array(
				'source'  => $source,
				'headers' => $request->get_headers(),
				'body'    => $event,
				'ip'      => $this->client_ip(),
			)
		);

		$config = $this->settings->source( $source );
		if ( null === $config ) {
			$this->logger->error( 'Unknown source', array( 'source' => $source ) );
			return new WP_Error( 'acme_orders_unknown_source', __( 'Unknown source.', 'acme-orders-sync' ), array( 'status' => 400 ) );
		}

		// API v1 shops sent the shared secret in a header. Kept for diagnostics only.
		$token = (string) $request->get_header( 'x_acme_token' );
		if ( '' !== $token && ! hash_equals( $config['secret'], $token ) ) {
			$this->logger->error(
				'Token mismatch',
				array(
					'expected' => $config['secret'],
					'got'      => $token,
				)
			);
		}

		$valid = $this->processor->validate( $event );
		if ( is_wp_error( $valid ) ) {
			$this->logger->error( 'Invalid event', array( 'error' => $valid->get_error_message() ) );
			$valid->add_data( array( 'status' => 400 ) );
			return $valid;
		}

		$result = $this->processor->process( $event, $source );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'received' => true,
				'event_id' => $event['id'],
				'order_id' => $result,
			),
			200
		);
	}

	/**
	 * Best-effort client IP (we sit behind the load balancer on production).
	 */
	private function client_ip(): string {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			return trim( $parts[0] );
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		// phpcs:enable
	}
}
