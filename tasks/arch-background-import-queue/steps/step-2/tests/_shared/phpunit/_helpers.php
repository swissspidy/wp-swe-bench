<?php
/**
 * Shared helpers for the Acme Importer background-queue tests.
 *
 * Everything runs on committed data: imports are uploaded over HTTP (real multipart
 * uploads), cron events are run in-process (or in WP-CLI subprocesses) the way
 * WP-Cron would run them, and the import is observed through the REST API.
 */

abstract class ImporterCase extends WPSB\TestCase {

	protected bool $use_transactions = false;

	const JOB_KEYS = array( 'id', 'status', 'file_name', 'total', 'processed', 'created', 'updated', 'skipped', 'failed', 'progress', 'user', 'created_at', 'started_at', 'finished_at' );

	const CORE_CRON = array( 'delete_expired_transients', 'recovery_mode_clean_expired_keys', 'importer_scheduled_cleanup', 'upgrader_scheduled_cleanup' );

	/** The cron event being run in-process right now: [hook, args]. */
	protected static ?array $running_event = null;

	/** @var int[] */
	protected array $jobs_created = array();

	protected function setUp(): void {
		parent::setUp();
		foreach ( array( 'wpsb_test_lock_timeout', 'wpsb_test_retry_delay', 'wpsb_test_crash_sku', 'wpsb_test_throw_skus', 'wpsb_test_reject_marker', 'wpsb_test_log_saves' ) as $o ) {
			delete_option( $o );
		}
		@unlink( '/tmp/wpsb-crash-armed' );
		@unlink( '/tmp/wpsb-saves.log' );
		$this->set_batch_size( 100 );
	}

	protected function tearDown(): void {
		// Stop whatever a failing test left behind.
		wp_set_current_user( $this->admin_id() );
		foreach ( $this->jobs_created as $id ) {
			$this->rest( 'POST', "/acme-importer/v1/imports/$id/cancel" );
		}
		foreach ( array( 'wpsb_test_lock_timeout', 'wpsb_test_retry_delay', 'wpsb_test_crash_sku', 'wpsb_test_throw_skus', 'wpsb_test_reject_marker', 'wpsb_test_log_saves' ) as $o ) {
			delete_option( $o );
		}
		@unlink( '/tmp/wpsb-crash-armed' );
		parent::tearDown();
	}

	protected function admin_id(): int {
		return (int) get_user_by( 'login', 'admin' )->ID;
	}

	protected function user_id( string $login ): int {
		return (int) get_user_by( 'login', $login )->ID;
	}

	protected function set_batch_size( int $size ): void {
		$settings               = get_option( 'acme_importer_settings', array() );
		$settings               = is_array( $settings ) ? $settings : array();
		$settings['batch_size'] = $size;
		update_option( 'acme_importer_settings', $settings );
	}

	// ---------------------------------------------------------------------
	// CSV
	// ---------------------------------------------------------------------

	/** Builds CSV text. $rows: list of arrays (null => blank line). */
	protected static function csv( array $rows, array $header = array( 'sku', 'name', 'price', 'stock', 'status', 'categories' ), string $delimiter = ',', string $eol = "\n" ): string {
		$h = fopen( 'php://memory', 'r+' );
		fputcsv( $h, $header, $delimiter, '"', '', $eol );
		foreach ( $rows as $row ) {
			if ( null === $row ) {
				fwrite( $h, $eol );
				continue;
			}
			fputcsv( $h, $row, $delimiter, '"', '', $eol );
		}
		rewind( $h );
		$csv = stream_get_contents( $h );
		fclose( $h );
		return $csv;
	}

	/** $n simple product rows SKU "{prefix}-0001"… */
	protected static function product_rows( string $prefix, int $n, int $from = 1 ): array {
		$rows = array();
		for ( $i = $from; $i < $from + $n; $i++ ) {
			$rows[] = array( sprintf( '%s-%04d', $prefix, $i ), "Product $prefix $i", sprintf( '%d,%02d', 10 + $i, $i % 100 ), (string) ( $i % 50 ), '', 'Imported|' . $prefix );
		}
		return $rows;
	}

	protected static function sku( string $prefix, int $i ): string {
		return sprintf( '%s-%04d', $prefix, $i );
	}

	// ---------------------------------------------------------------------
	// HTTP
	// ---------------------------------------------------------------------

