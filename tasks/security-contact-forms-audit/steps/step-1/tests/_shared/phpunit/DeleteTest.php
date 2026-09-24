<?php
/**
 * F-2 (delete): row action, single-view link and bulk action work from the inbox, and only from there.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\absolute;
use function WPSB\Forms\count_rows;
use function WPSB\Forms\forge_tokens;
use function WPSB\Forms\forge_url_tokens;
use function WPSB\Forms\row;
use function WPSB\Forms\sub_id;
use function WPSB\Forms\xpath;

class DeleteTest extends HttpCase {

	public function test_row_action_and_single_view_link_delete(): void {
		$id   = sub_id( 'visitor05@example.org' );
		$list = $this->inbox( 'erin', array( 's' => 'visitor05@example.org' ) );
		$link = $this->row_delete_link( $list['body'], $id );
		$res  = $this->http( 'GET', $link, array( 'login' => $this->session( 'erin' ), 'follow' => true ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertNull( row( $id ), 'deleted through the row action' );

		$id   = sub_id( 'visitor06@example.org' );
		$view = $this->view( 'admin', $id );
		$xp   = xpath( $view['body'] );
		$a    = $xp->query( "//div[contains(@class,'wrap')]//a[normalize-space(.)='Delete']" )->item( 0 );
		$this->assertNotNull( $a, 'Delete link on the single view' );
		$res = $this->http( 'GET', absolute( $a->getAttribute( 'href' ) ), array( 'login' => $this->session( 'admin' ), 'follow' => true ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertNull( row( $id ), 'deleted through the single view' );
		$this->assertSame( 43, count_rows() );
	}

	public function test_row_delete_only_from_the_inbox(): void {
		$id = sub_id( 'visitor07@example.org' );

		// The link as it was before (what a third-party page would use).
		$this->get( 'admin', '/wp-admin/admin.php?page=acme-forms-submissions&action=delete&submission=' . $id );
		$this->assertNotNull( row( $id ), 'plain link must not delete' );
		$this->get( 'erin', '/wp-admin/admin.php?page=acme-forms-submissions&action=delete&submission=' . $id . '&_wpnonce=deadbeef00' );
		$this->assertNotNull( row( $id ), 'made-up token must not delete' );

		// The real link with its token(s) replaced.
		$link = $this->row_delete_link( $this->inbox( 'admin', array( 's' => 'visitor07@example.org' ) )['body'], $id );
		$res  = $this->http( 'GET', forge_url_tokens( $link ), array( 'login' => $this->session( 'admin' ) ) );
		$this->assertSame( 403, $res['status'] );
		$this->assertNotNull( row( $id ) );

		// Another user's link does not work for a different session either.
		$res = $this->http( 'GET', $link, array( 'login' => $this->session( 'erin' ) ) );
		$this->assertSame( 403, $res['status'], 'token of another user' );
		$this->assertNotNull( row( $id ) );
		$this->assertSame( 45, count_rows() );
	}

	public function test_bulk_delete_from_the_inbox(): void {
		$one  = sub_id( 'visitor01@example.org' );
		$two  = sub_id( 'visitor02@example.org' );
		$form = $this->list_form( $this->inbox( 'admin', array( 's' => 'visitor0' ) )['body'] );
		$form['values']['submission[]'] = array( (string) $one, (string) $two );
		$res = $this->submit( 'admin', $form['method'], $form['action'], $form['values'] );
		$this->assertContains( $res['status'], array( 200, 302, 303 ), substr( $res['body'], 0, 300 ) );
		$this->assertNull( row( $one ) );
		$this->assertNull( row( $two ) );
		$this->assertNotNull( row( sub_id( 'visitor03@example.org' ) ) );
		$this->assertSame( 43, count_rows() );
	}

	public function test_bulk_delete_only_from_the_inbox(): void {
		$ids = array( (string) sub_id( 'visitor03@example.org' ), (string) sub_id( 'visitor04@example.org' ) );

		// Hand-made request without any token (third-party page).
		$this->submit(
			'erin',
			'GET',
			rtrim( WP_HOME, '/' ) . '/wp-admin/admin.php',
			array(
				'page'         => 'acme-forms-submissions',
				'action'       => 'delete',
				'submission[]' => $ids,
			)
		);
		$this->submit(
			'erin',
			'POST',
			rtrim( WP_HOME, '/' ) . '/wp-admin/admin.php?page=acme-forms-submissions',
			array(
				'page'         => 'acme-forms-submissions',
				'action'       => 'delete',
				'action2'      => 'delete',
				'submission[]' => $ids,
			)
		);
		$this->assertSame( 45, count_rows(), 'no token: nothing deleted' );

		// The real form with forged token(s).
		$form = $this->list_form( $this->inbox( 'erin', array( 's' => 'visitor0' ) )['body'] );
		$form['values']['submission[]'] = $ids;
		$res = $this->submit( 'erin', $form['method'], $form['action'], forge_tokens( $form['values'] ) );
		$this->assertSame( 403, $res['status'] );
		$this->assertSame( 45, count_rows(), 'forged token: nothing deleted' );
	}

	public function test_accounts_without_inbox_access_cannot_delete(): void {
		$id = sub_id( 'visitor08@example.org' );
		foreach ( array( 'sally', 'arthur' ) as $login ) {
			$res = $this->get( $login, '/wp-admin/admin.php?page=acme-forms-submissions&action=delete&submission=' . $id );
			$this->assertSame( 403, $res['status'], $login );
		}
		$this->assertNotNull( row( $id ) );
		$this->assertSame( 45, count_rows() );
	}
}
