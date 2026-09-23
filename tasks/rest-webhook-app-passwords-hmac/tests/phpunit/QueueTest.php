<?php
/**
 * Duplicates, background processing, retries and the status endpoint (in-process).
 */

class QueueTest extends AcmeOrdersCase {

	const STATUS_KEYS = array( 'attempts', 'event_id', 'last_error', 'next_attempt_at', 'order_id', 'processed_at', 'received_at', 'source', 'status', 'type' );

	public function test_cron_applies_the_event(): void {
		$this->clear_mails();
		$before = time();
		$event  = $this->event(
			'order.created',
			array(
				'number'   => 'EU-60001',
				'total'    => '129,90',
				'currency' => 'eur',
				'email'    => 'erika@customer.example',
			)
		);
		$this->assertAccepted( $this->deliver_signed( $event ), $event['id'] );

		$status = $this->delivery_status( $event['id'] );
		$keys   = array_keys( $status );
		sort( $keys );
		$this->assertSame( self::STATUS_KEYS, $keys, 'status endpoint keys' );
		$this->assertSame( $event['id'], $status['event_id'] );
		$this->assertSame( 'shop-eu', $status['source'] );
		$this->assertSame( 'order.created', $status['type'] );
		$this->assertSame( 'queued', $status['status'] );
		$this->assertSame( 0, $status['attempts'] );
		$received = $this->assertIsoTime( $status['received_at'], 'received_at' );
		$this->assertGreaterThanOrEqual( $before - 2, $received );
		$this->assertLessThanOrEqual( time() + 2, $received );
		$this->assertNull( $status['processed_at'] );
		$this->assertNull( $status['next_attempt_at'] );
		$this->assertNull( $status['last_error'] );
		$this->assertNull( $status['order_id'] );

		$this->assertGreaterThan( 0, $this->run_cron(), 'a due cron event must exist right after the delivery was accepted' );

		$post_id = $this->order_post( 'shop-eu', 'EU-60001' );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( 'paid', get_post_meta( $post_id, '_acme_order_status', true ) );
		$this->assertSame( 12990, (int) get_post_meta( $post_id, '_acme_order_total', true ) );
		$this->assertSame( 'EUR', get_post_meta( $post_id, '_acme_order_currency', true ) );
		$this->assertSame( 'erika@customer.example', get_post_meta( $post_id, '_acme_order_email', true ) );
		$this->assertSame( 1, $this->synced[ $event['id'] ] ?? 0 );
		$this->assertCount( 1, $this->pick_mails( 'EU-60001' ) );

		$status = $this->delivery_status( $event['id'] );
		$this->assertSame( 'processed', $status['status'] );
		$this->assertSame( 1, $status['attempts'] );
		$this->assertSame( $post_id, $status['order_id'] );
		$this->assertGreaterThanOrEqual( $received, $this->assertIsoTime( $status['processed_at'], 'processed_at' ) );
		$this->assertNull( $status['next_attempt_at'] );
	}

	public function test_duplicates_get_the_original_response_and_are_applied_once(): void {
		$this->clear_mails();
		$event = $this->event( 'order.created', array( 'number' => 'EU-60002' ) );
		$first = $this->deliver_signed( $event );
		$this->assertAccepted( $first, $event['id'] );
		$this->assertNull( $this->header( $first, 'X-Acme-Duplicate' ) );
		$original = $this->rest_data( $first );

		$again = $this->deliver_signed( $event );
		$this->assertSame( 202, $again->get_status() );
		$this->assertSame( $original, $this->rest_data( $again ), 'duplicate must get exactly the original body' );
		$this->assertSame( 'true', $this->header( $again, 'X-Acme-Duplicate' ) );

		$this->run_cron();
		$this->assertSame( 1, $this->synced[ $event['id'] ] ?? 0 );

		// The shop retries again after we processed it (fresh timestamp + signature).
		sleep( 1 );
		$late = $this->deliver_signed( $event );
		$this->assertSame( 202, $late->get_status() );
		$this->assertSame( $original, $this->rest_data( $late ) );
		$this->assertSame( 'true', $this->header( $late, 'X-Acme-Duplicate' ) );
		$this->run_cron();

		$this->assertSame( 1, $this->synced[ $event['id'] ], 'applied exactly once' );
		$this->assertCount( 1, $this->pick_mails( 'EU-60002' ), 'one pick mail' );
		$status = $this->delivery_status( $event['id'] );
		$this->assertSame( 'processed', $status['status'] );
		$this->assertSame( 1, $status['attempts'] );
	}

