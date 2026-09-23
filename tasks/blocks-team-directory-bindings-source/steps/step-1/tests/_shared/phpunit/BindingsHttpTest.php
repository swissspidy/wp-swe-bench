<?php
/**
 * Pages served by the real web server.
 */

use function WPSB\Team\texts;

class BindingsHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_served_pages(): void {
		$res = $this->http( 'GET', '/meet-ada/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Head of Engineering', $res['body'] );
		$this->assertStringContainsString( 'href="mailto:ada@acme.test"', $res['body'] );

		$res = $this->http( 'GET', '/team/ada-lovelace/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertContains( 'Head of Engineering', texts( $res['body'], '//p' ) );
		$this->assertContains( 'she/her', texts( $res['body'], '//p' ) );

		$res = $this->http( 'GET', '/our-team/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertContains( 'Kernel Maintainer', texts( $res['body'], '//p' ) );
	}

	public function test_logged_in_admin_sees_no_internal_members(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$res   = $this->http( 'GET', '/internal-members/', array( 'login' => $login ) );
		$this->assertSame( 200, $res['status'] );
		foreach ( array( 'Secret Project Lead', 'Acquisition Target', '010 9999', 'Board Member', 'Incoming CFO' ) as $secret ) {
			$this->assertStringNotContainsString( $secret, $res['body'] );
		}
		wp_delete_user( $admin );
	}
}
