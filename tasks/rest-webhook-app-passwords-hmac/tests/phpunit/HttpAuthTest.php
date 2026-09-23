<?php
/**
 * Real HTTP requests against the Playground server: signatures through the full request
 * lifecycle, application passwords vs. cookies, status endpoint permissions, rate limits.
 */

class HttpAuthTest extends AcmeOrdersCase {

	protected bool $use_transactions = false;

	private function status_http( string $event_id, array $opts ): array {
		return $this->http( 'GET', '/wp-json/acme-orders/v1/deliveries/' . rawurlencode( $event_id ), $opts );
	}

	public function test_signed_delivery_over_http_is_queued_and_applied_by_wp_cron(): void {
		$this->clear_mails();
		$event = $this->event( 'order.created', array( 'number' => 'EU-70001' ) );
		$body  = self::body( $event );
		$res   = $this->http_deliver( $body, $this->signed( $body ) );
		$this->assertSame( 202, $res['status'], $res['body'] );
		$this->assertSame( $event['id'], $res['json']['event_id'] ?? null );
		$this->assertSame( 'queued', $res['json']['status'] ?? null );

		wp_cache_flush();
		$this->assertSame( 0, $this->order_post( 'shop-eu', 'EU-70001' ), 'applied during the request' );
		$this->assertCount( 0, $this->pick_mails( 'EU-70001' ) );

		$dup = $this->http_deliver( $body, $this->signed( $body ) );
		$this->assertSame( 202, $dup['status'] );
		$this->assertSame( $res['json'], $dup['json'] );
		$this->assertSame( 'true', $dup['headers']['x-acme-duplicate'] ?? null );

		$this->cron_cli();
		$this->assertGreaterThan( 0, $this->order_post( 'shop-eu', 'EU-70001' ) );
		$this->assertCount( 1, $this->pick_mails( 'EU-70001' ) );

		$this->cron_cli();
		$this->assertCount( 1, $this->pick_mails( 'EU-70001' ) );

		$tampered = $this->http_deliver( str_replace( 'EU-70001', 'EU-70002', $body ), $this->signed( $body ) );
		$this->assertSame( 401, $tampered['status'] );
		$this->assertSame( 'acme_webhook_unauthorized', $tampered['json']['code'] ?? null );
	}

	public function test_admin_application_password_can_resend_without_signature(): void {
		$admin = $this->create_user( 'administrator' );
		$creds = $this->app_password( $admin );
		$event = $this->event( 'order.updated', array( 'number' => 'EU-70010', 'status' => 'shipped' ) );
		$body  = self::body( $event );

		$res = $this->http_deliver(
			$body,
			array(
				'X-Acme-Source' => 'shop-eu',
				'Authorization' => self::basic( $creds ),
			)
		);
		$this->assertSame( 202, $res['status'], $res['body'] );
		$this->assertSame( $event['id'], $res['json']['event_id'] ?? null );
		$this->assertSame( 'queued', $res['json']['status'] ?? null );

		wp_cache_flush();
		$this->assertSame( 0, $this->order_post( 'shop-eu', 'EU-70010' ) );

		// Status with the same application password.
		$status = $this->status_http( $event['id'], array( 'headers' => array( 'Authorization' => self::basic( $creds ) ) ) );
		$this->assertSame( 200, $status['status'], $status['body'] );
		$this->assertSame( 'queued', $status['json']['status'] );
		$this->assertSame( 'shop-eu', $status['json']['source'] );

		// Re-sending it again is a duplicate.
		$again = $this->http_deliver(
			$body,
			array(
				'X-Acme-Source' => 'shop-eu',
				'Authorization' => self::basic( $creds ),
			)
		);
		$this->assertSame( 202, $again['status'] );
		$this->assertSame( $res['json'], $again['json'] );
		$this->assertSame( 'true', $again['headers']['x-acme-duplicate'] ?? null );

		$this->cron_cli();
		$post_id = $this->order_post( 'shop-eu', 'EU-70010' );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( 'shipped', get_post_meta( $post_id, '_acme_order_status', true ) );

		$status = $this->status_http( $event['id'], array( 'headers' => array( 'Authorization' => self::basic( $creds ) ) ) );
		$this->assertSame( 'processed', $status['json']['status'] );
		$this->assertSame( $post_id, $status['json']['order_id'] );

		// Invalid events are still rejected.
		$bad = $this->http_deliver(
			self::body( $this->event( 'order.exploded' ) ),
			array(
				'X-Acme-Source' => 'shop-eu',
				'Authorization' => self::basic( $creds ),
			)
		);
		$this->assertSame( 400, $bad['status'], $bad['body'] );
		$this->assertSame( 'acme_webhook_invalid_event', $bad['json']['code'] ?? null );

		wp_delete_user( $admin );
	}

