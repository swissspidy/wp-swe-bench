<?php
/**
 * Existing behaviour that must keep working (passes on 2.3.1 too).
 */

class AdminAndFrontEndTest extends AcmeRedirectsCase {

	private function admin_login(): array {
		return $this->http_login( (int) get_user_by( 'login', 'admin' )->ID );
	}

	public function test_front_end_redirects_and_hit_counters(): void {
		$this->assertFrontRedirect( '/old-about/', 301, self::h( '/about/' ) );
		$this->assertFrontRedirect( '/OLD-contact', 301, self::h( '/contact/' ) );
		$this->assertFrontRedirect( '/shop/shoes?x=1', 301, self::h( '/store/shoes?x=1' ) );
		$this->assertFrontRedirect( '/shop/sale/now', 302, self::h( '/deals/' ) );
		$this->assertFrontRedirect( '/blog/2020/05/hello-world/', 301, self::h( '/news/hello-world/' ) );
		$this->assertFrontRedirect( '/products/Foo', 308, 'https://shop.example.com/p/Foo?ref=acme' );
		$this->assertFrontRedirect( '/docs/v1/auth', 301, self::h( '/help/legacy/auth' ) );
		$this->assertSame( 410, $this->front( '/retired-product' )['status'] );
		$this->assertNoFrontRedirect( '/disabled-page' );
		$this->assertNoFrontRedirect( '/promo?preview=1' );

		$rows = $this->rows();
		$this->assertSame( 13, (int) $rows[1]['hits'] );
		$this->assertSame( 4, (int) $rows[2]['hits'] );
		$this->assertSame( 10, (int) $rows[8]['hits'] );
		$this->assertSame( 2, (int) $rows[10]['hits'] );
		$this->assertGreaterThanOrEqual( gmdate( 'Y-m-d H:i:s', time() - 120 ), $rows[1]['last_hit'] );
	}

	public function test_admin_screen_lists_rules(): void {
		$r = $this->http( 'GET', '/wp-admin/tools.php?page=acme-redirects', array( 'login' => $this->admin_login() ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertStringContainsString( 'id="rule-1"', $r['body'] );
		$this->assertStringContainsString( '/old-contact', $r['body'] );
		$this->assertStringContainsString( 'data-hits="981"', $r['body'] );

		$editor = $this->http_login( (int) get_user_by( 'login', 'eddie' )->ID );
		$r      = $this->http( 'GET', '/wp-admin/tools.php?page=acme-redirects', array( 'login' => $editor ) );
		$this->assertNotSame( 200, $r['status'], 'editors cannot manage redirects' );
	}

	public function test_admin_form_creates_and_edits_rules(): void {
		$admin_id = (int) get_user_by( 'login', 'admin' )->ID;
		$login    = $this->admin_login();
		$nonce    = $this->nonce_for( $admin_id, 'acme_redirects_save', $login['logged_in'] );
		$body     = array(
			'action'     => 'acme_redirects_save',
			'_wpnonce'   => $nonce,
			'id'         => 0,
			'source'     => '/from-admin',
			'target'     => '/to-admin/',
			'match_type' => 'exact',
			'status'     => 307,
			'priority'   => 4,
			'enabled'    => 1,
			'note'       => 'via form',
		);
		$r        = $this->http( 'POST', '/wp-admin/admin-post.php', array( 'login' => $login, 'body' => $body ) );
		$this->assertSame( 302, $r['status'] );
		$this->assertStringContainsString( 'saved=1', $r['headers']['location'] ?? '' );
		$rows = $this->rows_by_source( '/from-admin' );
		$this->assertCount( 1, $rows );
		$this->assertFrontRedirect( '/from-admin', 307, self::h( '/to-admin/' ) );

		// Duplicate is rejected.
		$r = $this->http( 'POST', '/wp-admin/admin-post.php', array( 'login' => $login, 'body' => $body ) );
		$this->assertStringContainsString( 'action=new', $r['headers']['location'] ?? '' );
		$this->assertCount( 1, $this->rows_by_source( '/from-admin' ) );

		// Edit.
		$body['id']     = (int) $rows[0]['id'];
		$body['target'] = '/to-admin-2/';
		$this->http( 'POST', '/wp-admin/admin-post.php', array( 'login' => $login, 'body' => $body ) );
		$this->assertFrontRedirect( '/from-admin', 307, self::h( '/to-admin-2/' ) );
		$this->assertContains( 'update:' . $rows[0]['id'], $this->purge_log() );
	}

	public function test_admin_export(): void {
		$csv  = $this->admin_export();
		$rows = self::csv_rows( $csv );
		$this->assertSame( array( 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'note' ), $rows[0] );
		$this->assertCount( 19, $rows );
		$this->assertSame( array( '/shop/sale', '/deals/', 'prefix', '302', '5', 'yes', 'Sale section' ), $rows[1] );
		$this->assertSame( array( '/old-contact', '/contact/', 'exact', '301', '10', 'yes', '' ), $rows[3] );
	}
}
