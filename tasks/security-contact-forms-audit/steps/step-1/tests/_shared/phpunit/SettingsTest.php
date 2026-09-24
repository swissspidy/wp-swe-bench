<?php
/**
 * F-2 (settings): the settings screen saves for administrators, and only from that screen.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\forge_tokens;
use function WPSB\Forms\settings;

class SettingsTest extends HttpCase {

	private function settings_form( string $login = 'admin' ): array {
		$res = $this->get( $login, '/wp-admin/admin.php?page=acme-forms-settings' );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		return $this->find_form( $res['body'], ".//input[@name='acme_forms_settings[notify_email]' or @name='notify_email']" );
	}

	private function email_key( array $values ): string {
		return array_key_exists( 'acme_forms_settings[notify_email]', $values ) ? 'acme_forms_settings[notify_email]' : 'notify_email';
	}

	public function test_settings_screen_saves(): void {
		$form = $this->settings_form();
		$this->assertSame( 'jobs@acme-recruiting.example', $form['values'][ $this->email_key( $form['values'] ) ] );

		$values                                  = $form['values'];
		$values[ $this->email_key( $values ) ]   = 'people@acme-recruiting.example';
		foreach ( array_keys( $values ) as $name ) {
			if ( false !== strpos( $name, 'store_ip' ) ) {
				unset( $values[ $name ] ); // Uncheck.
			}
			if ( false !== strpos( $name, 'subject_prefix' ) ) {
				$values[ $name ] = '[Careers]';
			}
		}
		$res = $this->submit( 'admin', $form['method'], $form['action'], $values );
		$this->assertContains( $res['status'], array( 200, 302, 303 ), substr( $res['body'], 0, 300 ) );

		wp_cache_flush();
		$saved = settings();
		$this->assertSame( 'people@acme-recruiting.example', $saved['notify_email'] );
		$this->assertSame( '[Careers]', $saved['subject_prefix'] );
		$this->assertEmpty( $saved['store_ip'] );
		$this->assertEquals( 5, $saved['max_upload_mb'] );
		$this->assertEquals( 20, $saved['per_page'] );

		// The screen shows the new values.
		$form = $this->settings_form();
		$this->assertSame( 'people@acme-recruiting.example', $form['values'][ $this->email_key( $form['values'] ) ] );
	}

	public function test_settings_only_change_from_the_settings_screen(): void {
		$before = settings();

		// Hand-made request (third-party page), no token.
		$res = $this->submit(
			'admin',
			'POST',
			rtrim( WP_HOME, '/' ) . '/wp-admin/admin-post.php',
			array(
				'action'                              => 'acme_forms_save_settings',
				'acme_forms_settings[notify_email]'   => 'attacker@evil.example',
				'acme_forms_settings[subject_prefix]' => 'x',
				'acme_forms_settings[max_upload_mb]'  => '64',
			)
		);
		$this->assertContains( $res['status'], array( 400, 403 ), 'no token' );
		wp_cache_flush();
		$this->assertSame( $before, settings(), 'no token: settings unchanged' );

		// The real form with forged token(s).
		$form   = $this->settings_form();
		$values = forge_tokens( $form['values'] );
		$values[ $this->email_key( $values ) ] = 'attacker@evil.example';
		$res = $this->submit( 'admin', $form['method'], $form['action'], $values );
		$this->assertSame( 403, $res['status'], 'forged token' );
		wp_cache_flush();
		$this->assertSame( $before, settings(), 'forged token: settings unchanged' );
	}

	public function test_editors_cannot_change_settings(): void {
		$before = settings();
		$res    = $this->get( 'erin', '/wp-admin/admin.php?page=acme-forms-settings' );
		$this->assertSame( 403, $res['status'], 'editors cannot open the settings' );

		// Replay the administrator's form with the editor's session.
		$form   = $this->settings_form();
		$values = $form['values'];
		$values[ $this->email_key( $values ) ] = 'attacker@evil.example';
		$res = $this->submit( 'erin', $form['method'], $form['action'], $values );
		$this->assertContains( $res['status'], array( 400, 403 ) );
		wp_cache_flush();
		$this->assertSame( $before, settings() );
	}
}
