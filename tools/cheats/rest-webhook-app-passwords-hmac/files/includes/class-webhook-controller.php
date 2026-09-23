<?php
/**
 * REST endpoints the Acme Shop talks to.
 *
 *  - GET  /acme-orders/v1/ping     Connectivity check from the shop's webhook settings screen.
 *  - POST /acme-orders/v1/webhook  Event deliveries (signed, or sent by an admin with an application password).
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
	 * Processor (validation only: events are applied by the queue).
	 *
	 * @var Order_Processor
	 */
	private $processor;

	/**
	 * Store.
	 *
	 * @var Delivery_Store
	 */
	private $store;

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Rate limiter.
	 *
	 * @var Rate_Limiter
	 */
	private $limiter;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings  Settings.
	 * @param Logger          $logger    Logger.
	 * @param Order_Processor $processor Processor.
	 * @param Delivery_Store  $store     Store.
	 * @param Queue           $queue     Queue.
	 * @param Rate_Limiter    $limiter   Rate limiter.
	 */
	public function __construct( Settings $settings, Logger $logger, Order_Processor $processor, Delivery_Store $store, Queue $queue, Rate_Limiter $limiter ) {
		$this->settings  = $settings;
		$this->logger    = $logger;
		$this->processor = $processor;
		$this->store     = $store;
		$this->queue     = $queue;
		$this->limiter   = $limiter;
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
				'permission_callback' => array( $this, 'authenticate' ),
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
	 * Permission callback of POST /webhook: a valid signature, or an administrator's
	 * application password (cookie authentication doesn't count).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function authenticate( WP_REST_Request $request ) {
		$source    = (string) $request->get_header( 'x_acme_source' );
		$signature = (string) $request->get_header( 'x_acme_signature' );
		$config    = '' === $source ? null : $this->settings->source( $source );

		if ( '' === $signature ) {
			if ( is_user_logged_in() ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return $this->reject( $request, 'acme_webhook_forbidden', __( 'Sorry, you are not allowed to send order events.', 'acme-orders-sync' ), 403 );
				}
				if ( null === $config ) {
					return $this->reject( $request, 'acme_webhook_unauthorized', __( 'Unknown source.', 'acme-orders-sync' ), 401 );
				}
				$request->set_param( '_acme_auth', 'application-password' );
				return true;
			}
			return $this->reject( $request, 'acme_webhook_unauthorized', __( 'The delivery is not signed.', 'acme-orders-sync' ), 401 );
		}

		$timestamp = trim( (string) $request->get_header( 'x_acme_timestamp' ) );
		if ( null === $config || ! Signature::is_valid_timestamp( $timestamp ) || ! Signature::verify( $signature, $timestamp, $request->get_body(), $config['secret'] ) ) {
			return $this->reject( $request, 'acme_webhook_unauthorized', __( 'Invalid webhook signature.', 'acme-orders-sync' ), 401 );
		}
		if ( ! Signature::is_fresh( $timestamp, time() ) ) {
			return $this->reject( $request, 'acme_webhook_expired', __( 'The delivery timestamp is too old or too far in the future.', 'acme-orders-sync' ), 401 );
		}

		$request->set_param( '_acme_auth', 'signature' );
		return true;
	}

	/**
	 * POST /webhook: stores and queues an authenticated event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive( WP_REST_Request $request ) {
		$source = (string) $request->get_header( 'x_acme_source' );
		$body   = $request->get_body();
		$event  = json_decode( $body, true );

		$valid = $this->processor->validate( $event );
		if ( is_wp_error( $valid ) ) {
			return $this->reject( $request, 'acme_webhook_invalid_event', $valid->get_error_message(), 400 );
		}

		$event_id = (string) $event['id'];
		if ( strlen( $event_id ) > 191 ) {
			return $this->reject( $request, 'acme_webhook_invalid_event', __( 'The event ID is too long.', 'acme-orders-sync' ), 400 );
		}
		$hash = hash( 'sha256', (string) wp_json_encode( $event ) );

		$existing = $this->store->find( $event_id );
		if ( $existing ) {
			return $this->replay( $request, $existing, $hash );
		}

		$retry_after = $this->limiter->retry_after( $source );
		if ( $retry_after > 0 ) {
			$error = $this->reject( $request, 'acme_webhook_rate_limited', __( 'Too many deliveries from this source. Try again later.', 'acme-orders-sync' ), 429 );
			$response = rest_convert_error_to_response( $error );
			$response->header( 'Retry-After', (string) $retry_after );
			return $response;
		}

		$data     = array(
			'event_id' => $event_id,
			'status'   => Delivery_Store::STATUS_QUEUED,
		);
		$inserted = $this->store->insert(
			array(
				'event_id'    => $event_id,
				'source'      => $source,
				'type'        => (string) $event['type'],
				'body_hash'   => $hash,
				'payload'     => (string) wp_json_encode( $this->logger->redactor()->redact( $event ) ),
				'status'      => Delivery_Store::STATUS_QUEUED,
				'attempts'    => 0,
				'received_at' => time(),
				'response'    => (string) wp_json_encode(
					array(
						'status' => 202,
						'data'   => $data,
					)
				),
			)
		);
		if ( ! $inserted ) {
			// Lost a race against a concurrent delivery of the same event.
			$existing = $this->store->find( $event_id );
			if ( $existing ) {
				return $this->replay( $request, $existing, $hash );
			}
			return new WP_Error( 'acme_webhook_storage_failed', __( 'Could not store the event.', 'acme-orders-sync' ), array( 'status' => 500 ) );
		}

		$this->limiter->hit( $source );
		$this->queue->schedule( time() );

		$this->logger->info(
			'Webhook accepted',
			array(
				'event'  => $event_id,
				'type'   => $event['type'],
				'source' => $source,
				'auth'   => $request->get_param( '_acme_auth' ),
				'result' => 202,
			)
		);

		return new WP_REST_Response( $data, 202 );
	}

	/**
	 * Answers a re-delivery of a known event.
	 *
	 * @param WP_REST_Request $request  Request.
	 * @param object          $existing Stored delivery.
	 * @param string          $hash     Hash of the new body.
	 * @return WP_REST_Response|WP_Error
	 */
	private function replay( WP_REST_Request $request, $existing, string $hash ) {
		if ( ! hash_equals( (string) $existing->body_hash, $hash ) ) {
			return $this->reject( $request, 'acme_webhook_conflict', __( 'An event with this ID but different content was already received.', 'acme-orders-sync' ), 409 );
		}
		$original = json_decode( (string) $existing->response, true );
		$response = new WP_REST_Response( $original['data'] ?? array(), (int) ( $original['status'] ?? 202 ) );
		$response->header( 'X-Acme-Duplicate', 'true' );

		$this->logger->info(
			'Duplicate delivery answered with the original response',
			array(
				'event'  => $existing->event_id,
				'source' => (string) $request->get_header( 'x_acme_source' ),
				'result' => $response->get_status(),
			)
		);
		return $response;
	}

	/**
	 * Logs a rejected delivery and returns the error.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $code    Error code.
	 * @param string          $message Message.
	 * @param int             $status  HTTP status.
	 */
	private function reject( WP_REST_Request $request, string $code, string $message, int $status ): WP_Error {
		$event = json_decode( $request->get_body(), true );
		$this->logger->error(
			'Webhook rejected',
			array(
				'event'   => is_array( $event ) && isset( $event['id'] ) && is_scalar( $event['id'] ) ? substr( (string) $event['id'], 0, 191 ) : null,
				'source'  => substr( (string) $request->get_header( 'x_acme_source' ), 0, 64 ),
				'result'  => $status,
				'reason'  => $code,
				'ip'      => $this->client_ip(),
				'headers' => $request->get_headers(),
			)
		);
		return new WP_Error( $code, $message, array( 'status' => $status ) );
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
