<?php
/**
 * Legitimate flows that must keep working (pass-to-pass).
 */

class LegitimateTest extends WPSB\TestCase {

	public function test_benign_shortcode_renders_from_cache(): void {
		$html = do_shortcode( '[acme_link_preview url="https://example.com/hello"]' );
		$this->assertStringContainsString( 'Hello from Example', $html );
		$this->assertStringContainsString( 'acme-lp-card', $html );
		$this->assertStringContainsString( 'https://example.com/img/hello.png', $html );
		$this->assertStringContainsString( 'https://example.com/hello', $html );
	}

	public function test_prefs_default_without_a_cookie(): void {
		unset( $_COOKIE['acme_lp_prefs'] );
		$this->login_as( 'subscriber' );
		$prefs = (array) $this->rest( 'GET', '/acme-link-previews/v1/prefs' )->get_data();
		$this->assertSame( 'light', $prefs['theme'] );
		$this->assertTrue( $prefs['show_images'] );
	}

	public function test_fetch_requires_the_edit_capability(): void {
		// Logged out.
		wp_set_current_user( 0 );
		$anon = $this->rest( 'POST', '/acme-link-previews/v1/fetch', array(), array( 'url' => 'https://example.com/' ) );
		$this->assertSame( 401, $anon->get_status() );

		// A subscriber cannot fetch.
		$this->login_as( 'subscriber' );
		$sub = $this->rest( 'POST', '/acme-link-previews/v1/fetch', array(), array( 'url' => 'https://example.com/' ) );
		$this->assertSame( 403, $sub->get_status() );
	}

	public function test_import_requires_manage_options(): void {
		$this->login_as( 'editor' );
		$res = $this->rest( 'POST', '/acme-link-previews/v1/import', array(), array( 'data' => base64_encode( wp_json_encode( array() ) ) ) );
		$this->assertSame( 403, $res->get_status() );
	}
}
