<?php
/**
 * Only allowed users, submitting the real screens, can change settings.
 */

class SecurityTest extends AcmeSocialCase {

	private function snapshot_state(): array {
		wp_cache_flush();
		return array( $this->stored(), $this->legacy_rows() );
	}

	private function assertUnchanged( array $before, string $message ): void {
		wp_cache_flush();
		$this->assertSettings( $before[0], $this->stored(), $message );
		$this->assertSame( $before[1], $this->legacy_rows(), $message . ' (old option rows)' );
	}

	/** Nonce the given user would get for the form (if the form uses the standard options flow). */
	private function nonce_for_form( array $form, string $user, array $login ): ?string {
		foreach ( $form['fields'] as $f ) {
			if ( 'option_page' === $f['name'] ) {
				return $this->nonce_for( 'admin' === $user ? 1 : $this->user_id( $user ), $f['value'] . '-options', $login['logged_in'] );
			}
		}
		return null;
	}

	public function test_forged_submissions_change_nothing(): void {
		$before = $this->snapshot_state();
		$admin  = $this->login( 'admin' );
		$set    = array(
			'acme_social_settings[twitter]'  => 'Hacked',
			'acme_social_settings[facebook]' => 'https://evil.example/',
		);

		$form = $this->get_form( self::screen( 'acme-social-profiles' ), $admin );
		$nonce_fields = array_values( array_filter( array_column( $form['fields'], 'name' ), static fn( $n ) => ! str_starts_with( $n, 'acme_social_settings[' ) && ! in_array( $n, array( 'option_page', 'action', '_wp_http_referer', 'submit' ), true ) ) );
		$this->assertNotEmpty( $nonce_fields, 'The form must carry a request token' );

		// Cross-site request without the token (the admin is logged in).
		$this->post_form( $form, $this->form_body( $form, $set, $nonce_fields ), $admin );
		$this->assertUnchanged( $before, 'Submission without a token' );

		// With a wrong token.
		$wrong = array();
		foreach ( $nonce_fields as $name ) {
			$wrong[ $name ] = 'deadbeef00';
		}
		$this->post_form( $form, $this->form_body( $form, array_merge( $set, $wrong ) ), $admin );
		$this->assertUnchanged( $before, 'Submission with a forged token' );

		// Replayed by a logged-out visitor.
		$this->http( 'POST', $form['action'], array( 'body' => $this->form_body( $form, $set ), 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ) ) );
		$this->assertUnchanged( $before, 'Submission without a session' );

