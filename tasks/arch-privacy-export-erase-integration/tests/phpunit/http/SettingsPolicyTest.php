<?php
/**
 * Settings screen (retention field + existing settings), cron scheduling on an already-active
 * site and the privacy policy guide text, over HTTP.
 */

class SettingsPolicyTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved;

	protected function setUp(): void {
		parent::setUp();
		$this->saved = get_option( 'acme_loyalty_settings' );
	}

	protected function tearDown(): void {
		update_option( 'acme_loyalty_settings', $this->saved );
		parent::tearDown();
	}

	private function fresh_settings(): array {
		wp_cache_delete( 'acme_loyalty_settings', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return (array) get_option( 'acme_loyalty_settings' );
	}

	private function save( array $login, string $nonce, array $values ): array {
		return $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'           => 'acme-loyalty',
					'action'                => 'update',
					'_wpnonce'              => $nonce,
					'_wp_http_referer'      => '/wp-admin/admin.php?page=acme-loyalty',
					'acme_loyalty_settings' => $values,
				),
			)
		);
	}

	private function form_values( array $overrides ): array {
		return array_merge(
			array(
				'points_per_currency' => '3',
				'welcome_bonus'       => '75',
				'double_optin'        => '1',
				'store_names'         => "Downtown\nHarbour\nOnline",
			),
			$overrides
		);
	}

	public function test_cleanup_is_scheduled_daily_without_reactivation(): void {
		$this->assertSame( 200, $this->http( 'GET', '/' )['status'] );
		$res = $this->wp_cli( 'cron event list --fields=hook,recurrence --format=json' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		$events = array_values( array_filter( json_decode( $res['stdout'], true ) ?: array(), static fn( $e ) => 'acme_loyalty_retention_cleanup' === $e['hook'] ) );
		$this->assertCount( 1, $events, 'acme_loyalty_retention_cleanup must be scheduled exactly once' );
		$this->assertMatchesRegularExpression( '/^1 day$/', $events[0]['recurrence'] );

		// Loading more pages doesn't schedule it again.
		$this->http( 'GET', '/newsletter/' );
		$res    = $this->wp_cli( 'cron event list --fields=hook --format=json' );
		$events = array_filter( json_decode( $res['stdout'], true ) ?: array(), static fn( $e ) => 'acme_loyalty_retention_cleanup' === $e['hook'] );
		$this->assertCount( 1, $events );
	}

	public function test_retention_setting_on_the_settings_screen(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$page  = $this->http( 'GET', '/wp-admin/admin.php?page=acme-loyalty', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/name="acme_loyalty_settings\[retention_months\]"[^>]*value="24"|value="24"[^>]*name="acme_loyalty_settings\[retention_months\]"/', $page['body'], 'Retention field with the default 24' );
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'] );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $page['body'], $m );

		$res = $this->save( $login, $m[1], $this->form_values( array( 'retention_months' => '18' ) ) );
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$saved = $this->fresh_settings();
		$this->assertSame( 18, (int) $saved['retention_months'] );
		$this->assertSame( 3, (int) $saved['points_per_currency'], 'Existing settings must still be saved' );
		$this->assertSame( 75, (int) $saved['welcome_bonus'] );
		$this->assertSame( array( 'Downtown', 'Harbour', 'Online' ), $saved['store_names'] );

		foreach ( array( '999', '-5', 'abc', '12.5', '121' ) as $bad ) {
			$res = $this->save( $login, $m[1], $this->form_values( array( 'retention_months' => $bad, 'welcome_bonus' => '80' ) ) );
			$this->assertContains( $res['status'], array( 302, 303 ) );
			$saved = $this->fresh_settings();
			$this->assertSame( 18, (int) $saved['retention_months'], "Invalid value '$bad' must be rejected and the previous value kept" );
			$this->assertSame( 80, (int) $saved['welcome_bonus'] );
		}
		// The rejection is reported on the screen.
		$page = $this->http( 'GET', '/wp-admin/admin.php?page=acme-loyalty&settings-updated=true', array( 'login' => $login ) );
		$this->assertMatchesRegularExpression( '/notice-error|settings-error error|class="error/', $page['body'], 'Expected an error message for the rejected value' );

		$res = $this->save( $login, $m[1], $this->form_values( array( 'retention_months' => '0' ) ) );
		$this->assertSame( 0, (int) $this->fresh_settings()['retention_months'] );
		$this->assertArrayHasKey( 'retention_months', $this->fresh_settings() );

		// Editors can't change it.
		$editor = get_user_by( 'login', 'barista' )->ID;
		$elogin = $this->http_login( $editor );
		$this->save( $elogin, $this->nonce_for( $editor, 'acme-loyalty-options', $elogin['logged_in'] ), $this->form_values( array( 'retention_months' => '6' ) ) );
		$this->assertSame( 0, (int) $this->fresh_settings()['retention_months'] );
	}

	public function test_privacy_policy_guide(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );

		$page = $this->http( 'GET', '/wp-admin/privacy-policy-guide.php', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'Acme Loyalty', $page['body'] );
		$this->assertStringContainsString( 'after 24 months', $page['body'] );
		$this->assertMatchesRegularExpression( '/accounting/i', $page['body'] );

		$settings                     = $this->saved;
		$settings['retention_months'] = 18;
		update_option( 'acme_loyalty_settings', $settings );
		$page = $this->http( 'GET', '/wp-admin/privacy-policy-guide.php', array( 'login' => $login ) );
		$this->assertStringContainsString( 'after 18 months', $page['body'] );
		$this->assertStringNotContainsString( 'after 24 months', $page['body'] );
	}
}
