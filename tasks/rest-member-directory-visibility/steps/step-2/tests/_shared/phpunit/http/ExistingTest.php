<?php
/**
 * What already worked must keep working (pass-to-pass): profile pages, the anonymous directory,
 * the wp-admin profile section.
 */

use function WPSB\Members\cards;
use function WPSB\Members\expected;
use function WPSB\Members\uid;

class ExistingTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function profile_fields( string $html ): array {
		$x   = WPSB\Members\xpath( $html );
		$out = array();
		foreach ( $x->query( '//*[' . WPSB\Members\cls( 'acme-member-field' ) . ']' ) as $node ) {
			if ( preg_match( '/acme-member-field--([a-z_]+)/', $node->getAttribute( 'class' ), $m ) ) {
				$out[ $m[1] ] = trim( $x->query( './/dd', $node )->item( 0 )->textContent );
			}
		}
		ksort( $out );
		return $out;
	}

	public function test_profile_pages(): void {
		$res = $this->http( 'GET', '/members/alice/' );
		$this->assertSame( 200, $res['status'] );
		$exp = expected( 'alice', 'public' );
		ksort( $exp );
		$this->assertSame( $exp, $this->profile_fields( $res['body'] ) );

		$this->assertSame( 404, $this->http( 'GET', '/members/bob/' )['status'] );
		$this->assertSame( 404, $this->http( 'GET', '/members/dave/' )['status'] );
		$this->assertSame( 404, $this->http( 'GET', '/members/eddie/' )['status'] );

		$gina = $this->http_login( uid( 'gina' ) );
		$res  = $this->http( 'GET', '/members/bob/', array( 'login' => $gina ) );
		$this->assertSame( 200, $res['status'] );
		$exp = expected( 'bob', 'members' );
		ksort( $exp );
		$this->assertSame( $exp, $this->profile_fields( $res['body'] ) );

		$admin = $this->http_login( uid( 'admin' ) );
		$res   = $this->http( 'GET', '/members/dave/', array( 'login' => $admin ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Chief Tinkerer', $res['body'] );

		$res = $this->http( 'GET', '/members/erin/' );
		$this->assertStringContainsString( 'Erin &amp; Co', $res['body'] );
		$this->assertStringNotContainsString( '555 0105', $res['body'] );
	}

	public function test_directory_for_anonymous_visitors(): void {
		$res = $this->http( 'GET', '/members/' );
		$this->assertSame( 200, $res['status'] );
		$cards = cards( $res['body'] );
		$this->assertCount( 19, $cards );
		$this->assertStringContainsString( '19 members', $res['body'] );
		$this->assertSame( array( 'job_title' => 'Product Designer', 'phone' => '+49 341 555 0103' ), $cards[ uid( 'carol' ) ] );

		$res = $this->http( 'GET', '/berlin-members/' );
		$this->assertSame( array( uid( 'alice' ), uid( 'henry' ) ), array_keys( cards( $res['body'] ) ) );
	}

	public function test_admin_profile_section_saves(): void {
		$admin = $this->http_login( uid( 'admin' ) );
		$henry = uid( 'henry' );
		$page  = $this->http( 'GET', '/wp-admin/user-edit.php?user_id=' . $henry, array( 'login' => $admin ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'name="acme_member[job_title]"', $page['body'] );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'], $m );
		$this->assertNotEmpty( $m );
		$user = get_userdata( $henry );
		$res  = $this->http(
			'POST',
			'/wp-admin/user-edit.php',
			array(
				'login' => $admin,
				'body'  => array(
					'_wpnonce'                     => $m[1],
					'action'                       => 'update',
					'user_id'                      => $henry,
					'email'                        => $user->user_email,
					'nickname'                     => $user->nickname,
					'display_name'                 => $user->display_name,
					'acme_member'                  => array( 'job_title' => 'Master Woodworker', 'city' => 'Berlin' ),
					'acme_member_visibility'       => 'public',
					'acme_member_field_visibility' => array( 'city' => 'members' ),
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), substr( $res['body'], 0, 500 ) );
		$res = $this->http( 'GET', '/members/henry/' );
		$this->assertStringContainsString( 'Master Woodworker', $res['body'] );
		$this->assertStringNotContainsString( 'acme-member-field--city', $res['body'] );
	}
}