	public function test_non_admin_application_password_is_forbidden(): void {
		foreach ( array( 'editor', 'author' ) as $role ) {
			$user  = $this->create_user( $role );
			$creds = $this->app_password( $user );
			$event = $this->event();
			$res   = $this->http_deliver(
				self::body( $event ),
				array(
					'X-Acme-Source' => 'shop-eu',
					'Authorization' => self::basic( $creds ),
				)
			);
			$this->assertSame( 403, $res['status'], "$role: " . $res['body'] );
			$this->assertSame( 'acme_webhook_forbidden', $res['json']['code'] ?? null );
			$this->assertSame(
				404,
				$this->status_http(
					$event['id'],
					array(
						'login'      => $this->http_login( 1 ),
						'rest_nonce' => true,
					)
				)['status'],
				'forbidden delivery must not be stored'
			);
			wp_delete_user( $user );
		}

		// Wrong application password.
		$res = $this->http_deliver(
			self::body( $this->event() ),
			array(
				'X-Acme-Source' => 'shop-eu',
				'Authorization' => 'Basic ' . base64_encode( 'admin:abcd EFGH ijkl MNOP qrst UVWX' ),
			)
		);
		$this->assertSame( 401, $res['status'], $res['body'] );
	}

	public function test_cookie_authentication_does_not_replace_the_signature(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$event = $this->event( 'order.created', array( 'number' => 'EU-70020' ) );
		$body  = self::body( $event );

		foreach ( array( true, false ) as $nonce ) {
			$res = $this->http_deliver(
				$body,
				array( 'X-Acme-Source' => 'shop-eu' ),
				array(
					'login'      => $login,
					'rest_nonce' => $nonce,
				)
			);
			$this->assertSame( 401, $res['status'], ( $nonce ? 'with' : 'without' ) . ' nonce: ' . $res['body'] );
			$this->assertSame( 'acme_webhook_unauthorized', $res['json']['code'] ?? null );
		}

		// The same admin can read delivery statuses with cookie + nonce, but nothing was stored.
		$status = $this->status_http(
			$event['id'],
			array(
				'login'      => $login,
				'rest_nonce' => true,
			)
		);
		$this->assertSame( 404, $status['status'], $status['body'] );
		$this->cron_cli();
		$this->assertSame( 0, $this->order_post( 'shop-eu', 'EU-70020' ) );
		wp_delete_user( $admin );
	}

