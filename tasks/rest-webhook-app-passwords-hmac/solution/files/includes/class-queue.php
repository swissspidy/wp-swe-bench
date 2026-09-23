<?php
/**
 * Background processing of accepted events (WP-Cron), with retries and backoff.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Queue.
 */
class Queue {

	const HOOK         = 'acme_orders_process_queue';
	const MAX_ATTEMPTS = 5;
	const BASE_DELAY   = 60;
	const BATCH        = 50;

	/**
	 * Store.
	 *
	 * @var Delivery_Store
	 */
	private $store;

	/**
	 * Processor.
	 *
	 * @var Order_Processor
	 */
	private $processor;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Delivery_Store  $store     Store.
	 * @param Order_Processor $processor Processor.
	 * @param Logger          $logger    Logger.
	 */
	public function __construct( Delivery_Store $store, Order_Processor $processor, Logger $logger ) {
		$this->store     = $store;
		$this->processor = $processor;
		$this->logger    = $logger;
	}

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Makes sure a cron run happens at $when (or earlier).
	 *
	 * @param int $when Timestamp.
	 */
	public function schedule( int $when ): void {
		$next = wp_next_scheduled( self::HOOK );
		if ( false !== $next && $next <= $when ) {
			return;
		}
		if ( false !== $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
		wp_schedule_single_event( $when, self::HOOK );
	}

	/**
	 * Cron callback: processes due deliveries in the order they were received.
	 */
	public function run(): void {
		foreach ( $this->store->due( time(), self::BATCH ) as $row ) {
			$this->process( $row );
		}
		$next = $this->store->next_due();
		if ( null !== $next ) {
			$this->schedule( max( time(), $next ) );
		}
	}

	/**
	 * One attempt.
	 *
	 * @param object $row Delivery row.
	 */
	private function process( $row ): void {
		if ( ! $this->store->claim( (int) $row->id ) ) {
			return;
		}
		$attempt = (int) $row->attempts + 1;
		$event   = json_decode( (string) $row->payload, true );

		try {
			$result = $this->processor->process( is_array( $event ) ? $event : array(), (string) $row->source );
		} catch ( \Throwable $e ) {
			$result = new WP_Error( 'acme_orders_exception', $e->getMessage() );
		}

		$context = array(
			'event'   => $row->event_id,
			'source'  => $row->source,
			'attempt' => $attempt,
		);

		if ( ! is_wp_error( $result ) ) {
			$this->store->update(
				(int) $row->id,
				array(
					'status'          => Delivery_Store::STATUS_PROCESSED,
					'processed_at'    => time(),
					'next_attempt_at' => null,
					'order_id'        => (int) $result,
				)
			);
			$this->logger->info( 'Event processed', $context + array( 'post_id' => (int) $result ) );
			return;
		}

		$message = $result->get_error_message();
		if ( $attempt >= self::MAX_ATTEMPTS ) {
			$this->store->update(
				(int) $row->id,
				array(
					'status'          => Delivery_Store::STATUS_FAILED,
					'next_attempt_at' => null,
					'last_error'      => $this->logger->redactor()->scrub( $message ),
				)
			);
			$this->logger->error( 'Event failed, giving up', $context + array( 'error' => $message ) );
			return;
		}

		/**
		 * Filters the delay before the next attempt of a failed event.
		 *
		 * @param int    $seconds  Delay in seconds (60 × 2^(attempt − 1)).
		 * @param int    $attempt  Number of failed attempts so far.
		 * @param string $event_id Event ID.
		 */
		$delay = (int) apply_filters( 'acme_orders_retry_delay', self::BASE_DELAY * ( 2 ** ( $attempt - 1 ) ), $attempt, (string) $row->event_id );
		$next  = time() + max( 0, $delay );

		$this->store->update(
			(int) $row->id,
			array(
				'status'          => Delivery_Store::STATUS_RETRYING,
				'next_attempt_at' => $next,
				'last_error'      => $this->logger->redactor()->scrub( $message ),
			)
		);
		$this->logger->error(
			'Event failed, will retry',
			$context + array(
				'error'        => $message,
				'next_attempt' => gmdate( 'Y-m-d\TH:i:s\Z', $next ),
			)
		);
	}
}
