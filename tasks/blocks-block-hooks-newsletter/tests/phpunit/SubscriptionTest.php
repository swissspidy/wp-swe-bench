<?php
/**
 * Submitting the forms that are on the served pages, and the settings screen.
 */

use function WPSB\Newsletter\analyse;
use function WPSB\Newsletter\form_fields;
use function WPSB\Newsletter\subscriber;
use function WPSB\Newsletter\delete_subscriber;
use const WPSB\Newsletter\OPTION;

class SubscriptionTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_option;
	private array $emails = array();

	protected function setUp(): void {
		parent::setUp();
		$this->saved_option = get_option( OPTION );
	}

	protected function tearDown(): void {
		update_option( OPTION, $this->saved_option );
		foreach ( $this->emails as $email ) {
			delete_subscriber( $email );
		}
		parent::tearDown();
	}

	private function email( string $prefix ): string {
		$email          = strtolower( $prefix . '-' . wp_rand( 100000, 999999 ) . '@example.org' );
		$this->emails[] = $email;
		return $email;
	}

	private function submit( \DOMElement $form, array $values, string $page_path ): array {
		$body = array_merge( form_fields( $form ), $values );
		$res  = $this->http(
			'POST',
			$form->getAttribute( 'action' ),
			array(
				'body'    => $body,
				'headers' => array( 'Referer' => rtrim( WP_HOME, '/' ) . $page_path ),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), 'Submitting the form must redirect back: ' . substr( $res['body'], 0, 500 ) );
		return $res;
	}

	private function forms_on( string $path ): array {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'] );
		return analyse( $res['body'] );
	}

	public function test_any_form_on_a_single_post_subscribes(): void {
		$p = $this->forms_on( '/welcome-to-the-new-blog/' );
		$this->assertNotEmpty( $p['forms'] );
		$email = $this->email( 'first' );
		$res   = $this->submit( $p['forms'][0], array( 'acme_email' => strtoupper( $email ), 'acme_name' => 'First <b>Reader</b>', 'acme_consent' => '1' ), '/welcome-to-the-new-blog/' );
		$this->assertStringContainsString( 'acme_newsletter=subscribed', $res['headers']['location'] ?? '' );
		$this->assertStringContainsString( '/welcome-to-the-new-blog/', $res['headers']['location'] ?? '' );
		$row = subscriber( $email );
		$this->assertNotNull( $row, 'subscriber not stored' );
		$this->assertSame( 'First Reader', $row->name );
		$this->assertSame( 'pending', $row->status );

		// Same address again.
		$res = $this->submit( $p['forms'][0], array( 'acme_email' => $email, 'acme_consent' => '1' ), '/welcome-to-the-new-blog/' );
		$this->assertStringContainsString( 'acme_newsletter=exists', $res['headers']['location'] ?? '' );

		// The page shows the success notice after the redirect.
		$page = $this->http( 'GET', '/welcome-to-the-new-blog/?acme_newsletter=subscribed' );
		$this->assertStringContainsString( 'Thanks! Please check your inbox', $page['body'] );
	}

	public function test_after_content_and_footer_forms_record_their_source(): void {
		$p = $this->forms_on( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['after_content'] );
		$this->assertCount( 1, $p['in_footer'] );

		$a = $this->email( 'after' );
		$this->submit( $p['after_content'][0], array( 'acme_email' => $a, 'acme_consent' => '1' ), '/welcome-to-the-new-blog/' );
		$this->assertSame( 'content', subscriber( $a )->source ?? null );

		$f = $this->email( 'footer' );
		$this->submit( $p['in_footer'][0], array( 'acme_email' => $f, 'acme_consent' => '1' ), '/welcome-to-the-new-blog/' );
		$this->assertSame( 'footer', subscriber( $f )->source ?? null, 'footer subscriptions must be recorded with the "footer" source' );

		$b = $this->email( 'block' );
		$p = $this->forms_on( '/spring-campaign/' );
		$this->assertCount( 1, $p['in_content'] );
		$this->submit( $p['in_content'][0], array( 'acme_email' => $b, 'acme_consent' => '1' ), '/spring-campaign/' );
		$this->assertSame( 'block', subscriber( $b )->source ?? null );

		// Unknown sources are not stored as such.
		$x = $this->email( 'forged' );
		$this->submit( $p['in_content'][0], array( 'acme_email' => $x, 'acme_consent' => '1', 'acme_source' => 'evil<script>' ), '/spring-campaign/' );
		$row = subscriber( $x );
		$this->assertNotNull( $row );
		$this->assertContains( $row->source, array( '', 'block', 'content', 'footer', 'shortcode', 'widget' ) );
		$this->assertNotSame( 'evilscript', $row->source );
	}

	public function test_invalid_submissions_are_rejected(): void {
		$p    = $this->forms_on( '/welcome-to-the-new-blog/' );
		$form = $p['forms'][0];

		$bad = $this->email( 'noconsent' );
		$res = $this->submit( $form, array( 'acme_email' => $bad ), '/welcome-to-the-new-blog/' );
		$this->assertStringContainsString( 'acme_newsletter=invalid', $res['headers']['location'] ?? '' );
		$this->assertNull( subscriber( $bad ) );

		$res = $this->submit( $form, array( 'acme_email' => 'not-an-email', 'acme_consent' => '1' ), '/welcome-to-the-new-blog/' );
		$this->assertStringContainsString( 'acme_newsletter=invalid', $res['headers']['location'] ?? '' );

		$nonce = $this->email( 'nonce' );
		$res   = $this->submit( $form, array( 'acme_email' => $nonce, 'acme_consent' => '1', '_acme_nonce' => 'deadbeef00' ), '/welcome-to-the-new-blog/' );
		$this->assertNull( subscriber( $nonce ), 'a forged nonce must not subscribe' );
		$this->assertStringNotContainsString( 'acme_newsletter=subscribed', $res['headers']['location'] ?? '' );

		$bot = $this->email( 'bot' );
		$this->submit( $form, array( 'acme_email' => $bot, 'acme_consent' => '1', 'acme_website' => 'http://spam.example' ), '/welcome-to-the-new-blog/' );
		$this->assertNull( subscriber( $bot ), 'honeypot submissions must not be stored' );
	}

	/**
	 * Submit Settings → Newsletter like a browser: all fields of the form as rendered,
	 * with the placement checkboxes set as requested (extra values appended raw).
	 */
	private function submit_settings( array $login, array $check, array $extra_pairs = array(), array $text = array() ): array {
		$page = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-newsletter', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$xpath = WPSB\Newsletter\dom( $page['body'] );
		$form  = $xpath->query( '//form[contains(@action, "options.php")]' )->item( 0 );
		$this->assertNotNull( $form, 'settings form not found' );
		$pairs = array();
		foreach ( $xpath->query( './/input | .//select | .//textarea', $form ) as $el ) {
			$name = $el->getAttribute( 'name' );
			if ( '' === $name ) {
				continue;
			}
			$type = strtolower( $el->getAttribute( 'type' ) );
			if ( 'submit' === $type || 'button' === $type ) {
				continue;
			}
			if ( 'checkbox' === $type || 'radio' === $type ) {
				if ( 'acme_newsletter_settings[placements][]' === $name ) {
					if ( in_array( $el->getAttribute( 'value' ), $check, true ) ) {
						$pairs[] = array( $name, $el->getAttribute( 'value' ) );
					}
				} elseif ( $el->hasAttribute( 'checked' ) ) {
					$pairs[] = array( $name, $el->getAttribute( 'value' ) ?: 'on' );
				}
				continue;
			}
			$value = 'textarea' === strtolower( $el->nodeName ) ? $el->textContent : $el->getAttribute( 'value' );
			foreach ( $text as $key => $v ) {
				if ( "acme_newsletter_settings[$key]" === $name ) {
					$value = $v;
				}
			}
			$pairs[] = array( $name, $value );
		}
		foreach ( $extra_pairs as $pair ) {
			$pairs[] = $pair;
		}
		$body = implode( '&', array_map( static fn( $p ) => rawurlencode( $p[0] ) . '=' . rawurlencode( $p[1] ), $pairs ) );
		$res  = $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login'   => $login,
				'body'    => $body,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), 'saving the settings failed: ' . substr( $res['body'], 0, 300 ) );
		wp_cache_delete( OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$saved = get_option( OPTION );
		$this->assertIsArray( $saved );
		return $saved;
	}

	public function test_settings_screen_saves_placements(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$page  = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-newsletter', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$boxes = array();
		foreach ( WPSB\Newsletter\dom( $page['body'] )->query( '//input[@type="checkbox"][@name="acme_newsletter_settings[placements][]"]' ) as $el ) {
			$boxes[] = $el->getAttribute( 'value' );
		}
		$this->assertEqualsCanonicalizing( array( 'after_content', 'footer' ), $boxes, 'placement checkboxes' );
		$this->assertEqualsCanonicalizing( array( 'after_content', 'footer' ), $this->checked_placements( $page['body'] ), 'seeded site: both placements on' );

		$saved = $this->submit_settings(
			$login,
			array( 'footer' ),
			array( array( 'acme_newsletter_settings[placements][]', 'bogus' ), array( 'acme_newsletter_settings[placements][]', '<script>' ) ),
			array( 'heading' => 'Weekly news' )
		);
		$this->assertSame( array( 'footer' ), array_values( array_filter( (array) ( $saved['placements'] ?? null ) ) ) );
		$this->assertSame( 'Weekly news', $saved['heading'] );
		$html = $this->http( 'GET', '/welcome-to-the-new-blog/' )['body'];
		$a    = analyse( $html );
		$this->assertCount( 0, $a['after_content'] );
		$this->assertCount( 1, $a['in_footer'] );
		$this->assertStringContainsString( 'Weekly news', $html );

		$saved = $this->submit_settings( $login, array( 'after_content', 'footer' ) );
		$this->assertEqualsCanonicalizing( array( 'after_content', 'footer' ), array_values( array_filter( (array) $saved['placements'] ) ) );
		$a = analyse( $this->http( 'GET', '/welcome-to-the-new-blog/' )['body'] );
		$this->assertCount( 2, $a['forms'] );

		// Nothing checked.
		$saved = $this->submit_settings( $login, array() );
		$this->assertSame( array(), array_values( array_filter( (array) ( $saved['placements'] ?? array( 'missing' ) ) ) ) );
		$a = analyse( $this->http( 'GET', '/welcome-to-the-new-blog/' )['body'] );
		$this->assertCount( 0, $a['forms'] );
		$a = analyse( $this->http( 'GET', '/spring-campaign/' )['body'] );
		$this->assertCount( 1, $a['forms'] );

		// The screen reflects the saved state.
		$page = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-newsletter', array( 'login' => $login ) );
		$this->assertSame( array(), $this->checked_placements( $page['body'] ) );
		$this->submit_settings( $login, array( 'after_content' ) );
		$page = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-newsletter', array( 'login' => $login ) );
		$this->assertSame( array( 'after_content' ), $this->checked_placements( $page['body'] ) );
	}

	private function checked_placements( string $html ): array {
		$out = array();
		foreach ( WPSB\Newsletter\dom( $html )->query( '//input[@type="checkbox"][@name="acme_newsletter_settings[placements][]"]' ) as $el ) {
			if ( $el->hasAttribute( 'checked' ) ) {
				$out[] = $el->getAttribute( 'value' );
			}
		}
		return $out;
	}

	public function test_non_admins_cannot_change_settings(): void {
		$editor = $this->create_user( 'editor' );
		$login  = $this->http_login( $editor );
		$before = get_option( OPTION );
		$res    = $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'              => 'acme-newsletter',
					'action'                   => 'update',
					'_wpnonce'                 => $this->nonce_for( $editor, 'acme-newsletter-options', $login['logged_in'] ),
					'acme_newsletter_settings' => array( 'placements' => array() ),
				),
			)
		);
		wp_cache_delete( OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$this->assertSame( $before, get_option( OPTION ) );
	}
}
