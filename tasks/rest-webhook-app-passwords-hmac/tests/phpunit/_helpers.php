<?php
/**
 * Helpers for the Acme Orders Sync tests.
 *
 * In-process tests dispatch real REST requests (raw body + headers) through the
 * REST server; HTTP tests talk to the Playground server like the shop / ops scripts do.
 */

abstract class AcmeOrdersCase extends WPSB\TestCase {

	const ROUTE   = '/acme-orders/v1/webhook';
	const SECRETS = array(
		'shop-eu' => 'whsec_eu_7Hq2LxP9vR4mT8kZ',
		'shop-us' => 'whsec_us_Qm3Nd8Wz5Yc1Bf6J',
		'default' => 'acme-legacy-4f9c2e71b8',
	);

	/** Core cron hooks that are never run by run_cron(). */
	const CORE_CRON = array(
		'delete_expired_transients',
		'recovery_mode_clean_expired_keys',
	);

	/** @var array<string, int> acme_orders_order_synced calls per event ID. */
	protected array $synced = array();

	protected function setUp(): void {
		parent::setUp();
		$this->synced = array();
		add_action( 'acme_orders_order_synced', array( $this, 'record_synced' ), 1, 4 );
	}

	protected function tearDown(): void {
		remove_action( 'acme_orders_order_synced', array( $this, 'record_synced' ), 1 );
		remove_all_filters( 'acme_orders_retry_delay' );
		remove_all_filters( 'acme_orders_pre_sync_order' );
		parent::tearDown();
	}

	/** @internal */
	public function record_synced( $post_id, $order, $event, $source = '' ): void {
		$id                  = is_array( $event ) ? (string) ( $event['id'] ?? '' ) : '';
		$this->synced[ $id ] = ( $this->synced[ $id ] ?? 0 ) + 1;
	}

	// ---------------------------------------------------------------------
	// Events + signatures
	// ---------------------------------------------------------------------

	protected static function new_event_id(): string {
		return 'evt_t' . strtolower( wp_generate_password( 16, false ) );
	}

