<?php
/**
 * Deprecated admin-ajax actions: existing callers keep working; CSRF / access-control probes fail.
 */

use WPSB\Inventory\HttpTestCase;
use function WPSB\Inventory\by_sku;
use function WPSB\Inventory\count_items;
use function WPSB\Inventory\id;
use function WPSB\Inventory\parse_csv;
use function WPSB\Inventory\snapshot;
use function WPSB\Inventory\stock;
use function WPSB\Inventory\user;

class LegacyAjaxTest extends HttpTestCase {

	private function assertRejected( array $res, array $statuses, string $what ): void {
		$this->assertContains( $res['status'], $statuses, "$what: got {$res['status']} " . substr( $res['body'], 0, 200 ) );
		$this->assertNotSame( true, $res['json']['success'] ?? null, $what );
	}

	public function test_existing_callers_keep_working(): void {
		$sam = $this->http_login( user( 'sam' ) );

		$res = $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_list', 'page' => 2 ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertEquals( count_items(), $res['json']['total'] );
		$this->assertEquals( 3, $res['json']['pages'] );
		$this->assertCount( 20, $res['json']['items'] );
		$this->assertArrayHasKey( 'sku', $res['json']['items'][0] );
		$this->assertArrayHasKey( 'stock', $res['json']['items'][0] );

		$res = $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_search', 'q' => 'poster' ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertEquals( 2, $res['json']['total'] );
		$this->assertEqualsCanonicalizing( array( 'PST-001', 'PST-002' ), array_column( $res['json']['items'], 'sku' ) );

		$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => 44 ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertTrue( $res['json']['success'] );
		$this->assertEquals( 44, $res['json']['item']['stock'] );
		$this->assertSame( 'MUG-001', $res['json']['item']['sku'] );
		$this->assertSame( 44, stock( 'MUG-001' ) );

		$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_bulk_adjust', 'ids' => array( id( 'MUG-001' ), id( 'MUG-002' ) ), 'delta' => 2, 'reason' => 'Scanner' ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertTrue( $res['json']['success'] );
		$this->assertEquals( 2, $res['json']['updated'] );
		$this->assertSame( 46, stock( 'MUG-001' ) );
		$this->assertSame( 14, stock( 'MUG-002' ) );

		$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_delete', 'id' => id( 'GEN-032' ) ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertTrue( $res['json']['success'] );
		$this->assertNull( by_sku( 'GEN-032' ) );

		$res = $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_export' ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringStartsWith( 'text/csv', $res['headers']['content-type'] ?? '' );
		$rows = parse_csv( $res['body'] );
		$this->assertSame( array( 'SKU', 'Name', 'Stock', 'Low stock threshold', 'Location', 'Updated' ), $rows[0] );
		$this->assertContains( array( 'TSH-001', 'Logo T-Shirt', '25', '5', 'B-01', by_sku( 'TSH-001' )->updated_at ), $rows );
	}

	public function test_bulk_adjust_is_not_forgeable(): void {
		$sam    = $this->http_login( user( 'sam' ) );
		$before = snapshot();
		$params = array( 'action' => 'acme_inv_bulk_adjust', 'ids' => array( id( 'MUG-001' ), id( 'TSH-001' ) ), 'delta' => -20, 'reason' => 'pwned' );

		$this->assertRejected( $this->ajax( $sam, 'POST', $params, null ), array( 403 ), 'bulk adjust without nonce' );
		$this->assertRejected( $this->ajax( $sam, 'POST', $params, 'deadbeef00' ), array( 403 ), 'bulk adjust with a forged nonce' );
		$this->assertRejected( $this->ajax( $sam, 'GET', $params ), array( 400, 403, 405 ), 'bulk adjust via GET (image tag CSRF)' );
		$this->assertSame( $before, snapshot(), 'no stock may change' );

		// A nonce minted for another user doesn't work either.
		$other = $this->nonce_for( user( 'wendy' ), 'acme_inventory', $this->http_login( user( 'wendy' ) )['logged_in'] );
		$this->assertRejected( $this->ajax( $sam, 'POST', $params, $other ), array( 403 ), 'bulk adjust with another user\'s nonce' );
		$this->assertSame( $before, snapshot() );
	}

