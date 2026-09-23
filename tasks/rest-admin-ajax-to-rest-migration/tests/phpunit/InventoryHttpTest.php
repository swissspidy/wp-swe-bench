<?php
/**
 * REST over real HTTP: cookie + nonce, application passwords, CSV export content.
 */

use WPSB\Inventory\HttpTestCase;
use function WPSB\Inventory\count_items;
use function WPSB\Inventory\csv_by_sku;
use function WPSB\Inventory\id;
use function WPSB\Inventory\parse_csv;
use function WPSB\Inventory\stock;
use function WPSB\Inventory\user;

class InventoryHttpTest extends HttpTestCase {

	public function test_cookie_auth_needs_the_rest_nonce(): void {
		$login = $this->http_login( user( 'sam' ) );
		$res   = $this->http( 'GET', '/wp-json/acme-inventory/v1/items', array( 'login' => $login ) );
		$this->assertSame( 401, $res['status'], 'cookies without the REST nonce count as logged out' );

		$res = $this->http( 'GET', '/wp-json/acme-inventory/v1/items?search=mug', array( 'login' => $login, 'rest_nonce' => true ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertCount( 4, $res['json'] );
		$this->assertSame( '4', $res['headers']['x-wp-total'] ?? null );

		$res = $this->http( 'PATCH', '/wp-json/acme-inventory/v1/items/' . id( 'MUG-003' ), array( 'login' => $login, 'rest_nonce' => true, 'json' => true, 'body' => array( 'stock' => 70 ) ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertSame( 70, stock( 'MUG-003' ) );

		$res = $this->http( 'POST', '/wp-json/acme-inventory/v1/items/bulk-adjust', array( 'login' => $login, 'json' => true, 'body' => array( 'ids' => array( id( 'MUG-003' ) ), 'delta' => 5 ) ) );
		$this->assertSame( 401, $res['status'], 'no nonce: no bulk adjustment (CSRF)' );
		$this->assertSame( 70, stock( 'MUG-003' ) );

		$editor = $this->http_login( user( 'eddie' ) );
		$res    = $this->http( 'POST', '/wp-json/acme-inventory/v1/items/bulk-adjust', array( 'login' => $editor, 'rest_nonce' => true, 'json' => true, 'body' => array( 'ids' => array( id( 'MUG-003' ) ), 'delta' => 5 ) ) );
		$this->assertSame( 403, $res['status'] );
		$this->assertSame( 70, stock( 'MUG-003' ) );
	}

	public function test_application_passwords(): void {
		$auth = $this->app_password( 'wendy' );
		$res  = $this->http( 'POST', '/wp-json/acme-inventory/v1/items/bulk-adjust', array( 'headers' => $auth, 'json' => true, 'body' => array( 'ids' => array( id( 'PST-001' ), id( 'PST-002' ) ), 'delta' => 4, 'reason' => 'Delivery' ) ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertSame( array( 'PST-001' => 22, 'PST-002' => 4 ), array_column( $res['json']['items'], 'stock', 'sku' ) );
		$this->assertSame( 22, stock( 'PST-001' ) );

		$auth = $this->app_password( 'sue' );
		$this->assertSame( 403, $this->http( 'GET', '/wp-json/acme-inventory/v1/items', array( 'headers' => $auth ) )['status'] );
		$this->assertSame( 401, $this->http( 'GET', '/wp-json/acme-inventory/v1/items' )['status'] );
	}

	public function test_rest_csv_export(): void {
		$auth = $this->app_password( 'sam' );
		$res  = $this->http( 'GET', '/wp-json/acme-inventory/v1/items/export', array( 'headers' => $auth ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertStringStartsWith( 'text/csv', $res['headers']['content-type'] ?? '' );
		$this->assertMatchesRegularExpression( '/charset=utf-8/i', $res['headers']['content-type'] ?? '' );
		$this->assertMatchesRegularExpression( '/attachment;\s*filename="?inventory-' . gmdate( 'Y-m-d' ) . '\.csv"?/', $res['headers']['content-disposition'] ?? '' );
		$this->assertCsvContent( $res['body'] );

		// Filters and sorting like the list.
		$res  = $this->http( 'GET', '/wp-json/acme-inventory/v1/items/export?search=mug&orderby=stock&order=desc', array( 'headers' => $auth ) );
		$rows = parse_csv( $res['body'] );
		$this->assertSame( array( 'MUG-001', 'MUG-002', 'MUG-003', 'MUG-004' ), array_column( array_slice( $rows, 1 ), 0 ) );
		$res  = $this->http( 'GET', '/wp-json/acme-inventory/v1/items/export?low_stock=1', array( 'headers' => $auth ) );
		$this->assertEqualsCanonicalizing( array( 'MUG-004', 'PST-002', 'LEG-002' ), array_column( array_slice( parse_csv( $res['body'] ), 1 ), 0 ) );

		// Not for everyone.
		$this->assertSame( 403, $this->http( 'GET', '/wp-json/acme-inventory/v1/items/export', array( 'headers' => $this->app_password( 'eddie' ) ) )['status'] );
		$res = $this->http( 'GET', '/wp-json/acme-inventory/v1/items/export' );
		$this->assertSame( 401, $res['status'] );
		$this->assertStringNotContainsString( 'MUG-001', $res['body'] );
	}
}
