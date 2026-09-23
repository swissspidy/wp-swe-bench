<?php
/**
 * Read side of the directory API and core REST endpoints, per viewer (in-process).
 */

use function WPSB\Members\expected;
use function WPSB\Members\ksorted;
use function WPSB\Members\norm;
use function WPSB\Members\uid;
use function WPSB\Members\visible_logins;
use const WPSB\Members\VIEWERS;

class RestMatrixTest extends WPSB\TestCase {

	private const KEY_MEMBERS = array( 'alice', 'bob', 'carol', 'dave', 'erin', 'frank', 'henry' );

	private function as_viewer( string $login ): void {
		wp_set_current_user( uid( $login ) );
	}

	public function test_single_member_per_viewer(): void {
		foreach ( VIEWERS as $viewer => $class ) {
			$this->as_viewer( $viewer );
			foreach ( self::KEY_MEMBERS as $member ) {
				$res      = $this->rest( 'GET', '/acme-members/v1/members/' . uid( $member ) );
				$expected = expected( $member, $class );
				$label    = "GET /members/<$member> as " . ( $viewer ?: 'anonymous' );
				if ( null === $expected ) {
					$this->assertSame( 404, $res->get_status(), $label );
					$this->assertSame( 'acme_members_not_found', norm( $res->get_data() )['code'] ?? null, $label );
					continue;
				}
				$this->assertSame( 200, $res->get_status(), $label );
				$data = norm( $this->rest_data( $res ) );
				$this->assertSame( uid( $member ), $data['id'], $label );
				$this->assertSame( $member, $data['slug'], $label );
				$this->assertSame( home_url( '/members/' . $member . '/' ), $data['link'], $label );
				$this->assertSame( get_userdata( uid( $member ) )->display_name, $data['name'], $label );
				$this->assertSame( ksorted( $expected ), ksorted( (array) $data['fields'] ), $label );
				if ( 'all' === $class ) {
					$this->assertArrayHasKey( 'visibility', $data, "$label: admins see the settings" );
				} else {
					$this->assertArrayNotHasKey( 'visibility', $data, "$label: visibility settings are only for the member and admins" );
				}
			}
		}
	}

	public function test_effective_visibility_settings_for_admins(): void {
		$this->as_viewer( 'admin' );
		$data = norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members/' . uid( 'erin' ) ) ) );
		$this->assertSame( 'public', $data['visibility']['profile'] );
		$this->assertSame( 'private', $data['visibility']['fields']['phone'], '1.x "hide phone" is effective' );
		$this->assertSame( 'public', $data['visibility']['fields']['city'] );
		$this->assertCount( 6, $data['visibility']['fields'] );
		$data = norm( $this->rest_data( $this->rest( 'GET', '/acme-members/v1/members/' . uid( 'dave' ) ) ) );
		$this->assertSame( 'private', $data['visibility']['profile'], '1.x "hide profile" is effective' );
	}

	public function test_non_members_and_unknown_ids(): void {
		foreach ( array( '', 'admin' ) as $viewer ) {
			$this->as_viewer( $viewer );
			foreach ( array( uid( 'eddie' ), uid( 'admin' ), 999999 ) as $id ) {
				$res = $this->rest( 'GET', '/acme-members/v1/members/' . $id );
				$this->assertSame( 404, $res->get_status(), "user $id is not a member" );
				$this->assertSame( 'acme_members_not_found', norm( $res->get_data() )['code'] ?? null );
			}
		}
	}

	public function test_list_per_viewer(): void {
		foreach ( VIEWERS + array( 'frank' => 'members' ) as $viewer => $class ) {
			$this->as_viewer( $viewer );
			$res = $this->rest( 'GET', '/acme-members/v1/members', array( 'per_page' => 50 ) );
			$this->assertSame( 200, $res->get_status() );
			$data     = norm( $this->rest_data( $res ) );
			$expected = 'all' === $class ? array_merge( array( 'alice', 'bob', 'carol', 'dave', 'erin', 'frank', 'gina', 'henry' ), array_map( static fn( $i ) => sprintf( 'filler%02d', $i ), range( 1, 14 ) ) ) : visible_logins( $class, $viewer );
			$this->assertSame( $expected, array_column( $data, 'slug' ), 'List as ' . ( $viewer ?: 'anonymous' ) . ' (ordered by display name)' );
			$this->assertSame( (string) count( $expected ), (string) $res->get_headers()['X-WP-Total'] );
			foreach ( $data as $item ) {
				if ( in_array( $item['slug'], self::KEY_MEMBERS, true ) && $item['slug'] !== $viewer ) {
					$this->assertSame( ksorted( expected( $item['slug'], $class ) ), ksorted( (array) $item['fields'] ), "{$item['slug']} in the list as " . ( $viewer ?: 'anonymous' ) );
				}
				if ( 'frank' === $viewer && 'frank' === $item['slug'] ) {
					$this->assertSame( 'Freelance Welder', $item['fields']['job_title'] ?? null, 'Members see their own private profile' );
					$this->assertArrayHasKey( 'visibility', $item );
				}
			}
		}
	}

