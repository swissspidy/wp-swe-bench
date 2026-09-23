<?php
/**
 * Helpers for the Acme CRM migration tests.
 *
 * Every test starts from the customer's pre-migration database (restored in place), triggers
 * migrations the way production does (HTTP requests to the Playground server, WP-CLI) and
 * inspects the database directly.
 */

namespace WPSB\CRM;

const DB_FILE    = '/wordpress/wp-content/database/.ht.sqlite';
const PRISTINE   = '/opt/wpsb/pristine/.ht.sqlite';
const MU_FIXTURE = '/wordpress/wp-content/mu-plugins/wpsb-crm-test-migrations.php';
const TEST_LOG   = '/wordpress/wp-content/wpsb-crm-migrations.log';

/** Latest built-in migration version of the step under test. */
function latest(): int {
	$v = (int) getenv( 'WPSB_CRM_LATEST' );
	return $v > 0 ? $v : 2;
}

function reset_db(): void {
	global $wpdb;
	$out = array();
	$rc  = 0;
	exec( 'sqlite3 ' . escapeshellarg( DB_FILE ) . ' ' . escapeshellarg( '.restore ' . PRISTINE ) . ' 2>&1', $out, $rc );
	if ( 0 !== $rc ) {
		throw new \RuntimeException( 'DB restore failed: ' . implode( "\n", $out ) );
	}
	$wpdb->flush();
	wp_cache_flush();
	@unlink( WP_CONTENT_DIR . '/wpsb-mail.log' ); // phpcs:ignore
}

function contacts_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_crm_contacts';
}

function notes_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_crm_notes';
}

function table_exists( string $table ): bool {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
}

/** Lower-cased column names (via the SQL layer's SHOW COLUMNS). */
function columns( string $table ): array {
	global $wpdb;
	if ( ! table_exists( $table ) ) {
		return array();
	}
	return array_map( 'strtolower', (array) $wpdb->get_col( "SHOW COLUMNS FROM $table" ) );
}

/** First column of every index: index name => column. */
function index_first_columns( string $table ): array {
	global $wpdb;
	$out = array();
	foreach ( (array) $wpdb->get_results( "SHOW INDEX FROM $table", ARRAY_A ) as $row ) {
		if ( 1 === (int) $row['Seq_in_index'] ) {
			$out[ $row['Key_name'] ] = strtolower( $row['Column_name'] );
		}
	}
	return $out;
}

function has_index_on( string $table, string $column ): bool {
	return in_array( $column, index_first_columns( $table ), true );
}

/** Option value straight from the DB (null when missing). */
function raw_option( string $name ) {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	return null === $v ? null : maybe_unserialize( $v );
}

function set_raw_option( string $name, $value ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	$wpdb->insert(
		$wpdb->options,
		array(
			'option_name'  => $name,
			'option_value' => maybe_serialize( $value ),
			'autoload'     => 'off',
		)
	);
	wp_cache_flush();
}

function delete_raw_option( string $name ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	wp_cache_flush();
}

function db_version(): ?int {
	$v = raw_option( 'acme_crm_db_version' );
	return null === $v ? null : (int) $v;
}

function migration_log(): array {
	$log = raw_option( 'acme_crm_migration_log' );
	return is_array( $log ) ? array_values( $log ) : array();
}

/** Log entries for one version and status. */
function log_entries( int $version, ?string $status = null ): array {
	return array_values(
		array_filter(
			migration_log(),
			static fn( $e ) => is_array( $e ) && (int) ( $e['version'] ?? 0 ) === $version && ( null === $status || ( $e['status'] ?? '' ) === $status )
		)
	);
}

/** Test add-on migrations (see fixtures/wpsb-crm-test-migrations.php). */
function install_test_migrations( array $config ): void {
	copy( __DIR__ . '/fixtures/wpsb-crm-test-migrations.php', MU_FIXTURE );
	set_raw_option( 'wpsb_crm_test_migrations', $config );
	@unlink( TEST_LOG ); // phpcs:ignore
}

function remove_test_migrations(): void {
	@unlink( MU_FIXTURE ); // phpcs:ignore
	@unlink( TEST_LOG ); // phpcs:ignore
}

/** Lines "up <version> <time>" / "down <version> <time>" written by the test migrations. */
function test_log( string $what = 'up' ): array {
	if ( ! is_file( TEST_LOG ) ) {
		return array();
	}
	$out = array();
	foreach ( file( TEST_LOG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$parts = explode( ' ', $line );
		if ( $parts[0] === $what ) {
			$out[] = (int) $parts[1];
		}
	}
	return $out;
}

/** Contact row by email (null when missing). */
function contact_by_email( string $email ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . contacts_table() . ' WHERE email = %s ORDER BY id LIMIT 1', $email ), ARRAY_A );
	return $row ? $row : null;
}

