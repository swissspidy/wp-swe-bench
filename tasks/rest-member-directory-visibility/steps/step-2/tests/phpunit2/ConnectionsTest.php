<?php
/**
 * Step 2: connections API, legacy buddy import and the `connections` visibility level (in-process,
 * rolled back after each test).
 */

use function WPSB\Members\ksorted;
use function WPSB\Members\norm;
use function WPSB\Members\uid;
use const WPSB\Members\VALUES;

class ConnectionsTest extends WPSB\TestCase {

	private function as_user( string $login ): void {
		wp_set_current_user( uid( $login ) );
	}

	private function lists( string $login ): array {
		$this->as_user( $login );
		$res = $this->rest( 'GET', '/acme-members/v1/members/me/connections' );
		$this->assertSame( 200, $res->get_status(), "connections of $login" );
		return norm( $res->get_data() );
	}

	private function ids( string ...$logins ): array {
		$ids = array_map( 'WPSB\Members\uid', $logins );
		sort( $ids );
		return $ids;
	}

	private function connect( string $from, string $to ): array {
		$this->as_user( $from );
		$res = $this->rest( 'POST', '/acme-members/v1/members/me/connections', array(), array( 'member' => uid( $to ) ) );
		return array( $res->get_status(), norm( $res->get_data() ) );
	}

	private function disconnect( string $from, string $to ): int {
		$this->as_user( $from );
		return $this->rest( 'DELETE', '/acme-members/v1/members/me/connections/' . uid( $to ) )->get_status();
	}

	private function fields_as( string $viewer, string $member ): ?array {
		wp_set_current_user( uid( $viewer ) );
		$res = $this->rest( 'GET', '/acme-members/v1/members/' . uid( $member ) );
		if ( 404 === $res->get_status() ) {
			return null;
		}
		$this->assertSame( 200, $res->get_status() );
		return ksorted( (array) norm( $this->rest_data( $res ) )['fields'] );
	}

	private function patch( string $login, array $body ): void {
		$this->as_user( $login );
		$res = $this->rest( 'PATCH', '/acme-members/v1/members/me', array(), $body );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
	}

	public function test_legacy_buddy_lists_are_imported(): void {
		$expected = array(
			'carol' => array( $this->ids( 'gina' ), array(), $this->ids( 'bob' ) ),
			'gina'  => array( $this->ids( 'carol', 'frank' ), array(), array() ),
			'frank' => array( $this->ids( 'gina' ), array(), array() ),
			'bob'   => array( array(), $this->ids( 'carol' ), array() ),
			'alice' => array( $this->ids( 'henry' ), array(), array() ),
			'henry' => array( $this->ids( 'alice' ), array(), array() ),
			'erin'  => array( array(), array(), $this->ids( 'dave' ) ),
			'dave'  => array( array(), $this->ids( 'erin' ), array() ),
			'filler01' => array( array(), array(), array() ),
		);
		foreach ( $expected as $login => list( $connections, $incoming, $outgoing ) ) {
			$this->assertSame(
				array(
					'connections' => $connections,
					'incoming'    => $incoming,
					'outgoing'    => $outgoing,
				),
				$this->lists( $login ),
				"connections of $login after importing the forum buddy lists"
			);
		}
	}