	public function test_list_pagination(): void {
		$res = $this->rest( 'GET', '/acme-members/v1/members', array( 'per_page' => 5, 'page' => 2 ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( array( 'filler01', 'filler02', 'filler03', 'filler04', 'filler05' ), array_column( norm( $this->rest_data( $res ) ), 'slug' ) );
		$this->assertSame( '19', (string) $res->get_headers()['X-WP-Total'] );
		$this->assertSame( '4', (string) $res->get_headers()['X-WP-TotalPages'] );

		$res = $this->rest( 'GET', '/acme-members/v1/members' );
		$this->assertCount( 10, norm( $this->rest_data( $res ) ), 'default per_page is 10' );

		$this->assertSame( 400, $this->rest( 'GET', '/acme-members/v1/members', array( 'per_page' => 51 ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'GET', '/acme-members/v1/members', array( 'per_page' => 0 ) )->get_status() );
	}

	private function search_slugs( string $viewer, array $query ): array {
		$this->as_viewer( $viewer );
		$res = $this->rest( 'GET', '/acme-members/v1/members', $query + array( 'per_page' => 50 ) );
		$this->assertSame( 200, $res->get_status() );
		return array_column( norm( $this->rest_data( $res ) ), 'slug' );
	}

	public function test_search_only_matches_what_the_viewer_may_see(): void {
		$this->assertSame( array( 'alice' ), $this->search_slugs( '', array( 'search' => 'ALICE' ) ) );
		$this->assertSame( array(), $this->search_slugs( '', array( 'search' => 'bobco' ) ), 'members-only profile' );
		$this->assertSame( array( 'bob' ), $this->search_slugs( 'gina', array( 'search' => 'bobco' ) ) );
		$this->assertSame( array(), $this->search_slugs( '', array( 'search' => 'Secret Corp' ) ), 'private field' );
		$this->assertSame( array(), $this->search_slugs( 'gina', array( 'search' => 'Secret Corp' ) ), 'private field' );
		$this->assertSame( array( 'carol' ), $this->search_slugs( 'admin', array( 'search' => 'secret corp' ) ) );
		$this->assertSame( array( 'carol' ), $this->search_slugs( '', array( 'search' => '341 555' ) ), 'public phone' );
		$this->assertSame( array(), $this->search_slugs( '', array( 'search' => '555 0101' ) ), 'members-only phone' );
		$this->assertSame( array(), $this->search_slugs( 'sue', array( 'search' => '555 0101' ) ), 'logged in is not enough' );
		$this->assertSame( array( 'alice' ), $this->search_slugs( 'gina', array( 'search' => '555 0101' ) ) );
		$this->assertSame( array(), $this->search_slugs( 'gina', array( 'search' => '555 0105' ) ), '1.x hidden phone' );
		$this->assertSame( array( 'erin' ), $this->search_slugs( '', array( 'search' => 'erin & co' ) ) );
		$this->assertSame( array(), $this->search_slugs( '', array( 'search' => 'tinkerer' ) ), '1.x hidden profile' );
		$this->assertSame( array( 'dave' ), $this->search_slugs( 'admin', array( 'search' => 'tinkerer' ) ) );
	}

	public function test_city_filter(): void {
		$this->assertSame( array( 'alice', 'henry' ), $this->search_slugs( '', array( 'city' => 'berlin' ) ) );
		$this->assertSame( array( 'alice', 'bob', 'henry' ), $this->search_slugs( 'gina', array( 'city' => 'Berlin' ) ) );
		$this->assertSame( array(), $this->search_slugs( '', array( 'city' => 'Leipzig' ) ), "Carol's city is members-only" );
		$this->assertSame( array( 'carol' ), $this->search_slugs( 'gina', array( 'city' => 'leipzig' ) ) );
		$this->assertSame( array( 'erin' ), $this->search_slugs( '', array( 'city' => 'Hamburg' ) ) );
		$this->assertSame( array( 'dave', 'erin' ), $this->search_slugs( 'admin', array( 'city' => 'Hamburg' ) ) );
		$this->assertSame( array(), $this->search_slugs( '', array( 'city' => 'Berl' ) ), 'exact match' );
	}

	public function test_me_read(): void {
		$res = $this->rest( 'GET', '/acme-members/v1/members/me' );
		$this->assertSame( 401, $res->get_status() );
		$this->as_viewer( 'sue' );
		$this->assertSame( 403, $this->rest( 'GET', '/acme-members/v1/members/me' )->get_status() );
		$this->as_viewer( 'frank' );
		$res = $this->rest( 'GET', '/acme-members/v1/members/me' );
		$this->assertSame( 200, $res->get_status() );
		$data = norm( $this->rest_data( $res ) );
		$this->assertSame( uid( 'frank' ), $data['id'] );
		$this->assertSame( ksorted( WPSB\Members\VALUES['frank'] ), ksorted( $data['fields'] ) );
		$this->assertSame( 'private', $data['visibility']['profile'] );
		$this->assertSame( 'members', $data['visibility']['fields']['phone'] );
	}

	public function test_core_users_endpoint(): void {
		$cases = array(
			array( '', 'alice' ),
			array( '', 'carol' ),
			array( '', 'dave' ),
			array( '', 'bob' ),
			array( 'sue', 'bob' ),
			array( 'gina', 'bob' ),
			array( 'gina', 'carol' ),
			array( 'gina', 'dave' ),
			array( 'admin', 'dave' ),
			array( 'admin', 'carol' ),
		);
		foreach ( $cases as list( $viewer, $member ) ) {
			$this->as_viewer( $viewer );
			$res = $this->rest( 'GET', '/wp/v2/users/' . uid( $member ) );
			$this->assertSame( 200, $res->get_status(), "$member is an author" );
			$data     = norm( $this->rest_data( $res ) );
			$expected = expected( $member, VIEWERS[ $viewer ] );
			$this->assertArrayHasKey( 'acme_profile', $data );
			$this->assertSame( ksorted( $expected ?? array() ), ksorted( (array) $data['acme_profile'] ), "acme_profile of $member as " . ( $viewer ?: 'anonymous' ) );
		}
		// Collection too.
		$this->as_viewer( '' );
		$all = norm( $this->rest_data( $this->rest( 'GET', '/wp/v2/users', array( 'per_page' => 100 ) ) ) );
		foreach ( $all as $user ) {
			$this->assertStringNotContainsString( 'Bobco', wp_json_encode( $user ) );
			$this->assertStringNotContainsString( 'Tinkerer', wp_json_encode( $user ) );
			$this->assertStringNotContainsString( 'Secret Corp', wp_json_encode( $user ) );
			$this->assertStringNotContainsString( '555 0101', wp_json_encode( $user ) );
		}
	}

	public function test_core_search_endpoint(): void {
		$search = function ( string $viewer, string $term ): array {
			$this->as_viewer( $viewer );
			$res = $this->rest( 'GET', '/wp/v2/search', array( 'type' => 'acme-member', 'search' => $term, 'per_page' => 50 ) );
			$this->assertSame( 200, $res->get_status() );
			return array_map( static fn( $r ) => $r['id'], norm( $this->rest_data( $res ) ) );
		};
		$this->assertSame( array(), $search( '', 'bobco' ) );
		$this->assertSame( array(), $search( '', 'Bob Builder' ), 'hidden profiles are not found by name either' );
		$this->assertSame( array( uid( 'bob' ) ), $search( 'gina', 'bobco' ) );
		$this->assertSame( array(), $search( '', 'Secret Corp' ) );
		$this->assertSame( array(), $search( 'gina', '555 0105' ) );
		$this->assertSame( array(), $search( '', 'Dave' ) );
		$this->assertSame( array( uid( 'dave' ) ), $search( 'admin', 'Dave' ) );
		$this->assertSame( array( uid( 'alice' ) ), $search( '', 'northwind' ) );
		$this->assertCount( 19, $search( '', '' ) );
		$this->assertCount( 22, $search( 'admin', '' ) );
	}
}