	public function test_same_event_id_with_different_body_is_a_conflict(): void {
		$event = $this->event( 'order.created', array( 'number' => 'EU-60003' ) );
		$this->assertAccepted( $this->deliver_signed( $event ), $event['id'] );

		$forged                            = $event;
		$forged['data']['order']['total']  = 0.01;
		$forged['data']['order']['status'] = 'refunded';
		$this->assertRejected( $this->deliver_signed( $forged ), 409, 'acme_webhook_conflict' );

		$this->run_cron();
		$post_id = $this->order_post( 'shop-eu', 'EU-60003' );
		$this->assertSame( 4250, (int) get_post_meta( $post_id, '_acme_order_total', true ) );
		$this->assertSame( 'paid', get_post_meta( $post_id, '_acme_order_status', true ) );
		$this->assertSame( 1, $this->synced[ $event['id'] ] ?? 0 );
	}

	public function test_events_are_applied_in_the_order_received(): void {
		$created   = $this->event( 'order.created', array( 'number' => 'EU-60004', 'status' => 'paid' ) );
		$other     = $this->event( 'order.created', array( 'number' => 'US-60004', 'currency' => 'USD' ) );
		$updated   = $this->event( 'order.updated', array( 'number' => 'EU-60004', 'status' => 'shipped' ) );
		$refunded  = $this->event( 'order.refunded', array( 'number' => 'EU-60004', 'status' => 'shipped', 'refund' => array( 'amount' => 5 ) ) );
		$refunded2 = $this->event( 'order.refunded', array( 'number' => 'EU-60004', 'status' => 'shipped', 'refund' => array( 'amount' => 2.5 ) ) );

		$this->assertAccepted( $this->deliver_signed( $created ), $created['id'] );
		$this->assertAccepted( $this->deliver_signed( $other, 'shop-us' ), $other['id'] );
		$this->assertAccepted( $this->deliver_signed( $updated ), $updated['id'] );
		$this->run_cron();

		$post_id = $this->order_post( 'shop-eu', 'EU-60004' );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( 'shipped', get_post_meta( $post_id, '_acme_order_status', true ), 'order.updated must win over the earlier order.created' );
		$this->assertGreaterThan( 0, $this->order_post( 'shop-us', 'US-60004' ) );

		$this->assertAccepted( $this->deliver_signed( $refunded ), $refunded['id'] );
		$this->assertAccepted( $this->deliver_signed( $refunded2 ), $refunded2['id'] );
		$this->run_cron();
		$this->assertSame( 'refunded', get_post_meta( $post_id, '_acme_order_status', true ) );
		$this->assertSame( 750, (int) get_post_meta( $post_id, '_acme_order_refunded', true ) );
		foreach ( array( $created, $updated, $refunded, $refunded2 ) as $e ) {
			$this->assertSame( 1, $this->synced[ $e['id'] ] ?? 0, $e['id'] );
			$this->assertSame( $post_id, $this->delivery_status( $e['id'] )['order_id'] );
		}
	}

	/** Makes processing of order numbers containing $needle fail with a WP_Error. */
	private function fail_orders( string $needle ): void {
		add_filter(
			'acme_orders_pre_sync_order',
			static fn( $order, $event ) => str_contains( (string) ( $event['data']['order']['number'] ?? '' ), $needle )
				? new WP_Error( 'erp_down', 'ERP maintenance window' )
				: $order,
			10,
			2
		);
	}

	/** Runs cron (with pauses) until the event reaches $status or $max rounds passed. */
	private function cron_until( string $event_id, string $status, int $max = 12 ): array {
		for ( $i = 0; $i < $max; $i++ ) {
			$this->run_cron();
			$current = $this->delivery_status( $event_id );
			if ( $status === $current['status'] ) {
				return $current;
			}
			sleep( 1 );
		}
		return $current;
	}

	public function test_failed_processing_is_retried_after_60_seconds(): void {
		$this->fail_orders( 'EU-6100' );
		$delays = array();
		add_filter(
			'acme_orders_retry_delay',
			static function ( $seconds, $attempt, $event_id ) use ( &$delays ) {
				$delays[] = array( $seconds, $attempt, $event_id );
				return $seconds;
			},
			10,
			3
		);

		$event = $this->event( 'order.created', array( 'number' => 'EU-61001' ) );
		$this->assertAccepted( $this->deliver_signed( $event ), $event['id'] );
		$this->run_cron();
		$failed_at = time();

		$status = $this->delivery_status( $event['id'] );
		$this->assertSame( 'retrying', $status['status'] );
		$this->assertSame( 1, $status['attempts'] );
		$this->assertStringContainsString( 'ERP maintenance window', (string) $status['last_error'] );
		$this->assertNull( $status['order_id'] );
		$this->assertNull( $status['processed_at'] );
		$next = $this->assertIsoTime( $status['next_attempt_at'], 'next_attempt_at' );
		$this->assertGreaterThanOrEqual( $failed_at + 55, $next, 'first retry after 60 s' );
		$this->assertLessThanOrEqual( $failed_at + 62, $next, 'first retry after 60 s' );
		$this->assertSame( array( array( 60, 1, $event['id'] ) ), $delays, 'acme_orders_retry_delay arguments' );

		// Not due yet: cron runs now must not attempt it again.
		$this->run_cron();
		sleep( 1 );
		$this->run_cron();
		$status = $this->delivery_status( $event['id'] );
		$this->assertSame( 1, $status['attempts'], 'attempt ran before it was due' );
		$this->assertSame( 'retrying', $status['status'] );
		$this->assertSame( array(), $this->synced );
	}