	public function test_authentication(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->rest( 'GET', '/acme-members/v1/members/me/connections' )->get_status() );
		$this->assertSame( 401, $this->rest( 'POST', '/acme-members/v1/members/me/connections', array(), array( 'member' => uid( 'alice' ) ) )->get_status() );
		$this->as_user( 'sue' );
		$this->assertSame( 403, $this->rest( 'GET', '/acme-members/v1/members/me/connections' )->get_status() );
		$this->assertSame( 403, $this->rest( 'POST', '/acme-members/v1/members/me/connections', array(), array( 'member' => uid( 'alice' ) ) )->get_status() );
		$this->assertSame( 403, $this->rest( 'DELETE', '/acme-members/v1/members/me/connections/' . uid( 'alice' ) )->get_status() );
	}

	public function test_request_accept_remove(): void {
		$this->assertSame( array( 200, array( 'member' => uid( 'bob' ), 'status' => 'requested' ) ), $this->connect( 'alice', 'bob' ) );
		$this->assertSame( $this->ids( 'bob' ), $this->lists( 'alice' )['outgoing'] );
		$this->assertSame( $this->ids( 'alice', 'carol' ), $this->lists( 'bob' )['incoming'] );
		$this->assertSame( array( 200, array( 'member' => uid( 'bob' ), 'status' => 'requested' ) ), $this->connect( 'alice', 'bob' ), 'Requesting twice' );

		$this->assertSame( array( 200, array( 'member' => uid( 'alice' ), 'status' => 'connected' ) ), $this->connect( 'bob', 'alice' ), 'Requesting back accepts' );
		$this->assertSame( array( 'connections' => $this->ids( 'alice' ), 'incoming' => $this->ids( 'carol' ), 'outgoing' => array() ), $this->lists( 'bob' ) );
		$this->assertSame( array( 'connections' => $this->ids( 'bob', 'henry' ), 'incoming' => array(), 'outgoing' => array() ), $this->lists( 'alice' ) );
		$this->assertSame( array( 200, array( 'member' => uid( 'alice' ), 'status' => 'connected' ) ), $this->connect( 'bob', 'alice' ), 'Already connected' );

		// Remove (either side).
		$this->assertSame( 200, $this->disconnect( 'alice', 'bob' ) );
		$this->assertSame( array(), $this->lists( 'bob' )['connections'] );
		$this->assertSame( $this->ids( 'henry' ), $this->lists( 'alice' )['connections'] );
		$this->assertSame( 404, $this->disconnect( 'alice', 'bob' ), 'Nothing left to remove' );

		// Cancel an outgoing request / decline an incoming one.
		$this->assertSame( 200, $this->disconnect( 'carol', 'bob' ) );
		$this->assertSame( array(), $this->lists( 'bob' )['incoming'] );
		$this->assertSame( array(), $this->lists( 'carol' )['outgoing'] );
		$this->assertSame( 200, $this->disconnect( 'dave', 'erin' ), 'decline' );
		$this->assertSame( array(), $this->lists( 'erin' )['outgoing'] );
	}

	public function test_request_errors(): void {
		list( $status, $data ) = $this->connect( 'alice', 'alice' );
		$this->assertSame( 400, $status );
		$this->assertSame( 'rest_invalid_param', $data['code'] );
		foreach ( array( 'eddie', 'admin' ) as $not_member ) {
			list( $status, $data ) = $this->connect( 'alice', $not_member );
			$this->assertSame( 404, $status );
			$this->assertSame( 'acme_members_not_found', $data['code'] );
		}
		$this->as_user( 'alice' );
		$this->assertSame( 404, $this->rest( 'POST', '/acme-members/v1/members/me/connections', array(), array( 'member' => 999999 ) )->get_status() );
		// Hidden profiles can't be found this way.
		foreach ( array( 'frank', 'dave' ) as $hidden ) {
			list( $status ) = $this->connect( 'alice', $hidden );
			$this->assertSame( 404, $status, "$hidden's profile is private" );
			$this->assertNotContains( uid( 'alice' ), $this->lists( $hidden )['incoming'] );
		}
		// …but an existing connection stays reachable.
		$this->assertSame( array( 200, array( 'member' => uid( 'frank' ), 'status' => 'connected' ) ), $this->connect( 'gina', 'frank' ) );
	}

	public function test_connections_level_on_fields(): void {
		$this->patch( 'carol', array( 'field_visibility' => array( 'phone' => 'connections', 'city' => 'connections' ) ) );

		$this->as_user( 'carol' );
		$me = norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members/me' ) ) );
		$this->assertSame( 'connections', $me['visibility']['fields']['phone'] );

		$full        = ksorted( array_intersect_key( VALUES['carol'], array_flip( array( 'job_title', 'city', 'phone', 'website', 'bio' ) ) ) );
		$without     = ksorted( array_intersect_key( VALUES['carol'], array_flip( array( 'job_title', 'website', 'bio' ) ) ) );
		$expectation = array(
			''      => $without,
			'sue'   => $without,
			'alice' => $without,
			'bob'   => $without, // Pending request only.
			'gina'  => $full,
		);
		foreach ( $expectation as $viewer => $fields ) {
			$this->assertSame( $fields, $this->fields_as( $viewer, 'carol' ), 'carol as ' . ( $viewer ?: 'anonymous' ) );
		}
		$this->assertSame( VALUES['carol']['company'], $this->fields_as( 'admin', 'carol' )['company'] ?? null );

		// Search, city filter, core endpoints.
		$search = function ( string $viewer, array $query ): array {
			$this->as_user( $viewer );
			return array_column( norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members', $query + array( 'per_page' => 50 ) ) ) ), 'slug' );
		};
		$this->assertSame( array( 'carol' ), $search( 'gina', array( 'search' => '341 555' ) ) );
		$this->assertSame( array(), $search( 'alice', array( 'search' => '341 555' ) ) );
		$this->assertSame( array(), $search( 'bob', array( 'search' => '341 555' ) ) );
		$this->assertSame( array( 'carol' ), $search( 'gina', array( 'city' => 'Leipzig' ) ) );
		$this->assertSame( array(), $search( 'alice', array( 'city' => 'Leipzig' ) ) );

		foreach ( array( 'gina' => true, 'alice' => false, 'bob' => false, '' => false ) as $viewer => $sees ) {
			$this->as_user( $viewer );
			$profile = (array) norm( $this->rest_data( $this->rest( 'GET', '/wp/v2/users/' . uid( 'carol' ) ) ) )['acme_profile'];
			$this->assertSame( $sees, isset( $profile['phone'] ), '/wp/v2/users acme_profile as ' . ( $viewer ?: 'anonymous' ) );
			$found = array_column( norm( $this->rest_data( $this->rest( 'GET', '/wp/v2/search', array( 'type' => 'acme-member', 'search' => '341 555' ) ) ) ), 'id' );
			$this->assertSame( $sees ? array( uid( 'carol' ) ) : array(), $found, '/wp/v2/search as ' . ( $viewer ?: 'anonymous' ) );
		}

		// Removing the connection revokes access immediately.
		$this->assertSame( 200, $this->disconnect( 'carol', 'gina' ) );
		$this->assertSame( $without, $this->fields_as( 'gina', 'carol' ) );
	}

	public function test_connections_level_on_the_profile(): void {
		$this->patch( 'gina', array( 'visibility' => 'connections' ) );
		foreach ( array( '', 'sue', 'eddie', 'alice', 'bob' ) as $viewer ) {
			$this->assertNull( $this->fields_as( $viewer, 'gina' ), 'gina hidden from ' . ( $viewer ?: 'anonymous' ) );
		}
		foreach ( array( 'carol', 'frank', 'admin' ) as $viewer ) {
			$this->assertSame( ksorted( VALUES['gina'] ), $this->fields_as( $viewer, 'gina' ), "gina as $viewer" );
		}
		$this->as_user( 'alice' );
		$this->assertNotContains( 'gina', array_column( norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members', array( 'per_page' => 50 ) ) ) ), 'slug' ) );
		$this->as_user( 'carol' );
		$this->assertContains( 'gina', array_column( norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members', array( 'per_page' => 50 ) ) ) ), 'slug' ) );

		// Alice can't find Gina's hidden profile to ask her…
		$this->assertSame( 404, $this->connect( 'alice', 'gina' )[0] );
		// …but Gina can ask Alice, and Alice can accept. Pending grants nothing, connected does.
		$this->assertSame( 'requested', $this->connect( 'gina', 'alice' )[1]['status'] ?? null );
		$this->assertNull( $this->fields_as( 'alice', 'gina' ), 'pending' );
		$this->assertSame( array( 200, array( 'member' => uid( 'gina' ), 'status' => 'connected' ) ), $this->connect( 'alice', 'gina' ) );
		$this->assertNotNull( $this->fields_as( 'alice', 'gina' ), 'connected' );
	}

	public function test_level_validation(): void {
		$this->as_user( 'gina' );
		$res = $this->rest( 'PATCH', '/acme-members/v1/members/me', array(), array( 'visibility' => 'friends' ) );
		$this->assertSame( 400, $res->get_status() );
		$res = $this->rest( 'PATCH', '/acme-members/v1/members/me', array(), array( 'field_visibility' => array( 'phone' => 'connection' ) ) );
		$this->assertSame( 400, $res->get_status() );
		$res = $this->rest( 'PATCH', '/acme-members/v1/members/me', array(), array( 'visibility' => 'connections', 'field_visibility' => array( 'bio' => 'connections' ) ) );
		$this->assertSame( 200, $res->get_status() );
		$data = norm( $this->rest_data( $res ) );
		$this->assertSame( 'connections', $data['visibility']['profile'] );
		$this->assertSame( 'connections', $data['visibility']['fields']['bio'] );
	}
}
