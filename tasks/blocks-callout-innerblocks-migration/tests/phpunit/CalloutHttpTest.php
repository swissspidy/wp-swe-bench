<?php
/**
 * Requests against the real (Playground) web server: front end + settings screen.
 */

use function WPSB\Callouts\callouts;

class CalloutHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_options;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_options = get_option( 'acme_callouts_options' );
	}

	protected function tearDown(): void {
		update_option( 'acme_callouts_options', $this->saved_options );
		parent::tearDown();
	}

	public function test_front_end_served_by_playground_uses_new_markup(): void {
		$res = $this->http( 'GET', '/legacy-v0-callout/' );
		$this->assertSame( 200, $res['status'] );
		$all = callouts( $res['body'] );
		$this->assertCount( 1, $all, 'Expected one callout on the served page' );
		$this->assertSame( 'aside', $all[0]['tag'] );
		$this->assertContains( 'is-type-warning', $all[0]['classes'] );
		$this->assertSame( 'Heads up', trim( (string) $all[0]['title'] ) );
		$this->assertStringContainsString( 'read the docs', (string) $all[0]['body_html'] );
		$this->assertStringNotContainsString( 'callout-warning', $res['body'] );

		$res = $this->http( 'GET', '/v1-callout/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertCount( 2, callouts( $res['body'] ) );
		$this->assertStringNotContainsString( 'acme-callout__content', $res['body'] );
	}

	public function test_disabling_the_shortcode_setting_still_works(): void {
		update_option( 'acme_callouts_options', array( 'default_type' => 'success', 'enable_shortcode' => false ) );
		$res = $this->http( 'GET', '/shortcode-callout/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertCount( 0, callouts( $res['body'] ), 'Shortcodes must not render when disabled in the settings' );
		$this->assertStringContainsString( '[callout', $res['body'] );

		// Block callouts are unaffected by the shortcode setting.
		$res = $this->http( 'GET', '/v1-callout/' );
		$this->assertCount( 2, callouts( $res['body'] ) );
	}

	public function test_settings_screen_saves_and_sanitizes(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );

		$page = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-callouts', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'] );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'], $m );
		$this->assertStringContainsString( 'value="tip"', $page['body'], 'Custom types must be selectable as the default type' );

		$res = $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'      => 'acme-callouts',
					'action'           => 'update',
					'_wpnonce'         => $m[1],
					'_wp_http_referer' => '/wp-admin/options-general.php?page=acme-callouts',
					'acme_callouts_options' => array( 'default_type' => 'tip', 'enable_shortcode' => '1' ),
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ) );
		wp_cache_delete( 'acme_callouts_options', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertSame( array( 'default_type' => 'tip', 'enable_shortcode' => true ), get_option( 'acme_callouts_options' ) );

		$res = $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'      => 'acme-callouts',
					'action'           => 'update',
					'_wpnonce'         => $m[1],
					'_wp_http_referer' => '/wp-admin/options-general.php?page=acme-callouts',
					'acme_callouts_options' => array( 'default_type' => '<b>bogus</b>' ),
				),
			)
		);
		wp_cache_delete( 'acme_callouts_options', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertSame( array( 'default_type' => 'info', 'enable_shortcode' => false ), get_option( 'acme_callouts_options' ) );

		// Shortcode without a type now uses the saved default.
		update_option( 'acme_callouts_options', array( 'default_type' => 'danger', 'enable_shortcode' => true ) );
		$res = $this->http( 'GET', '/shortcode-callout/' );
		$all = callouts( $res['body'] );
		$this->assertCount( 2, $all );
		$this->assertContains( 'is-type-danger', $all[1]['classes'] );
	}
}