	public function test_access_control(): void {
		$before = snapshot();
		foreach ( array( 'eddie', 'alex', 'sue' ) as $who ) {
			$login = $this->http_login( user( $who ) );
			$this->assertRejected( $this->ajax( $login, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => 1 ) ), array( 403 ), "$who update_stock" );
			$this->assertRejected( $this->ajax( $login, 'POST', array( 'action' => 'acme_inv_bulk_adjust', 'ids' => array( id( 'MUG-001' ) ), 'delta' => 1 ) ), array( 403 ), "$who bulk_adjust" );
			$this->assertRejected( $this->ajax( $login, 'POST', array( 'action' => 'acme_inv_delete', 'id' => id( 'GEN-031' ) ) ), array( 403 ), "$who delete" );
			$res = $this->ajax( $login, 'GET', array( 'action' => 'acme_inv_search', 'q' => 'mug' ) );
			$this->assertRejected( $res, array( 403 ), "$who search" );
			$this->assertStringNotContainsString( 'MUG-001', $res['body'] );
			$res = $this->ajax( $login, 'GET', array( 'action' => 'acme_inv_list' ) );
			$this->assertRejected( $res, array( 403 ), "$who list" );
			$res = $this->ajax( $login, 'GET', array( 'action' => 'acme_inv_export' ) );
			$this->assertSame( 403, $res['status'], "$who export" );
			$this->assertStringNotContainsString( 'MUG-001', $res['body'] );
		}
		// Warehouse staff accounts have the capability without an editor role.
		$wendy = $this->http_login( user( 'wendy' ) );
		$res   = $this->ajax( $wendy, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-002' ), 'stock' => 30 ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 200 ) );
		$this->assertSame( 30, stock( 'MUG-002' ) );
		$res = $this->ajax( $wendy, 'POST', array( 'action' => 'acme_inv_delete', 'id' => id( 'GEN-030' ) ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertNull( by_sku( 'GEN-030' ) );
		$before['MUG-002'] = 30;
		unset( $before['GEN-030'] );

		// Logged out.
		$res = $this->ajax( null, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => 1 ), null );
		$this->assertRejected( $res, array( 400, 403 ), 'guest update_stock' );
		$this->assertSame( $before, snapshot() );
		$this->assertNotNull( by_sku( 'GEN-031' ) );
	}

	public function test_nonce_and_method_are_required_everywhere(): void {
		$sam    = $this->http_login( user( 'sam' ) );
		$before = snapshot();
		$this->assertRejected( $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => 1 ), 'nope' ), array( 403 ), 'update_stock forged nonce' );
		$this->assertRejected( $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => 1 ), null ), array( 403 ), 'update_stock without nonce' );
		$this->assertRejected( $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => 1 ) ), array( 400, 403, 405 ), 'update_stock via GET' );
		$this->assertRejected( $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_delete', 'id' => id( 'GEN-031' ) ) ), array( 400, 403, 405 ), 'delete via GET' );
		$this->assertRejected( $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_delete', 'id' => id( 'GEN-031' ) ), null ), array( 403 ), 'delete without nonce' );
		$res = $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_export' ), null );
		$this->assertSame( 403, $res['status'], 'export without nonce' );
		$this->assertStringNotContainsString( 'MUG-001', $res['body'] );
		$this->assertSame( $before, snapshot() );
		$this->assertNotNull( by_sku( 'GEN-031' ) );
	}

	public function test_same_validation_as_the_api(): void {
		$sam    = $this->http_login( user( 'sam' ) );
		$before = snapshot();
		foreach ( array( '-5', 'abc', '' ) as $bad ) {
			$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_update_stock', 'id' => id( 'MUG-001' ), 'stock' => $bad ) );
			$this->assertRejected( $res, array( 400 ), "stock '$bad'" );
		}
		$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_bulk_adjust', 'ids' => array( id( 'MUG-001' ), id( 'PST-002' ) ), 'delta' => -1 ) );
		$this->assertRejected( $res, array( 400 ), 'bulk adjust below zero' );
		$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_bulk_adjust', 'ids' => array( id( 'MUG-001' ), 999999 ), 'delta' => 1 ) );
		$this->assertRejected( $res, array( 400 ), 'bulk adjust unknown item' );
		$res = $this->ajax( $sam, 'POST', array( 'action' => 'acme_inv_bulk_adjust', 'ids' => array( id( 'MUG-001' ) ), 'delta' => 0 ) );
		$this->assertRejected( $res, array( 400 ), 'bulk adjust by 0' );
		$this->assertSame( $before, snapshot(), 'nothing may change' );
	}

	public function test_legacy_export_is_the_fixed_csv(): void {
		$sam = $this->http_login( user( 'sam' ) );
		$res = $this->ajax( $sam, 'GET', array( 'action' => 'acme_inv_export' ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertMatchesRegularExpression( '/attachment;\s*filename="?inventory-' . gmdate( 'Y-m-d' ) . '\.csv"?/', $res['headers']['content-disposition'] ?? '' );
		$this->assertCsvContent( $res['body'] );
	}
}
