<?php
/**
 * F-4: the dashboard widget endpoint serves inbox users only, and only through the dashboard.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\sub_id;

class WidgetAjaxTest extends HttpCase {

	/** Token + action as the dashboard gives them to the widget script. */
	private function dashboard_config( string $login ): array {
		$res = $this->get( $login, '/wp-admin/index.php' );
		$this->assertSame( 200, $res['status'] );
		$this->assertPresent( 'Latest form submissions', $res['body'], "$login sees the widget" );
		$this->assertMatchesRegularExpression( '/acmeFormsWidget\s*=\s*(\{.*?\});/s', $res['body'], 'widget config printed on the dashboard' );
		preg_match( '/acmeFormsWidget\s*=\s*(\{.*?\});/s', $res['body'], $m );
		$config = json_decode( $m[1], true );
		$this->assertIsArray( $config );
		return $config;
	}

	private function call( ?string $login, array $params, $token ): array {
		if ( null !== $token ) {
			$params += array(
				'nonce'       => $token,
				'_ajax_nonce' => $token,
				'_wpnonce'    => $token,
			);
		}
		return $this->get( $login, '/wp-admin/admin-ajax.php?' . http_build_query( array( 'action' => 'acme_forms_widget' ) + $params ) );
	}

	private function minted( string $login ): string {
		$session = $this->session( $login );
		return $this->nonce_for( $session['user_id'], 'acme_forms_widget', $session['logged_in'] );
	}

	public function test_widget_works_for_editors_and_administrators(): void {
		global $wpdb;
		foreach ( array( 'erin', 'admin' ) as $login ) {
			$config = $this->dashboard_config( $login );
			$token  = $config['nonce'] ?? $this->minted( $login );

			$res = $this->call( $login, array( 'limit' => 3 ), $token );
			$this->assertSame( 200, $res['status'], "$login list: " . substr( $res['body'], 0, 300 ) );
			$this->assertTrue( $res['json']['success'] ?? false );
			$items = $res['json']['data']['submissions'];
			$this->assertCount( 3, $items );
			$newest = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}acme_form_submissions ORDER BY created_at DESC, id DESC LIMIT 3" );
			$this->assertEquals( array_map( 'intval', $newest ), array_column( $items, 'id' ), 'newest first' );
			foreach ( array( 'id', 'form_id', 'form', 'created', 'email', 'status', 'summary' ) as $key ) {
				$this->assertArrayHasKey( $key, $items[0] );
			}

			$res = $this->call( $login, array( 'submission' => sub_id( 'anna@example.org' ) ), $token );
			$this->assertSame( 200, $res['status'] );
			$detail = $res['json']['data']['submission'];
			$this->assertSame( 'Job application', $detail['form'] );
			$this->assertSame( 'anna@example.org', $detail['email'] );
			$values = array_column( $detail['fields'], 'value', 'label' );
			$this->assertSame( 'Anna Andersson', $values['Full name'] );
			$this->assertSame( 'cv-anna.pdf', $values['CV'] );
			$this->assertSame( 'https://anna.example', $values['Portfolio website'] );
		}
	}

	public function test_accounts_without_inbox_access_get_nothing(): void {
		$anna = sub_id( 'anna@example.org' );
		foreach ( array( 'sally', 'connie', 'arthur' ) as $login ) {
			$token = $this->minted( $login );
			$res   = $this->call( $login, array( 'limit' => 20 ), $token );
			$this->assertSame( 403, $res['status'], "$login list: " . substr( $res['body'], 0, 200 ) );
			$this->assertNotSame( true, $res['json']['success'] ?? null );
			$this->assertNoSubmissionData( $res, "$login list" );

			$res = $this->call( $login, array( 'submission' => $anna ), $token );
			$this->assertSame( 403, $res['status'], "$login single" );
			$this->assertNoSubmissionData( $res, "$login single" );
		}
	}

	public function test_logged_out_visitors_get_nothing(): void {
		$res = $this->call( null, array( 'limit' => 20 ), null );
		$this->assertContains( $res['status'], array( 400, 401, 403 ) );
		$this->assertNoSubmissionData( $res, 'logged out' );
		$res = $this->call( null, array( 'submission' => sub_id( 'anna@example.org' ) ), 'deadbeef00' );
		$this->assertContains( $res['status'], array( 400, 401, 403 ) );
		$this->assertNoSubmissionData( $res, 'logged out single' );
	}

	public function test_requests_not_made_by_the_dashboard_are_refused(): void {
		$anna = sub_id( 'anna@example.org' );
		foreach ( array( null, 'deadbeef00', $this->minted( 'sally' ) ) as $i => $token ) {
			$res = $this->call( 'erin', array( 'limit' => 20 ), $token );
			$this->assertSame( 403, $res['status'], "variant $i list: " . substr( $res['body'], 0, 200 ) );
			$this->assertNoSubmissionData( $res, "variant $i list" );
			$res = $this->call( 'admin', array( 'submission' => $anna ), $token );
			$this->assertSame( 403, $res['status'], "variant $i single" );
			$this->assertNoSubmissionData( $res, "variant $i single" );
		}
	}
}
