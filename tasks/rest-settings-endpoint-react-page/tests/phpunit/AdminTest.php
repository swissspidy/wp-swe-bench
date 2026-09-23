<?php
/**
 * The settings screen is for administrators only, and the old form handler is gone.
 */

class AdminTest extends AcmeSeoCase {

	private function post_old_form( array $login, int $user_id ): array {
		return $this->http(
			'POST',
			'/wp-admin/admin-post.php',
			array(
				'login' => $login,
				'body'  => array(
					'action'             => 'acme_seo_save_settings',
					'_acme_seo_nonce'    => $this->nonce_for( $user_id, 'acme_seo_save_settings', $login['logged_in'] ),
					'_wp_http_referer'   => '/wp-admin/options-general.php?page=acme-seo',
					'title_separator'    => '<b>',
					'home_title'         => '<script>alert("t")</script>Pwned',
					'home_description'   => '<img src=x onerror=alert(1)>',
					'noindex_post_types' => array( 'post', 'page', 'event' ),
					'og_default_image'   => '999',
					'twitter_handle'     => '"><script>alert(2)</script>',
					'social_profiles'    => array( 'facebook' => 'javascript:alert(3)' ),
					'sitemap_exclude'    => '1,2,3',
					'verification'       => array( 'google' => '"><script>alert(4)</script>' ),
				),
			)
		);
	}

	public function test_old_form_handler_no_longer_writes_anything(): void {
		$before = $this->settings();
		$names  = $this->stored_option_names();

		$admin_login = $this->admin();
		$this->post_old_form( $admin_login, 1 );
		$erin = get_user_by( 'login', 'erin' )->ID;
		$this->post_old_form( $this->user_login( 'erin' ), $erin );

		$this->assertSettings( $before, $this->settings() );
		$this->assertSame( $names, $this->stored_option_names() );
		$this->assertNoOldOptions();

		$home = $this->page( '/' )['body'];
		$this->assertStringNotContainsString( 'Pwned', $home );
		$this->assertStringNotContainsString( 'alert(', $home );
	}

	public function test_settings_screen_is_for_administrators_only(): void {
		$res = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-seo', array( 'login' => $this->user_login( 'erin' ) ) );
		$this->assertContains( $res['status'], array( 403, 500 ), 'editors must not get the screen' );
		$this->assertStringContainsString( 'not allowed', $res['body'] );

		$res = $this->http( 'GET', '/wp-admin/options-general.php', array( 'login' => $this->user_login( 'erin' ) ) );
		$this->assertStringNotContainsString( 'page=acme-seo', $res['body'] );

		$res = $this->http( 'GET', '/wp-admin/index.php', array( 'login' => $this->user_login( 'erin' ) ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringNotContainsString( 'options-general.php?page=acme-seo', $res['body'] );

		$res = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-seo', array( 'login' => $this->admin() ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'options-general.php?page=acme-seo', $res['body'] );
	}
}
