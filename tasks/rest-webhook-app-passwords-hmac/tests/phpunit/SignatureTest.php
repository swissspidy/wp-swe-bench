<?php
/**
 * Signed deliveries (in-process REST dispatch with raw bodies and headers).
 */

class SignatureTest extends AcmeOrdersCase {

	public function test_valid_signature_is_accepted_and_queued_but_not_applied(): void {
		$this->clear_mails();
		$event = $this->event( 'order.created', array( 'number' => 'EU-50001' ) );
		$res   = $this->deliver_signed( $event );
		$this->assertAccepted( $res, $event['id'] );

		$this->assertSame( 0, $this->order_post( 'shop-eu', 'EU-50001' ), 'the event must not be applied during the webhook request' );
		$this->assertSame( array(), $this->synced );
		$this->assertCount( 0, $this->pick_mails( 'EU-50001' ) );

		$status = $this->delivery_status( $event['id'] );
		$this->assertNotNull( $status, 'accepted event must be visible in the status endpoint' );
		$this->assertSame( 'queued', $status['status'] );
	}

	public function test_missing_or_plaintext_credentials_are_rejected(): void {
		$event = $this->event();
		$body  = self::body( $event );

		$this->assertRejected( $this->deliver( $body, array( 'X-Acme-Source' => 'shop-eu' ) ), 401, 'acme_webhook_unauthorized', 'no signature' );
		$this->assertRejected(
			$this->deliver(
				$body,
				array(
					'X-Acme-Source' => 'shop-eu',
					'X-Acme-Token'  => self::SECRETS['shop-eu'],
				)
			),
			401,
			'acme_webhook_unauthorized',
			'legacy plaintext token'
		);
		$headers = $this->signed( $body );
		unset( $headers['X-Acme-Timestamp'] );
		$this->assertRejected( $this->deliver( $body, $headers ), 401, 'acme_webhook_unauthorized', 'no timestamp' );

		$this->assertSame( array(), $this->synced );
		$this->assertNull( $this->delivery_status( $event['id'] ), 'rejected deliveries must not be stored' );
	}

	public function test_bad_signatures_are_rejected(): void {
		$event = $this->event();
		$body  = self::body( $event );
		$ts    = time();

		$cases = array(
			'wrong secret'           => array( $body, $this->signed( $body, 'shop-eu', $ts, 'not-the-secret' ) ),
			'other source secret'    => array( $body, $this->signed( $body, 'shop-eu', $ts, self::SECRETS['shop-us'] ) ),
			'tampered body'          => array( str_replace( '42.5', '0.01', $body ), $this->signed( $body, 'shop-eu', $ts ) ),
			'signature for other ts' => array( $body, array_merge( $this->signed( $body, 'shop-eu', $ts ), array( 'X-Acme-Timestamp' => (string) ( $ts - 1 ) ) ) ),
			'unknown source'         => array( $body, $this->signed( $body, 'shop-xx', $ts, self::SECRETS['shop-eu'] ) ),
			'non-integer timestamp'  => array( $body, $this->signed( $body, 'shop-eu', $ts ) ),
			'bare hex without v1'    => array( $body, array_merge( $this->signed( $body, 'shop-eu', $ts ), array( 'X-Acme-Signature' => self::signature( $body, self::SECRETS['shop-eu'], $ts ) ) ) ),
			'v0 scheme only'         => array( $body, array_merge( $this->signed( $body, 'shop-eu', $ts ), array( 'X-Acme-Signature' => 'v0=' . self::signature( $body, self::SECRETS['shop-eu'], $ts ) ) ) ),
			'truncated signature'    => array( $body, array_merge( $this->signed( $body, 'shop-eu', $ts ), array( 'X-Acme-Signature' => 'v1=' . substr( self::signature( $body, self::SECRETS['shop-eu'], $ts ), 0, 32 ) ) ) ),
		);
		$cases['non-integer timestamp'][1]['X-Acme-Timestamp'] = $ts . '.5';
		$cases['non-integer timestamp'][1]['X-Acme-Signature'] = 'v1=' . self::signature( $body, self::SECRETS['shop-eu'], 0 ) . ',v1=' . hash_hmac( 'sha256', $ts . '.5.' . $body, self::SECRETS['shop-eu'] );

		foreach ( $cases as $label => list( $raw, $headers ) ) {
			$this->assertRejected( $this->deliver( $raw, $headers ), 401, 'acme_webhook_unauthorized', $label );
		}
		$this->assertSame( array(), $this->synced );
		$this->run_cron();
		$this->assertSame( array(), $this->synced, 'nothing may be queued by rejected deliveries' );
		$this->assertNull( $this->delivery_status( $event['id'] ) );

		// The genuine delivery of the same event afterwards is accepted as new.
		$this->assertAccepted( $this->deliver( $body, $this->signed( $body ) ), $event['id'], 'genuine delivery after forged ones' );
	}