	/**
	 * Multipart POST to the site.
	 *
	 * @param array $fields Form fields.
	 * @param array $files  field => [filename, contents].
	 */
	protected function multipart( string $path, array $login, array $fields, array $files, bool $rest_nonce = true ): array {
		$tmp  = array();
		$post = $fields;
		foreach ( $files as $field => list( $name, $contents ) ) {
			$f = tempnam( sys_get_temp_dir(), 'wpsb-upload-' );
			file_put_contents( $f, $contents );
			$tmp[]          = $f;
			$post[ $field ] = new CURLFile( $f, 'text/csv', $name );
		}
		$headers = array( 'Cookie: ' . $login['cookie'] );
		if ( $rest_nonce ) {
			$headers[] = 'X-WP-Nonce: ' . $login['rest_nonce'];
		}
		$resp_headers = array();
		$ch           = curl_init( rtrim( WP_HOME, '/' ) . $path );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $post,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_TIMEOUT        => 120,
				CURLOPT_PROXY          => '',
				CURLOPT_HEADERFUNCTION => static function ( $ch, $line ) use ( &$resp_headers ) {
					$parts = explode( ':', $line, 2 );
					if ( 2 === count( $parts ) ) {
						$resp_headers[ strtolower( trim( $parts[0] ) ) ] = trim( $parts[1] );
					}
					return strlen( $line );
				},
			)
		);
		$body   = curl_exec( $ch );
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );
		foreach ( $tmp as $f ) {
			@unlink( $f );
		}
		$this->assertNotFalse( $body, 'upload request failed' );
		return array(
			'status'  => $status,
			'headers' => $resp_headers,
			'body'    => (string) $body,
			'json'    => json_decode( (string) $body, true ),
		);
	}

	/** Uploads a CSV through the REST API. Returns the HTTP result. */
	protected function upload( string $csv, string $file_name = 'products.csv', string $login = 'admin' ): array {
		$auth = $this->http_login( $this->user_id( $login ) );
		return $this->multipart( '/wp-json/acme-importer/v1/imports', $auth, array(), array( 'file' => array( $file_name, $csv ) ) );
	}

	/** Uploads and asserts that an import was queued; returns it. */
	protected function queue( string $csv, string $file_name = 'products.csv', string $login = 'admin' ): array {
		$r = $this->upload( $csv, $file_name, $login );
		$this->assertSame( 201, $r['status'], 'upload must queue an import: ' . $r['body'] );
		$this->assertIsArray( $r['json'] );
		$this->assertIsInt( $r['json']['id'] ?? null, 'import id' );
		$this->jobs_created[] = $r['json']['id'];
		return $r['json'];
	}

	// ---------------------------------------------------------------------
	// Imports
	// ---------------------------------------------------------------------

	/** Current state of an import (in-process REST as admin). */
	protected function job( int $id ): array {
		wp_cache_flush();
		$prev = get_current_user_id();
		wp_set_current_user( $this->admin_id() );
		$response = $this->rest( 'GET', "/acme-importer/v1/imports/$id" );
		wp_set_current_user( $prev );
		$this->assertSame( 200, $response->get_status(), 'GET import: ' . wp_json_encode( $response->get_data() ) );
		return $this->rest_data( $response );
	}

	protected function assertJobShape( array $job ): void {
		$keys = array_keys( $job );
		foreach ( self::JOB_KEYS as $key ) {
			$this->assertContains( $key, $keys, "import has '$key'" );
		}
		foreach ( array( 'id', 'total', 'processed', 'created', 'updated', 'skipped', 'failed', 'progress', 'user' ) as $int ) {
			$this->assertIsInt( $job[ $int ], "$int is an integer" );
		}
		$this->assertSame( $job['created'] + $job['updated'] + $job['skipped'] + $job['failed'], $job['processed'], 'processed = created + updated + skipped + failed' );
		$expected = $job['total'] > 0 ? (int) floor( $job['processed'] * 100 / $job['total'] ) : ( 'completed' === $job['status'] ? 100 : $job['progress'] );
		$this->assertSame( $expected, $job['progress'], 'progress' );
		$this->assertIsoDate( $job['created_at'] );
	}

	protected function assertIsoDate( $value, bool $nullable = false ): ?int {
		if ( $nullable && null === $value ) {
			return null;
		}
		$this->assertIsString( $value );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]00:?00)$/', $value, 'ISO 8601 date in UTC' );
		return strtotime( $value );
	}

	/**
	 * One WP-Cron round in this process: runs the non-core events that are due
	 * (within $ahead seconds) as they were when the round started.
	 */
	protected function cron_round( int $ahead = 0 ): int {
		wp_cache_flush();
		$ran = 0;
		foreach ( (array) _get_cron_array() as $ts => $hooks ) {
			if ( $ts > time() + $ahead ) {
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
					self::$running_event = array( $hook, $event['args'] );
					try {
						do_action_ref_array( $hook, $event['args'] );
					} finally {
						self::$running_event = null;
					}
					++$ran;
					wp_cache_flush();
				}
			}
		}
		return $ran;
	}

	/** One cron round in a WP-CLI subprocess. */
	protected function cron_round_subprocess( int $ahead = 0 ): array {
		return $this->wp_cli( 'eval-file ' . __DIR__ . '/fixtures/cron-round.php ' . $ahead );
	}

	/** Runs cron until the import has ended (or $seconds passed). Returns the import. */
	protected function drain( int $id, int $seconds = 90 ): array {
		$until = time() + $seconds;
		do {
			$ran = $this->cron_round( HOUR_IN_SECONDS );
			$job = $this->job( $id );
			if ( in_array( $job['status'], array( 'completed', 'cancelled', 'failed' ), true ) ) {
				return $job;
			}
			if ( 0 === $ran ) {
				sleep( 1 );
			}
		} while ( time() < $until );
		$this->fail( "import $id did not finish: " . wp_json_encode( $job ) );
	}

	/** An option as stored in the database right now. */
	protected function db_option( string $name ) {
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		return null === $raw ? null : maybe_unserialize( $raw );
	}

	/** Downloads the error report over HTTP. */
	protected function error_report( int $id, string $login = 'admin' ): array {
		$auth = $this->http_login( $this->user_id( $login ) );
		return $this->http( 'GET', "/wp-json/acme-importer/v1/imports/$id/errors", array( 'login' => $auth, 'rest_nonce' => true ) );
	}

	/** Parses CSV text into rows. */
	protected static function parse_csv( string $csv ): array {
		$h = fopen( 'php://memory', 'r+' );
		fwrite( $h, $csv );
		rewind( $h );
		$rows = array();
		while ( false !== ( $r = fgetcsv( $h, 0, ',', '"', '' ) ) ) {
			if ( array( null ) !== $r ) {
				$rows[] = $r;
			}
		}
		fclose( $h );
		return $rows;
	}

	// ---------------------------------------------------------------------
	// Products
	// ---------------------------------------------------------------------

	/** IDs of the (non-trashed) products with this SKU, case-insensitive. */
	protected function products_with_sku( string $sku ): array {
		global $wpdb;
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_acme_sku'
					WHERE p.post_type = 'acme_product' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND UPPER( TRIM( m.meta_value ) ) = %s ORDER BY p.ID",
					strtoupper( trim( $sku ) )
				)
			)
		);
	}

	/** SKU => number of products, for all SKUs starting with $prefix. */
	protected function sku_counts( string $prefix ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT UPPER( TRIM( m.meta_value ) ) AS sku, COUNT(*) AS n FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_acme_sku'
				WHERE p.post_type = 'acme_product' AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' ) AND UPPER( m.meta_value ) LIKE %s GROUP BY UPPER( TRIM( m.meta_value ) )",
				$wpdb->esc_like( strtoupper( $prefix ) ) . '%'
			)
		);
		$out = array();
		foreach ( $rows as $r ) {
			$out[ $r->sku ] = (int) $r->n;
		}
		ksort( $out );
		return $out;
	}

	protected function assertEachSkuOnce( string $prefix, int $expected_skus ): void {
		$counts = $this->sku_counts( $prefix );
		$this->assertCount( $expected_skus, $counts, "number of $prefix products" );
		$dupes = array_filter( $counts, static fn( $n ) => $n > 1 );
		$this->assertSame( array(), $dupes, 'duplicate products' );
	}

	protected function product( string $sku ): WP_Post {
		$ids = $this->products_with_sku( $sku );
		$this->assertCount( 1, $ids, "exactly one product with SKU $sku" );
		clean_post_cache( $ids[0] );
		return get_post( $ids[0] );
	}

	protected function meta( int $id, string $key ) {
		wp_cache_delete( $id, 'post_meta' );
		return get_post_meta( $id, $key, true );
	}

	protected function count_products(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'acme_product' AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )" );
	}
}
