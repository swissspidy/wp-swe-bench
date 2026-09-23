<?php
/**
 * Existing behaviour that must keep working (pass-to-pass).
 */

class ExistingFeaturesTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_account_page_for_2x_and_1x_members(): void {
		$jane  = get_user_by( 'login', 'jane' )->ID;
		$login = $this->http_login( $jane );
		$res   = $this->http( 'GET', '/my-loyalty/', array( 'login' => $login ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Tier: Gold', $res['body'] );
		$this->assertMatchesRegularExpression( '/Your balance: <strong>[\d,]+ points<\/strong>/', $res['body'] );
		$this->assertSame( 20, substr_count( $res['body'], '<tr>' ) - 1, 'History shows the last 20 entries' );
		$this->assertMatchesRegularExpression( '/value="sms"\s+checked/', $res['body'] );

		$marco = get_user_by( 'login', 'marco' )->ID;
		$res   = $this->http( 'GET', '/my-loyalty/', array( 'login' => $this->http_login( $marco ) ) );
		$this->assertMatchesRegularExpression( '/value="post"\s+checked/', $res['body'], '1.x preferences are read' );
		$this->assertStringContainsString( 'value="1979-09-23"', $res['body'], '1.x birthday is read' );
		$this->assertMatchesRegularExpression( '/value="Harbour"\s+selected/', $res['body'] );

		$res = $this->http( 'GET', '/my-loyalty/' );
		$this->assertStringContainsString( 'Please log in to see your loyalty points.', $res['body'] );
	}

	public function test_newsletter_signup(): void {
		global $wpdb;
		$page = $this->http( 'GET', '/newsletter/' );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/name="acme_newsletter_nonce" value="([a-f0-9]+)"/', $page['body'] );
		preg_match( '/name="acme_newsletter_nonce" value="([a-f0-9]+)"/', $page['body'], $m );
		$this->clear_mails();
		$res = $this->http(
			'POST',
			'/wp-admin/admin-post.php',
			array(
				'headers' => array( 'Referer' => home_url( '/newsletter/' ) ),
				'body'    => array(
					'action'                => 'acme_loyalty_subscribe',
					'acme_newsletter_nonce' => $m[1],
					'email'                 => 'New.Reader@Example.org',
					'first_name'            => 'Nia',
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'acme_loyalty_subscribers WHERE email = %s', 'new.reader@example.org' ), ARRAY_A );
		$this->assertNotNull( $row );
		$this->assertSame( 'pending', $row['status'] );
		$mails = $this->mails();
		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'acme_confirm=', $mails[0]['message'] );
		$wpdb->delete( $wpdb->prefix . 'acme_loyalty_subscribers', array( 'id' => $row['id'] ) );
	}

	public function test_core_exporters_are_still_registered(): void {
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		$this->assertArrayHasKey( 'wordpress-user', $exporters );
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		$this->assertArrayHasKey( 'wordpress-comments', $erasers );
	}
}