	public function test_backoff_sequence_and_giving_up_after_five_attempts(): void {
		$this->fail_orders( 'EU-6101' );
		$delays = array();
		add_filter(
			'acme_orders_retry_delay',
			static function ( $seconds, $attempt, $event_id ) use ( &$delays ) {
				$delays[] = array( $seconds, $attempt, $event_id );
				return 1; // Ops: retry quickly.
			},
			10,
			3
		);

		$event = $this->event( 'order.created', array( 'number' => 'EU-61011' ) );
		$ok    = $this->event( 'order.created', array( 'number' => 'EU-62011' ) );
		$this->assertAccepted( $this->deliver_signed( $event ), $event['id'] );
		$this->assertAccepted( $this->deliver_signed( $ok ), $ok['id'] );

		$status = $this->cron_until( $event['id'], 'failed' );
		$this->assertSame( 'failed', $status['status'] );
		$this->assertSame( 5, $status['attempts'] );
		$this->assertNull( $status['next_attempt_at'] );
		$this->assertNull( $status['order_id'] );
		$this->assertStringContainsString( 'ERP maintenance window', (string) $status['last_error'] );

		$attempts = array_map( static fn( $d ) => array( $d[0], $d[1], $d[2] ), array_slice( $delays, 0, 4 ) );
		$this->assertSame(
			array( array( 60, 1, $event['id'] ), array( 120, 2, $event['id'] ), array( 240, 3, $event['id'] ), array( 480, 4, $event['id'] ) ),
			$attempts,
			'backoff 60 * 2^(n-1)'
		);

		// A failing event doesn't block the others for good.
		$this->run_cron();
		$this->assertSame( 'processed', $this->delivery_status( $ok['id'] )['status'] );

		// Given up: never attempted again.
		sleep( 2 );
		$this->run_cron( 7 * DAY_IN_SECONDS );
		$this->assertSame( 5, $this->delivery_status( $event['id'] )['attempts'] );
		$this->assertSame( 'failed', $this->delivery_status( $event['id'] )['status'] );
		$this->assertSame( 0, $this->order_post( 'shop-eu', 'EU-61011' ) );
		$this->assertArrayNotHasKey( $event['id'], $this->synced );
	}

	public function test_exception_during_processing_is_retried_and_later_succeeds(): void {
		$this->clear_mails();
		$throws  = 1;
		$thrower = static function ( $post_id, $order ) use ( &$throws ) {
			if ( 'EU-61002' === $order['number'] && $throws > 0 ) {
				--$throws;
				throw new RuntimeException( 'ERP connection reset' );
			}
		};
		add_action( 'acme_orders_order_synced', $thrower, 5, 2 );
		add_filter( 'acme_orders_retry_delay', static fn() => 1 );

		$event = $this->event( 'order.created', array( 'number' => 'EU-61002' ) );
		$this->assertAccepted( $this->deliver_signed( $event ), $event['id'] );
		$this->run_cron( 0, 1 );
		$status = $this->delivery_status( $event['id'] );
		$this->assertSame( 'retrying', $status['status'] );
		$this->assertSame( 1, $status['attempts'] );
		$this->assertStringContainsString( 'ERP connection reset', (string) $status['last_error'] );

		$status = $this->cron_until( $event['id'], 'processed' );
		remove_action( 'acme_orders_order_synced', $thrower, 5 );

		$this->assertSame( 'processed', $status['status'] );
		$this->assertSame( 2, $status['attempts'] );
		$this->assertSame( $this->order_post( 'shop-eu', 'EU-61002' ), $status['order_id'] );
		$this->assertCount( 1, $this->pick_mails( 'EU-61002' ) );
	}

	public function test_status_endpoint_for_unknown_event_and_permissions(): void {
		$this->assertNull( $this->delivery_status( 'evt_does_not_exist' ) );

		$event = $this->event();
		$this->assertAccepted( $this->deliver_signed( $event ), $event['id'] );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', '/acme-orders/v1/deliveries/' . $event['id'] )->get_status() );
		$this->login_as( 'editor' );
		$this->assertSame( 403, $this->rest( 'GET', '/acme-orders/v1/deliveries/' . $event['id'] )->get_status() );
		$this->login_as( 'administrator' );
		$this->assertSame( 200, $this->rest( 'GET', '/acme-orders/v1/deliveries/' . $event['id'] )->get_status() );
	}
}