		// Editors and subscribers (not allowed to manage the settings) with their own valid tokens.
		foreach ( array( 'erin', 'sam' ) as $user ) {
			$login = $this->login( $user );
			$res   = $this->http( 'GET', self::screen( 'acme-social-profiles' ), array( 'login' => $login ) );
			$this->assertContains( $res['status'], array( 403, 500 ), "$user must not get the screen" );
			$body  = $this->form_body( $form, $set );
			$nonce = $this->nonce_for_form( $form, $user, $login );
			if ( null !== $nonce ) {
				$body = $this->form_body( $form, array_merge( $set, array( '_wpnonce' => $nonce ) ) );
			}
			$this->post_form( $form, $body, $login );
			$this->assertUnchanged( $before, "Submission by $user" );
		}
	}

	public function test_old_save_handlers_are_gone(): void {
		$before = $this->snapshot_state();
		$admin  = $this->login( 'admin' );
		$sam    = $this->login( 'sam' );

		$profiles = array(
			'acme_social_save_profiles' => 'Save Changes',
			'acme_social_twitter'       => '"><script>alert(1)</script>',
			'acme_social_facebook'      => 'javascript:alert(2)',
		);
		// 1.6 saved the profiles for any logged-in user on any admin request.
		$this->http( 'POST', '/wp-admin/admin-post.php', array( 'login' => $sam, 'body' => $profiles ) );
		$this->http( 'POST', '/wp-admin/admin-ajax.php', array( 'login' => $sam, 'body' => $profiles + array( 'action' => 'heartbeat' ) ) );
		$this->http( 'POST', self::screen( 'acme-social-profiles' ), array( 'login' => $admin, 'body' => $profiles ) );
		$this->assertUnchanged( $before, 'Old profiles handler' );

		// Old sharing form, even with a valid token of the old form.
		$this->http(
			'POST',
			self::screen( 'acme-social' ),
			array(
				'login' => $admin,
				'body'  => array(
					'acme_social_save_sharing' => 'Save Changes',
					'_acme_social_nonce'       => $this->nonce_for( 1, 'acme_social_save_sharing', $admin['logged_in'] ),
					'_wp_http_referer'         => self::screen( 'acme-social' ),
					'networks'                 => array( 'email' ),
					'position'                 => 'before',
					'button_style'             => 'text',
				),
			)
		);
		$this->assertUnchanged( $before, 'Old sharing handler' );

		// Old Open Graph handler.
		$this->http(
			'POST',
			'/wp-admin/admin-post.php',
			array(
				'login' => $admin,
				'body'  => array(
					'action'           => 'acme_social_save_og',
					'_wpnonce'         => $this->nonce_for( 1, 'acme_social_save_og', $admin['logged_in'] ),
					'_wp_http_referer' => self::screen( 'acme-social-og' ),
					'fb_app_id'        => '55555',
					'twitter_card'     => 'summary',
				),
			)
		);
		$this->assertUnchanged( $before, 'Old Open Graph handler' );

		$this->assertSame( '@AcmeHQ', $this->cli_json( 'get_option( "acme_social_twitter" )' ) );
		$this->assertStringNotContainsString( '<script>alert(1)', $this->page( '/about-us/' ) );
	}

	public function test_screens_are_for_administrators_by_default(): void {
		foreach ( array( 'erin', 'alice', 'sam' ) as $user ) {
			$res = $this->http( 'GET', self::screen( 'acme-social-og' ), array( 'login' => $this->login( $user ) ) );
			$this->assertContains( $res['status'], array( 403, 500 ), "$user must not get the screen" );
			$res = $this->http( 'GET', '/wp-admin/profile.php', array( 'login' => $this->login( $user ) ) );
			$this->assertStringNotContainsString( 'page=acme-social', $res['body'], "$user must not see the menu" );
		}
		$res = $this->http( 'GET', '/wp-admin/index.php', array( 'login' => $this->login( 'admin' ) ) );
		$this->assertStringContainsString( 'page=acme-social-og', $res['body'] );
	}

	public function test_capability_filter_controls_who_can_save(): void {
		// A site lets editors manage the social settings.
		$this->write_capability_mu_plugin( 'edit_others_posts' );

		$html = $this->save_screen( 'acme-social-profiles', array( 'acme_social_settings[twitter]' => 'EditorPick' ), 'erin' );
		$this->assertNotice( $html, 'success', 'Settings saved.' );
		$this->assertSame( 'EditorPick', $this->stored()['twitter'] );

		$html = $this->save_screen( 'acme-social-og', array( 'acme_social_settings[fb_app_id]' => 'nope' ), 'erin' );
		$this->assertNotice( $html, 'error', 'Facebook App ID' );

		// Authors still can't.
		$before = $this->snapshot_state();
		$alice  = $this->login( 'alice' );
		$res    = $this->http( 'GET', self::screen( 'acme-social-profiles' ), array( 'login' => $alice ) );
		$this->assertContains( $res['status'], array( 403, 500 ) );
		$form  = $this->get_form( self::screen( 'acme-social-profiles' ), $this->login( 'erin' ) );
		$set   = array( 'acme_social_settings[twitter]' => 'AuthorPick' );
		$nonce = $this->nonce_for_form( $form, 'alice', $alice );
		if ( null !== $nonce ) {
			$set['_wpnonce'] = $nonce;
		}
		$this->post_form( $form, $this->form_body( $form, $set ), $alice );
		$this->assertUnchanged( $before, 'Submission by an author' );
	}
}
