<?php
/**
 * Real HTTP requests: conditional GETs, _fields and cursors through the full server stack.
 */

use function WPSB\Leads\lead_id;
use function WPSB\Leads\row;
use function WPSB\Leads\table;
use function WPSB\Leads\user_id;

class LeadsHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var array<int, array> */
	private array $restore = array();

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->restore as $id => $row ) {
			$wpdb->update( table(), $row, array( 'id' => $id ) );
		}
		parent::tearDown();
	}

	private function get( string $path, array $login, array $headers = array() ): array {
		return $this->http( 'GET', '/wp-json/acme-leads/v2/' . ltrim( $path, '/' ), array( 'login' => $login, 'rest_nonce' => true, 'headers' => $headers ) );
	}

	private function patch( int $id, array $body, array $login ): array {
		$res = $this->http(
			'PATCH',
			'/wp-json/acme-leads/v2/leads/' . $id,
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => $body,
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		return $res;
	}

	public function test_etags_and_conditional_requests(): void {
		$id                   = lead_id( 'zed@quiet.example' );
		$this->restore[ $id ] = row( $id );
		$mona                 = $this->http_login( user_id( 'mona' ) );

		$first = $this->get( "leads/$id", $mona );
		$this->assertSame( 200, $first['status'], $first['body'] );
		$etag = $first['headers']['etag'] ?? '';
		$this->assertNotSame( '', $etag, 'Lead responses need an ETag' );
		$this->assertMatchesRegularExpression( '/^(W\/)?"[^"]+"$/', $etag, 'ETag must be a quoted entity tag' );
		$this->assertSame( 'Signed 3-year deal.', $first['json']['notes'] ?? null );

		$again = $this->get( "leads/$id", $mona );
		$this->assertSame( $etag, $again['headers']['etag'] ?? '', 'The ETag is stable while the lead does not change' );

		$not_modified = $this->get( "leads/$id", $mona, array( 'If-None-Match' => $etag ) );
		$this->assertSame( 304, $not_modified['status'] );
		$this->assertLessThan( 5, strlen( trim( $not_modified['body'] ) ), '304 responses have no body' );

		$this->assertSame( 304, $this->get( "leads/$id", $mona, array( 'If-None-Match' => '"something-else", ' . $etag ) )['status'] );
		$this->assertSame( 200, $this->get( "leads/$id", $mona, array( 'If-None-Match' => '"something-else"' ) )['status'] );

		// Any change produces a new ETag, even two changes within the same second.
		$e1 = $this->patch( $id, array( 'notes' => 'First change' ), $mona );
		$r1 = $this->get( "leads/$id", $mona, array( 'If-None-Match' => $etag ) );
		$this->assertSame( 200, $r1['status'], 'Changed lead must not be 304' );
		$this->assertSame( 'First change', $r1['json']['notes'] ?? null );
		$etag1 = $r1['headers']['etag'] ?? '';
		$this->assertNotSame( $etag, $etag1 );

		$this->patch( $id, array( 'notes' => 'Second change' ), $mona );
		$r2 = $this->get( "leads/$id", $mona, array( 'If-None-Match' => $etag1 ) );
		$this->assertSame( 200, $r2['status'] );
		$etag2 = $r2['headers']['etag'] ?? '';
		$this->assertNotSame( $etag1, $etag2 );
		$this->assertSame( 304, $this->get( "leads/$id", $mona, array( 'If-None-Match' => $etag2 ) )['status'] );

		// Bulk changes too.
		$bulk = $this->http(
			'POST',
			'/wp-json/acme-leads/v2/leads/bulk',
			array(
				'login'      => $mona,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'ids'    => array( $id ),
					'status' => 'lost',
				),
			)
		);
		$this->assertSame( 200, $bulk['status'], $bulk['body'] );
		$r3 = $this->get( "leads/$id", $mona, array( 'If-None-Match' => $etag2 ) );
		$this->assertSame( 200, $r3['status'] );
		$this->assertSame( 'lost', $r3['json']['status'] ?? null );

		// Conditional requests don't bypass access rules.
		$rita = $this->http_login( user_id( 'rita' ) );
		$this->assertSame( 404, $this->get( "leads/$id", $rita, array( 'If-None-Match' => $r3['headers']['etag'] ?? '' ) )['status'] );
		$anon = $this->http( 'GET', "/wp-json/acme-leads/v2/leads/$id", array( 'headers' => array( 'If-None-Match' => $r3['headers']['etag'] ?? '' ) ) );
		$this->assertSame( 401, $anon['status'] );
	}

	public function test_cursor_and_fields_over_http(): void {
		$mona = $this->http_login( user_id( 'mona' ) );
		$page = $this->get( 'leads?orderby=name&order=asc&per_page=25&_fields=id,name', $mona );
		$this->assertSame( 200, $page['status'], $page['body'] );
		$this->assertCount( 25, $page['json'] );
		$this->assertSame( array( 'id', 'name' ), array_keys( $page['json'][0] ) );
		$cursor = $page['headers']['x-next-cursor'] ?? '';
		$this->assertNotSame( '', $cursor );

		$next = $this->get( 'leads?orderby=name&order=asc&per_page=25&_fields=id,name&cursor=' . rawurlencode( $cursor ), $mona );
		$this->assertSame( 200, $next['status'], $next['body'] );
		global $wpdb;
		$expected = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . table() . ' ORDER BY name ASC, id ASC LIMIT 25 OFFSET 25' ) );
		$this->assertSame( $expected, array_column( $next['json'], 'id' ) );

		// The Link header points to the next page.
		$this->assertMatchesRegularExpression( '/<([^>]+)>;\s*rel="next"/', $page['headers']['link'] ?? '' );
		preg_match( '/<([^>]+)>;\s*rel="next"/', $page['headers']['link'], $m );
		$via_link = $this->http( 'GET', html_entity_decode( $m[1] ), array( 'login' => $mona, 'rest_nonce' => true ) );
		$this->assertSame( 200, $via_link['status'], $via_link['body'] );
		$this->assertSame( $expected, array_column( $via_link['json'], 'id' ) );
	}
}