	public function test_status_endpoint_permissions(): void {
		$event = $this->event();
		$body  = self::body( $event );
		$this->assertSame( 202, $this->http_deliver( $body, $this->signed( $body, 'shop-us' ) )['status'] );

		$this->assertSame( 401, $this->status_http( $event['id'], array() )['status'], 'anonymous' );

		$subscriber = $this->create_user( 'subscriber' );
		$res        = $this->status_http(
			$event['id'],
			array(
				'login'      => $this->http_login( $subscriber ),
				'rest_nonce' => true,
			)
		);
		$this->assertSame( 403, $res['status'], 'subscriber' );
		$this->assertArrayNotHasKey( 'source', (array) $res['json'] );

		$editor = $this->create_user( 'editor' );
		$res    = $this->status_http( $event['id'], array( 'headers' => array( 'Authorization' => self::basic( $this->app_password( $editor ) ) ) ) );
		$this->assertSame( 403, $res['status'], 'editor application password' );

		$admin = $this->create_user( 'administrator' );
		$res   = $this->status_http(
			$event['id'],
			array(
				'login'      => $this->http_login( $admin ),
				'rest_nonce' => true,
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( $event['id'], $res['json']['event_id'] );
		$this->assertSame( 'shop-us', $res['json']['source'] );
		$this->assertSame( 'order.created', $res['json']['type'] );

		$res = $this->status_http(
			'evt_nope_' . wp_rand(),
			array(
				'login'      => $this->http_login( $admin ),
				'rest_nonce' => true,
			)
		);
		$this->assertSame( 404, $res['status'] );

		foreach ( array( $subscriber, $editor, $admin ) as $id ) {
			wp_delete_user( $id );
		}
	}

	public function test_rate_limit_per_source(): void {
		// Keep the whole burst inside one clock minute, whatever windowing is used.
		while ( (int) gmdate( 's' ) > 30 ) {
			sleep( 1 );
		}
		$source   = 'rl-' . strtolower( wp_generate_password( 6, false ) );
		$secret   = 'whsec_rl_' . wp_generate_password( 20, false );
		$settings = get_option( 'acme_orders_sync_settings' );
		$changed  = $settings;
		$changed['sources'][ $source ] = array(
			'label'  => 'Rate limit test',
			'secret' => $secret,
		);
		$changed['sources'][ $source . '-b' ] = array(
			'label'  => 'Rate limit test B',
			'secret' => $secret . '-b',
		);
		$changed['rate_limit'] = 3;
		update_option( 'acme_orders_sync_settings', $changed );

		try {
			// Forged and invalid deliveries don't use up the budget.
			for ( $i = 0; $i < 5; $i++ ) {
				$body = self::body( $this->event() );
				$res  = $this->http_deliver( $body, $this->signed( $body, $source, null, 'forged-' . $i ) );
				$this->assertSame( 401, $res['status'], 'forged' );
			}
			$invalid = self::body( $this->event( 'order.teleported' ) );
			$this->assertSame( 400, $this->http_deliver( $invalid, $this->signed( $invalid, $source, null, $secret ) )['status'] );

			$accepted = array();
			for ( $i = 0; $i < 3; $i++ ) {
				$event = $this->event();
				$body  = self::body( $event );
				$res   = $this->http_deliver( $body, $this->signed( $body, $source, null, $secret ) );
				$this->assertSame( 202, $res['status'], "delivery $i within the limit: " . $res['body'] );
				$accepted[] = array( $body, $res['json'] );
			}

			$over      = $this->event();
			$over_body = self::body( $over );
			$res       = $this->http_deliver( $over_body, $this->signed( $over_body, $source, null, $secret ) );
			$this->assertSame( 429, $res['status'], $res['body'] );
			$this->assertSame( 'acme_webhook_rate_limited', $res['json']['code'] ?? null );
			$this->assertArrayHasKey( 'retry-after', $res['headers'] );
			$this->assertMatchesRegularExpression( '/^\d+$/', $res['headers']['retry-after'] );
			$this->assertGreaterThanOrEqual( 1, (int) $res['headers']['retry-after'] );
			$this->assertLessThanOrEqual( 60, (int) $res['headers']['retry-after'] );

			// Duplicates are still answered.
			$dup = $this->http_deliver( $accepted[0][0], $this->signed( $accepted[0][0], $source, null, $secret ) );
			$this->assertSame( 202, $dup['status'], $dup['body'] );
			$this->assertSame( $accepted[0][1], $dup['json'] );
			$this->assertSame( 'true', $dup['headers']['x-acme-duplicate'] ?? null );

			// Other sources are not affected.
			$other = self::body( $this->event() );
			$res   = $this->http_deliver( $other, $this->signed( $other, $source . '-b', null, $secret . '-b' ) );
			$this->assertSame( 202, $res['status'], 'other source: ' . $res['body'] );

			// The rejected event was not stored.
			$admin = $this->http_login( 1 );
			$this->assertSame(
				404,
				$this->status_http(
					$over['id'],
					array(
						'login'      => $admin,
						'rest_nonce' => true,
					)
				)['status']
			);
		} finally {
			update_option( 'acme_orders_sync_settings', $settings );
		}
	}
}
