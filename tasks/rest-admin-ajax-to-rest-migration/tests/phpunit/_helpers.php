<?php
/**
 * Helpers for the Acme Inventory tests.
 */

namespace WPSB\Inventory;

const NS = '/acme-inventory/v1';

function table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_inventory_items';
}

function by_sku( string $sku ): ?object {
	global $wpdb;
	$t = table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE sku = %s", $sku ) ) ?: null;
}

function id( string $sku ): int {
	$row = by_sku( $sku );
	if ( ! $row ) {
		throw new \RuntimeException( "item $sku not found" );
	}
	return (int) $row->id;
}

function stock( string $sku ): ?int {
	$row = by_sku( $sku );
	return $row ? (int) $row->stock : null;
}

function count_items( string $where = '1=1' ): int {
	global $wpdb;
	$t = table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$where}" );
}

/** Snapshot of all stock levels (sku => stock), to prove nothing changed. */
function snapshot(): array {
	global $wpdb;
	$t    = table();
	$rows = $wpdb->get_results( "SELECT sku, stock FROM {$t} ORDER BY sku", ARRAY_A );
	return array_map( 'intval', array_column( $rows, 'stock', 'sku' ) );
}

function user( string $login ): int {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		throw new \RuntimeException( "user $login not found" );
	}
	return (int) $u->ID;
}

/** Parse a CSV document into rows (RFC 4180, any line ending). */
function parse_csv( string $csv ): array {
	$fh = fopen( 'php://memory', 'r+' );
	fwrite( $fh, $csv );
	rewind( $fh );
	$rows = array();
	while ( false !== ( $row = fgetcsv( $fh, null, ',', '"', '' ) ) ) {
		if ( array( null ) === $row ) {
			continue;
		}
		$rows[] = $row;
	}
	fclose( $fh );
	return $rows;
}

/** Index CSV data rows by SKU column (after removing a leading formula guard). */
function csv_by_sku( array $rows ): array {
	$out = array();
	foreach ( array_slice( $rows, 1 ) as $row ) {
		$out[ $row[0] ] = $row;
	}
	return $out;
}

/** Full copy of the items table (for tests that must commit their changes). */
function backup(): array {
	global $wpdb;
	$t = table();
	return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id", ARRAY_A );
}

function restore( array $rows ): void {
	global $wpdb;
	$t = table();
	$wpdb->query( "DELETE FROM {$t}" );
	foreach ( $rows as $row ) {
		$wpdb->insert( $t, $row );
	}
}

/**
 * Base for HTTP tests: committed data, restored after each test.
 */
abstract class HttpTestCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $backup   = array();
	private array $app_pass = array();

	protected function setUp(): void {
		parent::setUp();
		$this->backup = backup();
	}

	protected function tearDown(): void {
		restore( $this->backup );
		foreach ( $this->app_pass as $pair ) {
			\WP_Application_Passwords::delete_application_password( $pair[0], $pair[1] );
		}
		parent::tearDown();
	}

	protected function app_password( string $login ): array {
		$uid     = user( $login );
		$created = \WP_Application_Passwords::create_new_application_password( $uid, array( 'name' => 'Handheld ' . wp_generate_password( 6, false ) ) );
		$this->assertIsArray( $created );
		$this->app_pass[] = array( $uid, $created[1]['uuid'] );
		return array( 'Authorization' => 'Basic ' . base64_encode( $login . ':' . $created[0] ) );
	}

	/** admin-ajax request as a logged-in user; $nonce true = valid nonce, string = that value, null = none. */
	protected function ajax( ?array $login, string $method, array $params, $nonce = true ): array {
		if ( true === $nonce ) {
			$params['nonce'] = $this->nonce_for( $login['user_id'], 'acme_inventory', $login['logged_in'] );
		} elseif ( is_string( $nonce ) ) {
			$params['nonce'] = $nonce;
		}
		$opts = $login ? array( 'login' => $login ) : array();
		if ( 'GET' === $method ) {
			return $this->http( 'GET', '/wp-admin/admin-ajax.php?' . http_build_query( $params ), $opts );
		}
		return $this->http( $method, '/wp-admin/admin-ajax.php', $opts + array( 'body' => $params ) );
	}

	/** The fixed CSV format (shared with the legacy export). */
	protected function assertCsvContent( string $body ): void {
		$this->assertStringStartsWith( 'SKU,Name,Stock,Low stock threshold,Location,Updated', $body, 'header row first, no BOM' );
		$rows = parse_csv( $body );
		$this->assertSame( array( 'SKU', 'Name', 'Stock', 'Low stock threshold', 'Location', 'Updated' ), $rows[0] );
		$this->assertCount( count_items() + 1, $rows, 'all items, not just one page' );
		foreach ( $rows as $i => $row ) {
			$this->assertCount( 6, $row, "row $i has 6 columns: " . implode( '|', $row ) );
		}
		global $wpdb;
		$this->assertSame( $wpdb->get_col( "SELECT sku FROM {$wpdb->prefix}acme_inventory_items ORDER BY name ASC, id ASC" ), array_column( array_slice( $rows, 1 ), 0 ), 'sorted by name' );
		$by = csv_by_sku( $rows );
		$this->assertSame( array( 'MUG-003', 'Mug, large', '7', '5', 'A-03' ), array_slice( $by['MUG-003'], 0, 5 ) );
		$this->assertSame( 'Espresso Mug "Tiny"', $by['MUG-004'][1] );
		$this->assertSame( '12" Ocean Poster', $by['PST-002'][1] );
		$this->assertSame( 'Crème brûlée torch', $by['KIT-001'][1] );
		$this->assertSame( '2026-08-01 10:00:00', $by['MUG-001'][5] );
		$this->assertSame( array( 'LEG-002', 'Gift Card Sleeve (2.x)', '-3', '0', 'E-02', '' ), $by['LEG-002'] );
		$this->assertSame( '', $by['LEG-001'][5] );
		$this->assertSame( '\'=HYPERLINK("http://example.com/x","Click me")', $by['IMP-001'][1] );
		$this->assertSame( "'@SUM(A1:A9)", $by['IMP-001'][4] );
		$this->assertSame( "'+Plus Bundle", $by['IMP-002'][1] );
		$this->assertSame( "'-B-9", $by['IMP-002'][4] );
		$this->assertStringContainsString( '"Mug, large"', $body );
		$this->assertStringContainsString( '"Espresso Mug ""Tiny"""', $body );
	}
}
