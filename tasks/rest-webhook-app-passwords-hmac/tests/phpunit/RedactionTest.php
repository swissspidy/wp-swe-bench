<?php
/**
 * No secrets at rest: sync log, debug.log, database. Includes the 1.3.2 log.
 */

class RedactionTest extends AcmeOrdersCase {

	protected bool $use_transactions = false;

	const SEEDED_EVENTS = array( 'evt_legacy_0001', 'evt_eu_0001', 'evt_eu_0002', 'evt_eu_0003', 'evt_us_0001', 'evt_us_0002' );
	const SEEDED_LEAKS  = array( '4000056655665556', '4242424242424242', 'sk_live_acme_51Hx9QdE' );

	private function log_file(): string {
		$uploads = wp_upload_dir( null, false );
		return $uploads['basedir'] . '/acme-orders-sync/sync.log';
	}

	private function read_log(): string {
		clearstatcache();
		$this->assertFileExists( $this->log_file() );
		return (string) file_get_contents( $this->log_file() );
	}

	/** Every text value in the SQLite database, except the settings that legitimately hold the secrets. */
	private function database_dump(): string {
		$pdo    = new PDO( 'sqlite:' . WP_CONTENT_DIR . '/database/.ht.sqlite' );
		$tables = $pdo->query( "SELECT name FROM sqlite_master WHERE type = 'table'" )->fetchAll( PDO::FETCH_COLUMN );
		$dump   = '';
		foreach ( $tables as $table ) {
			foreach ( $pdo->query( 'SELECT * FROM "' . str_replace( '"', '""', $table ) . '"' )->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
				if ( isset( $row['option_name'] ) && in_array( $row['option_name'], array( 'acme_orders_sync_settings', 'acme_orders_webhook_secret' ), true ) ) {
					continue;
				}
				$dump .= $table . ': ' . implode( ' | ', array_map( 'strval', $row ) ) . "\n";
			}
		}
		return $dump;
	}

	private function assertNoLeaks( string $haystack, array $needles, string $where ): void {
		foreach ( $needles as $label => $needle ) {
			$pos = strpos( $haystack, $needle );
			$this->assertFalse( $pos, "$label leaked into $where: …" . substr( $haystack, max( 0, (int) $pos - 200 ), 400 ) . '…' );
		}
	}