function debug_log_offset(): int {
	$f = WP_CONTENT_DIR . '/debug.log';
	clearstatcache();
	return is_file( $f ) ? (int) filesize( $f ) : 0;
}

/** PHP errors / DB errors logged since an offset (ignores the offline update checks and the test's own broken query). */
function php_problems_since( int $offset ): array {
	$f = WP_CONTENT_DIR . '/debug.log';
	clearstatcache();
	if ( ! is_file( $f ) ) {
		return array();
	}
	$out = array();
	foreach ( preg_split( '/\R/', (string) file_get_contents( $f, false, null, $offset ) ) as $line ) {
		if ( preg_match( '/wp_update_(plugins|themes)|wp_version_check|wpsb_no_such_table/', $line ) ) {
			continue;
		}
		if ( preg_match( '/PHP (Fatal|Warning|Deprecated|Notice)|Uncaught|WordPress database error|SQLSTATE/', $line ) ) {
			$out[] = substr( $line, 0, 400 );
		}
	}
	return $out;
}

/**
 * Base class: pristine pre-migration database for every test.
 */
abstract class CrmTestCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function setUp(): void {
		remove_test_migrations();
		reset_db();
		parent::setUp();
	}

	protected function tearDown(): void {
		remove_test_migrations();
		parent::tearDown();
	}

	protected function user_id( string $login ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $login ) );
	}

	/** A plain front-end request (the "first request after the deploy"). */
	protected function visit( string $path = '/' ): array {
		$r = $this->http( 'GET', $path );
		wp_cache_flush();
		return $r;
	}

	/** Keep making web requests until the schema is at the latest built-in version. */
	protected function migrate_fully( int $max_requests = 12 ): int {
		$n = 0;
		while ( db_version() !== latest() && $n < $max_requests ) {
			$r = $this->visit( 0 === $n % 2 ? '/' : '/wp-json/' );
			$this->assertSame( 200, $r['status'], 'A request during migrations failed: ' . substr( $r['body'], 0, 500 ) );
			++$n;
		}
		$this->assertSame( latest(), db_version(), "The database should be at version " . latest() . " after $n web requests" );
		return $n;
	}

	/** REST request as a logged-in user over HTTP. */
	protected function rest_as( string $login, string $method, string $route, ?array $body = null ): array {
		$auth = $this->http_login( $this->user_id( $login ) );
		$opts = array(
			'login'      => $auth,
			'rest_nonce' => true,
		);
		if ( null !== $body ) {
			$opts['body'] = $body;
			$opts['json'] = true;
		}
		$r = $this->http( $method, '/wp-json/acme-crm/v1' . $route, $opts );
		wp_cache_flush();
		return $r;
	}

	/** Submit the website contact form like a visitor. */
	protected function submit_contact_form( array $fields ): array {
		$page = $this->http( 'GET', '/contact/' );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/name="_acme_crm_nonce" value="([^"]+)"/', $page['body'] );
		preg_match( '/name="_acme_crm_nonce" value="([^"]+)"/', $page['body'], $m );
		$r = $this->http(
			'POST',
			'/wp-admin/admin-post.php',
			array(
				'body' => array_merge(
					array(
						'action'          => 'acme_crm_form',
						'_acme_crm_nonce' => $m[1],
					),
					$fields
				),
			)
		);
		wp_cache_flush();
		return $r;
	}
}

/**
 * Reference implementation of the name rules from the 1.6 specification (test oracle).
 *
 * @return array{0:string,1:string}
 */
function expected_split( string $name ): array {
	$norm = static fn( $v ) => trim( (string) preg_replace( '/\s+/u', ' ', (string) $v ) );
	$name = $norm( $name );
	if ( '' === $name ) {
		return array( '', '' );
	}
	if ( 1 === substr_count( $name, ',' ) ) {
		list( $last, $first ) = explode( ',', $name );
		return array( $norm( $first ), $norm( $last ) );
	}
	$tokens = explode( ' ', $norm( str_replace( ',', ' ', $name ) ) );
	if ( 1 === count( $tokens ) ) {
		return array( $tokens[0], '' );
	}
	$suffix = '';
	if ( count( $tokens ) >= 3 && in_array( strtolower( rtrim( end( $tokens ), '.' ) ), array( 'jr', 'sr', 'ii', 'iii', 'iv' ), true ) ) {
		$suffix = array_pop( $tokens );
	}
	$particles = array( 'van', 'von', 'der', 'den', 'de', 'del', 'della', 'di', 'da', 'du', 'la', 'le', 'ter', 'ten', 'bin', 'al' );
	$last      = array( array_pop( $tokens ) );
	while ( count( $tokens ) > 1 && in_array( end( $tokens ), $particles, true ) ) {
		array_unshift( $last, array_pop( $tokens ) );
	}
	if ( '' !== $suffix ) {
		$last[] = $suffix;
	}
	return array( implode( ' ', $tokens ), implode( ' ', $last ) );
}
