<?php
/**
 * Shared helpers for the Acme Redirects WP-CLI tests.
 *
 * Tests run WP-CLI in subprocesses and hit the Playground server, so they work on
 * committed data: every test snapshots the rules table and the options it touches
 * and restores them afterwards.
 */

abstract class AcmeRedirectsCase extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** Rule IDs in matching order in the seeded data. */
	const SEEDED_ORDER = array( 4, 1, 2, 5, 7, 8, 9, 10, 13, 14, 15, 16, 17, 6, 3, 12, 11, 18 );

	const DEFAULT_FIELDS = array( 'id', 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'hits' );

	private array $table_snapshot = array();

	/** @var mixed */
	private $purge_snapshot;

	/** @var string[] */
	protected array $tmp_files = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->table_snapshot = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}acme_redirects ORDER BY id", ARRAY_A );
		$this->purge_snapshot = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'acme_cdn_purge_log'" );
		$this->assertCount( 18, $this->table_snapshot, 'seed sanity: 18 rules' );
	}

	protected function tearDown(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'acme_redirects';
		$wpdb->query( "DELETE FROM {$table}" );
		foreach ( $this->table_snapshot as $row ) {
			$wpdb->insert( $table, $row );
		}
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ( '_transient_acme_redirects_rules', '_transient_timeout_acme_redirects_rules' )" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name = 'acme_cdn_purge_log'" );
		if ( null !== $this->purge_snapshot ) {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => 'acme_cdn_purge_log',
					'option_value' => $this->purge_snapshot,
					'autoload'     => 'off',
				)
			);
		}
		foreach ( $this->tmp_files as $f ) {
			@unlink( $f );
		}
		wp_cache_flush();
		parent::tearDown();
	}

	/** Runs `wp acme-redirects …`. */
	protected function cmd( string $args ): array {
		return $this->wp_cli( 'acme-redirects ' . $args );
	}

	protected function assertOk( array $run, string $msg = '' ): void {
		$this->assertSame( 0, $run['exit'], trim( $msg . "\nexit {$run['exit']}\nSTDOUT: {$run['stdout']}\nSTDERR: {$run['stderr']}" ) );
	}

	protected function assertFails( array $run, string $msg = '' ): void {
		$this->assertSame( 1, $run['exit'], trim( $msg . "\nexpected exit 1, got {$run['exit']}\nSTDOUT: {$run['stdout']}\nSTDERR: {$run['stderr']}" ) );
		$this->assertStringContainsString( 'Error:', $run['stderr'] . $run['stdout'], $msg );
		$this->assertStringNotContainsString( 'is not a registered', $run['stderr'], 'the command must exist' );
	}

	/** Decoded JSON from a command's STDOUT. */
	protected function json( array $run ) {
		$data = json_decode( trim( $run['stdout'] ), true );
		$this->assertNotNull( $data, "STDOUT is not JSON:\n{$run['stdout']}\nSTDERR: {$run['stderr']}" );
		return $data;
	}

	/** Last non-empty line of STDOUT+STDERR (summary lines). */
	protected function last_line( array $run, string $stream = 'stdout' ): string {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $run[ $stream ] ) ), 'strlen' ) );
		return (string) end( $lines );
	}

	/** Raw rows (DB columns) keyed by ID. */
	protected function rows(): array {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}acme_redirects ORDER BY id", ARRAY_A ) as $row ) {
			$out[ (int) $row['id'] ] = $row;
		}
		return $out;
	}

	protected function row( int $id ): ?array {
		return $this->rows()[ $id ] ?? null;
	}

	/** Rows with a given source (any match type). */
	protected function rows_by_source( string $source ): array {
		return array_values( array_filter( $this->rows(), static fn( $r ) => $r['source'] === $source ) );
	}

	/** Entries of the CDN purge log written by the site's mu-plugin. */
	protected function purge_log(): array {
		global $wpdb;
		$raw = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'acme_cdn_purge_log'" );
		$log = null === $raw ? array() : maybe_unserialize( $raw );
		return is_array( $log ) ? array_values( $log ) : array();
	}

	/** Front-end request without following redirects. */
	protected function front( string $path ): array {
		$r = $this->http( 'GET', $path );
		return array(
			'status'   => $r['status'],
			'location' => $r['headers']['location'] ?? '',
			'acme'     => 'Acme Redirects' === ( $r['headers']['x-redirect-by'] ?? '' ),
		);
	}

	/** Asserts the front end redirects $path with $status to $location (via Acme Redirects). */
	protected function assertFrontRedirect( string $path, int $status, string $location ): void {
		$r = $this->front( $path );
		$this->assertSame( $status, $r['status'], "front-end status for $path" );
		$this->assertTrue( $r['acme'], "$path must be redirected by Acme Redirects" );
		$this->assertSame( $location, $r['location'], "Location for $path" );
	}

	protected function assertNoFrontRedirect( string $path ): void {
		$r = $this->front( $path );
		$this->assertFalse( $r['acme'], "$path must not be redirected by Acme Redirects (got {$r['status']} {$r['location']})" );
		$this->assertNotSame( 410, $r['status'], "$path must not be 410" );
	}

	/** Warms the front-end rule cache (a real visit). */
	protected function warm_front_end(): void {
		$this->front( '/?acme-warm-up=1' );
	}

	protected function tmp_file( string $contents, string $ext = '.csv' ): string {
		$f = tempnam( sys_get_temp_dir(), 'acme-redirects-' );
		@unlink( $f );
		$f .= $ext;
		file_put_contents( $f, $contents );
		$this->tmp_files[] = $f;
		return $f;
	}

	protected function tmp_path( string $ext = '.csv' ): string {
		$f = tempnam( sys_get_temp_dir(), 'acme-redirects-out-' );
		@unlink( $f );
		$f .= $ext;
		$this->tmp_files[] = $f;
		return $f;
	}

	protected static function h( string $home_relative ): string {
		return 'http://127.0.0.1:9400' . $home_relative;
	}

	/** Admin CSV export (Tools → Redirects → Export CSV) over HTTP. */
	protected function admin_export(): string {
		$admin = (int) get_user_by( 'login', 'admin' )->ID;
		$login = $this->http_login( $admin );
		$nonce = $this->nonce_for( $admin, 'acme_redirects_export', $login['logged_in'] );
		$r     = $this->http( 'GET', '/wp-admin/admin-post.php?action=acme_redirects_export&_wpnonce=' . $nonce, array( 'login' => $login ) );
		$this->assertSame( 200, $r['status'], 'admin export' );
		$this->assertStringContainsString( 'text/csv', $r['headers']['content-type'] ?? '' );
		return $r['body'];
	}

	/** Parses CSV text into rows (arrays). */
	protected static function csv_rows( string $csv ): array {
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
}