	public function test_existing_log_is_scrubbed_but_keeps_its_entries(): void {
		// The updated plugin has run at least once (this request, plus the test bootstrap).
		$this->assertSame( 200, $this->http( 'GET', '/wp-json/acme-orders/v1/ping' )['status'] );

		$log = $this->read_log();
		$this->assertNoLeaks( $log, array_merge( self::SECRETS, self::SEEDED_LEAKS ), 'sync.log' );
		foreach ( self::SEEDED_EVENTS as $id ) {
			$this->assertStringContainsString( $id, $log, "entry of $id must be kept" );
		}
		$this->assertStringContainsString( '[redacted]', $log );
		$this->assertGreaterThanOrEqual( 6, substr_count( $log, 'Webhook received' ), 'old entries (messages) must be kept' );
		$this->assertStringContainsString( 'Token mismatch', $log );
		$this->assertStringContainsString( '2026-', $log );

		// Still one JSON object per line, readable by the log screen.
		foreach ( array_filter( explode( "\n", $log ) ) as $line ) {
			$this->assertIsArray( json_decode( $line, true ), "not a JSON line: $line" );
		}
		$card = null;
		foreach ( array_filter( explode( "\n", $log ) ) as $line ) {
			$entry = json_decode( $line, true );
			if ( isset( $entry['context']['body']['data']['order']['payment']['card_number'] ) ) {
				$card = $entry['context']['body']['data']['order']['payment'];
				break;
			}
		}
		$this->assertNotNull( $card, 'the redacted payment block must still be there' );
		$this->assertSame( '[redacted]', $card['card_number'] );
		$this->assertSame( '[redacted]', $card['cvv'] );
		$this->assertSame( 'Erika Mustermann', $card['holder'] );

		// Tools → Order Sync Log.
		$page = $this->http(
			'GET',
			'/wp-admin/tools.php?page=acme-orders-sync-log',
			array( 'login' => $this->http_login( 1 ) )
		);
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'evt_eu_0001', $page['body'] );
		$this->assertNoLeaks( $page['body'], array_merge( self::SECRETS, self::SEEDED_LEAKS ), 'the log screen' );
	}

	public function test_new_deliveries_leave_no_secrets_anywhere(): void {
		$old_settings = $this->merge_settings( array( 'log_level' => 'debug' ) );
		$debug_log    = WP_CONTENT_DIR . '/debug.log';
		clearstatcache();
		$debug_offset = is_file( $debug_log ) ? filesize( $debug_log ) : 0;

		try {
			$needles = self::SECRETS;

			// 1. Signed delivery with sensitive payload fields.
			$event = $this->event(
				'order.created',
				array(
					'number'   => 'EU-80001',
					'payment'  => array(
						'method'      => 'card',
						'card_number' => '5555555555554444',
						'CVV'         => '9981',
					),
					'customer' => array(
						'Password' => 'hunter2-Ultra-Secret',
						'profile'  => array( 'Api_Key' => 'sk_live_newkey_Zx81' ),
					),
					'token'    => 'tok_abcdef123456',
				)
			);
			$body    = self::body( $event );
			$headers = $this->signed( $body );
			$this->assertSame( 202, $this->http_deliver( $body, $headers )['status'] );
			$needles['card number']       = '5555555555554444';
			$needles['customer password'] = 'hunter2-Ultra-Secret';
			$needles['api key']           = 'sk_live_newkey_Zx81';
			$needles['token']             = 'tok_abcdef123456';
			$needles['eu signature']      = substr( $headers['X-Acme-Signature'], 3 );

			// 2. Forged delivery with a plaintext token + bearer header.
			$forged      = $this->event( 'order.created', array( 'number' => 'EU-80002' ) );
			$forged_body = self::body( $forged );
			$forged_sig  = self::signature( $forged_body, 'guessed-secret-123', time() );
			$res         = $this->http_deliver(
				$forged_body,
				array(
					'X-Acme-Source'    => 'shop-eu',
					'X-Acme-Timestamp' => (string) time(),
					'X-Acme-Signature' => 'v1=' . $forged_sig,
					'X-Acme-Token'     => self::SECRETS['shop-eu'],
					'Authorization'    => 'Bearer bearer-secret-XYZ123',
				)
			);
			$this->assertSame( 401, $res['status'] );
			$needles['forged signature'] = $forged_sig;
			$needles['bearer']           = 'bearer-secret-XYZ123';

			// 3. US storefront.
			$us      = $this->event( 'order.created', array( 'number' => 'US-80003', 'currency' => 'USD' ) );
			$us_body = self::body( $us );
			$us_head = $this->signed( $us_body, 'shop-us' );
			$this->assertSame( 202, $this->http_deliver( $us_body, $us_head )['status'] );
			$needles['us signature'] = substr( $us_head['X-Acme-Signature'], 3 );

			// 4. Ops re-send with an application password.
			$admin  = $this->create_user( 'administrator' );
			$creds  = $this->app_password( $admin );
			$resend = $this->event( 'order.updated', array( 'number' => 'EU-80001', 'status' => 'shipped' ) );
			$res    = $this->http_deliver(
				self::body( $resend ),
				array(
					'X-Acme-Source' => 'shop-eu',
					'Authorization' => self::basic( $creds ),
				)
			);
			$this->assertSame( 202, $res['status'], $res['body'] );
			$needles['application password'] = $creds[1];
			$needles['basic credentials']    = base64_encode( $creds[0] . ':' . $creds[1] );

			// 5. Cookie-authenticated attempt.
			$login = $this->http_login( $admin );
			$this->http_deliver(
				self::body( $this->event() ),
				array( 'X-Acme-Source' => 'shop-us' ),
				array(
					'login'      => $login,
					'rest_nonce' => true,
				)
			);
			$needles['session token'] = explode( '|', $login['logged_in'] )[2];
			$needles['rest nonce']    = $login['rest_nonce'];

			$this->cron_cli();

			$post_id = $this->order_post( 'shop-eu', 'EU-80001' );
			$this->assertGreaterThan( 0, $post_id );
			$this->assertSame( 'shipped', get_post_meta( $post_id, '_acme_order_status', true ) );
			$this->assertGreaterThan( 0, $this->order_post( 'shop-us', 'US-80003' ) );

			$log = $this->read_log();
			$this->assertNoLeaks( $log, $needles, 'sync.log' );
			$this->assertNoLeaks( $this->database_dump(), $needles, 'the database' );
			clearstatcache();
			if ( is_file( $debug_log ) ) {
				$this->assertNoLeaks( (string) file_get_contents( $debug_log, false, null, $debug_offset ), $needles, 'debug.log' );
			}

			// …but it still says what happened.
			foreach ( array( $event['id'], $forged['id'], $us['id'], $resend['id'] ) as $id ) {
				$this->assertStringContainsString( $id, $log, "delivery $id must be logged" );
			}

			// The redacted payload keeps its keys.
			$raw = get_post_meta( $this->order_post( 'shop-us', 'US-80003' ), '_acme_raw_payload', true );
			$this->assertIsString( $raw );
			$eu_raw = json_decode( (string) get_post_meta( $post_id, '_acme_raw_payload', true ), true );
			$this->assertIsArray( $eu_raw );
			$this->assertSame( $resend['id'], $eu_raw['id'] );

			wp_delete_user( $admin );
		} finally {
			update_option( 'acme_orders_sync_settings', $old_settings );
		}
	}

	public function test_stored_payload_is_redacted_with_keys_kept(): void {
		$event = $this->event(
			'order.created',
			array(
				'number'  => 'EU-80010',
				'payment' => array(
					'method'      => 'card',
					'card_number' => '378282246310005',
					'cvv'         => '4321',
					'holder'      => 'Max Mustermann',
				),
			)
		);
		$body = self::body( $event );
		$this->assertSame( 202, $this->http_deliver( $body, $this->signed( $body ) )['status'] );
		$this->cron_cli();

		$post_id = $this->order_post( 'shop-eu', 'EU-80010' );
		$this->assertGreaterThan( 0, $post_id );
		$raw = json_decode( (string) get_post_meta( $post_id, '_acme_raw_payload', true ), true );
		$this->assertIsArray( $raw, '_acme_raw_payload must still hold the processed event' );
		$payment = $raw['data']['order']['payment'];
		$this->assertSame( '[redacted]', $payment['card_number'] );
		$this->assertSame( '[redacted]', $payment['cvv'] );
		$this->assertSame( 'Max Mustermann', $payment['holder'] );
		$this->assertSame( 'card', $payment['method'] );
		$this->assertNoLeaks( $this->database_dump(), array( 'card' => '378282246310005' ), 'the database' );
		$this->assertNoLeaks( $this->read_log(), array( 'card' => '378282246310005' ), 'sync.log' );
	}
}