	/** An order event (array). */
	protected function event( string $type = 'order.created', array $order = array(), ?string $id = null ): array {
		$number = $order['number'] ?? 'T-' . wp_rand( 100000, 999999 );
		return array(
			'id'         => $id ?? self::new_event_id(),
			'type'       => $type,
			'created_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'data'       => array(
				'order' => array_merge(
					array(
						'number'   => $number,
						'status'   => 'paid',
						'total'    => 42.5,
						'currency' => 'EUR',
						'email'    => 'buyer@customer.example',
						'items'    => array(
							array(
								'sku'   => 'MUG-01',
								'name'  => 'Acme mug',
								'qty'   => 1,
								'price' => 42.5,
							),
						),
					),
					$order
				),
			),
		);
	}

	protected static function body( array $event ): string {
		return (string) wp_json_encode( $event, JSON_UNESCAPED_SLASHES );
	}

	protected static function signature( string $body, string $secret, int $ts ): string {
		return hash_hmac( 'sha256', $ts . '.' . $body, $secret );
	}

	/** Signed headers as the shop sends them. */
	protected function signed( string $body, string $source = 'shop-eu', ?int $ts = null, ?string $secret = null ): array {
		$ts = $ts ?? time();
		return array(
			'X-Acme-Source'    => $source,
			'X-Acme-Timestamp' => (string) $ts,
			'X-Acme-Signature' => 'v1=' . self::signature( $body, $secret ?? self::SECRETS[ $source ], $ts ),
		);
	}

	// ---------------------------------------------------------------------
	// In-process REST
	// ---------------------------------------------------------------------

	/** POST the raw body to the webhook route (in-process). */
	protected function deliver( string $body, array $headers ): WP_REST_Response {
		return $this->rest( 'POST', self::ROUTE, array(), $body, array_merge( array( 'Content-Type' => 'application/json' ), $headers ) );
	}

	/** Signed delivery of an event array (in-process). */
	protected function deliver_signed( array $event, string $source = 'shop-eu' ): WP_REST_Response {
		$body = self::body( $event );
		return $this->deliver( $body, $this->signed( $body, $source ) );
	}

	protected function error_code( WP_REST_Response $response ): ?string {
		$data = $response->get_data();
		return is_array( $data ) ? ( $data['code'] ?? null ) : null;
	}

	protected function header( WP_REST_Response $response, string $name ): ?string {
		foreach ( $response->get_headers() as $k => $v ) {
			if ( strtolower( $k ) === strtolower( $name ) ) {
				return (string) $v;
			}
		}
		return null;
	}

	protected function assertAccepted( WP_REST_Response $response, string $event_id, string $message = '' ): void {
		$this->assertSame( 202, $response->get_status(), $message . ' ' . wp_json_encode( $response->get_data() ) );
		$data = $this->rest_data( $response );
		$this->assertSame( $event_id, $data['event_id'] ?? null, $message );
		$this->assertSame( 'queued', $data['status'] ?? null, $message );
	}

	protected function assertRejected( WP_REST_Response $response, int $status, string $code, string $message = '' ): void {
		$this->assertSame( $status, $response->get_status(), $message . ' ' . wp_json_encode( $response->get_data() ) );
		$this->assertSame( $code, $this->error_code( $response ), $message );
	}

	/** Delivery status as an administrator (in-process); null on 404. */
	protected function delivery_status( string $event_id ): ?array {
		$prev = get_current_user_id();
		wp_set_current_user( 1 );
		$response = $this->rest( 'GET', '/acme-orders/v1/deliveries/' . $event_id );
		wp_set_current_user( $prev );
		if ( 404 === $response->get_status() ) {
			return null;
		}
		$this->assertSame( 200, $response->get_status(), 'status endpoint: ' . wp_json_encode( $response->get_data() ) );
		return $this->rest_data( $response );
	}

	/**
	 * Runs every due cron event in-process, like `wp cron event run --due-now`, repeatedly
	 * until nothing is due. $ahead pretends the clock is $ahead seconds later *for choosing
	 * events only*.
	 */
	protected function run_cron( int $ahead = 0, int $rounds = 12 ): int {
		$ran = 0;
		for ( $i = 0; $i < $rounds; $i++ ) {
			$batch = 0;
			$crons = _get_cron_array();
			$limit = time() + $ahead;
			foreach ( $crons as $ts => $hooks ) {
				if ( $ts > $limit ) {
					continue;
				}
				foreach ( $hooks as $hook => $events ) {
					if ( str_starts_with( $hook, 'wp_' ) || in_array( $hook, self::CORE_CRON, true ) ) {
						continue;
					}
					foreach ( $events as $event ) {
						if ( ! empty( $event['schedule'] ) ) {
							wp_reschedule_event( $ts, $event['schedule'], $hook, $event['args'] );
						}
						wp_unschedule_event( $ts, $hook, $event['args'] );
						do_action_ref_array( $hook, $event['args'] );
						++$batch;
					}
				}
			}
			$ran += $batch;
			if ( 0 === $batch ) {
				break;
			}
		}
		return $ran;
	}

	/** Local order post for a shop order (0 if none). */
	protected function order_post( string $source, string $number ): int {
		$ids = get_posts(
			array(
				'post_type'        => 'acme_order',
				'post_status'      => 'any',
				'numberposts'      => 5,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'   => '_acme_order_number',
						'value' => $number,
					),
					array(
						'key'   => '_acme_order_source',
						'value' => $source,
					),
				),
			)
		);
		$this->assertLessThanOrEqual( 1, count( $ids ), "duplicate local orders for $source/$number" );
		return $ids ? (int) $ids[0] : 0;
	}

	protected function pick_mails( string $number ): array {
		return array_values( array_filter( $this->mails(), static fn( $m ) => str_contains( (string) ( $m['subject'] ?? '' ), "Pick order $number " ) ) );
	}

	protected function merge_settings( array $changes ): array {
		$old = get_option( 'acme_orders_sync_settings' );
		update_option( 'acme_orders_sync_settings', array_merge( is_array( $old ) ? $old : array(), $changes ) );
		return is_array( $old ) ? $old : array();
	}

	/** Parses YYYY-MM-DDTHH:MM:SSZ; fails otherwise. */
	protected function assertIsoTime( $value, string $what ): int {
		$this->assertIsString( $value, "$what must be a string" );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value, "$what format" );
		return (int) strtotime( $value );
	}

	// ---------------------------------------------------------------------
	// HTTP
	// ---------------------------------------------------------------------

	/** Raw HTTP POST to the webhook route. */
	protected function http_deliver( string $body, array $headers, array $opts = array() ): array {
		return $this->http(
			'POST',
			'/wp-json' . self::ROUTE,
			array_merge(
				$opts,
				array(
					'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
					'body'    => $body,
				)
			)
		);
	}

	/** [user_login, plain application password]. */
	protected function app_password( int $user_id ): array {
		list( $password ) = WP_Application_Passwords::create_new_application_password( $user_id, array( 'name' => 'ops-' . wp_rand() ) );
		return array( get_userdata( $user_id )->user_login, $password );
	}

	protected static function basic( array $credentials ): string {
		return 'Basic ' . base64_encode( $credentials[0] . ':' . $credentials[1] );
	}

	protected function cron_cli(): void {
		$res = $this->wp_cli( 'cron event run --due-now' );
		$this->assertSame( 0, $res['exit'], 'wp cron event run --due-now failed: ' . $res['stderr'] . $res['stdout'] );
		wp_cache_flush();
	}
}