	public function test_timestamp_tolerance(): void {
		foreach ( array( -301, 301, -3600, 86400 ) as $offset ) {
			$event = $this->event();
			$body  = self::body( $event );
			$this->assertRejected( $this->deliver( $body, $this->signed( $body, 'shop-eu', time() + $offset ) ), 401, 'acme_webhook_expired', "offset $offset" );
			$this->assertNull( $this->delivery_status( $event['id'] ) );
		}
		foreach ( array( -290, 0, 290 ) as $offset ) {
			$event = $this->event();
			$body  = self::body( $event );
			$this->assertAccepted( $this->deliver( $body, $this->signed( $body, 'shop-eu', time() + $offset ) ), $event['id'], "offset $offset" );
		}
	}

	public function test_recorded_request_replayed_later_is_rejected(): void {
		$event   = $this->event();
		$body    = self::body( $event );
		$headers = $this->signed( $body, 'shop-eu', time() - 250 );
		$this->assertAccepted( $this->deliver( $body, $headers ), $event['id'] );
		$this->run_cron();
		$this->assertSame( 1, $this->synced[ $event['id'] ] ?? 0 );

		// Same captured request, but "later": outside the window.
		$old = $this->signed( $body, 'shop-eu', time() - 400 );
		$this->assertRejected( $this->deliver( $body, $old ), 401, 'acme_webhook_expired' );
		$this->run_cron();
		$this->assertSame( 1, $this->synced[ $event['id'] ], 'applied once' );
	}

	public function test_multiple_signatures_during_rotation(): void {
		$event = $this->event();
		$body  = self::body( $event );
		$ts    = time();
		$good  = self::signature( $body, self::SECRETS['shop-us'], $ts );
		$bad   = self::signature( $body, 'old-secret-being-rotated', $ts );

		$headers                     = $this->signed( $body, 'shop-us', $ts );
		$headers['X-Acme-Signature'] = "v0=deadbeef, v1=$bad,v1=$good";
		$this->assertAccepted( $this->deliver( $body, $headers ), $event['id'], 'rotation header' );

		$event2                      = $this->event();
		$body2                       = self::body( $event2 );
		$headers                     = $this->signed( $body2, 'shop-us', $ts );
		$headers['X-Acme-Signature'] = 'v1=' . self::signature( $body2, 'old-secret-being-rotated', $ts ) . ',v1=' . str_repeat( 'a', 64 );
		$this->assertRejected( $this->deliver( $body2, $headers ), 401, 'acme_webhook_unauthorized', 'no matching entry' );
	}

	public function test_legacy_default_source_uses_the_1_0_secret(): void {
		$event = $this->event( 'order.created', array( 'number' => 'D-9001' ) );
		$body  = self::body( $event );
		$this->assertRejected( $this->deliver( $body, $this->signed( $body, 'default', null, self::SECRETS['shop-eu'] ) ), 401, 'acme_webhook_unauthorized' );
		$this->assertAccepted( $this->deliver( $body, $this->signed( $body, 'default' ) ), $event['id'] );
		$this->run_cron();
		$this->assertGreaterThan( 0, $this->order_post( 'default', 'D-9001' ) );
	}

	public function test_invalid_events_are_rejected_after_authentication(): void {
		$bodies = array(
			'not an object'   => '[1,2,3]',
			'no id'           => self::body( array_diff_key( $this->event(), array( 'id' => 1 ) ) ),
			'unknown type'    => self::body( $this->event( 'order.deleted' ) ),
			'no order number' => self::body( array_merge( $this->event(), array( 'data' => array( 'order' => array( 'status' => 'paid' ) ) ) ) ),
		);
		foreach ( $bodies as $label => $body ) {
			$this->assertRejected( $this->deliver( $body, array( 'X-Acme-Source' => 'shop-eu' ) ), 401, 'acme_webhook_unauthorized', "unsigned $label" );
			$this->assertRejected( $this->deliver( $body, $this->signed( $body ) ), 400, 'acme_webhook_invalid_event', "signed $label" );
		}
		$this->run_cron();
		$this->assertSame( array(), $this->synced );
	}
}
