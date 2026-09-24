<?php
/**
 * Existing read behaviour that must keep working (pass-to-pass on the starting code).
 */

use WPSB\CRM\CrmTestCase;

class CrmReadsTest extends CrmTestCase {

	public function test_rest_lists_and_reads_contacts(): void {
		$r = $this->rest_as( 'sally', 'GET', '/contacts?per_page=10' );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertCount( 10, $r['json'] );
		$this->assertSame( '1200', $r['headers']['x-wp-total'] ?? null );
		$this->assertSame( 1200, $r['json'][0]['id'], 'newest first' );

		$r = $this->rest_as( 'sally', 'GET', '/contacts/1' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( 'Ludwig van Beethoven', $r['json']['name'] );
		$this->assertSame( 'named01@example.test', $r['json']['email'] );
		$this->assertSame( 'Bonn Pianos', $r['json']['company'] );
		$this->assertSame( 'customer', $r['json']['stage'] );
		foreach ( array( 'id', 'name', 'email', 'phone', 'company', 'stage', 'owner', 'source', 'created_at', 'updated_at' ) as $key ) {
			$this->assertArrayHasKey( $key, $r['json'] );
		}

		$r = $this->rest_as( 'sally', 'GET', '/contacts/3' );
		$this->assertSame( 'churned', $r['json']['stage'] );

		$r = $this->rest_as( 'sally', 'GET', '/contacts/99999' );
		$this->assertSame( 404, $r['status'] );
	}

	public function test_rest_search_and_notes(): void {
		$r = $this->rest_as( 'sally', 'GET', '/contacts?search=Beethoven' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( array( 'named01@example.test' ), array_column( $r['json'], 'email' ) );

		$r = $this->rest_as( 'sally', 'GET', '/contacts?search=Starfleet' );
		$this->assertSame( array( 'Jean-Luc Picard' ), array_column( $r['json'], 'name' ) );

		$r = $this->rest_as( 'sally', 'GET', '/contacts/2/notes' );
		$this->assertSame( 200, $r['status'] );
		$this->assertCount( 3, $r['json'] );
		$this->assertStringStartsWith( 'Call notes #1:', $r['json'][0]['body'] );
	}

	public function test_crm_is_not_public(): void {
		$r = $this->http( 'GET', '/wp-json/acme-crm/v1/contacts' );
		$this->assertSame( 401, $r['status'] );
		$r = $this->rest_as( 'sam', 'GET', '/contacts' );
		$this->assertSame( 403, $r['status'] );
	}
}
