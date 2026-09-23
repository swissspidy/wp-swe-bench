<?php
/**
 * Step 2 over HTTP: the `connections` level on front-end surfaces (profile page, author card,
 * directory incl. its cache, feed), the profile form, cookie-authenticated connection calls, and
 * that the one-time buddy import doesn't resurrect removed connections.
 */

use function WPSB\Members\author_card;
use function WPSB\Members\cards;
use function WPSB\Members\uid;
use function WPSB\Members\xpath;

class ConnectionsSurfacesTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $logins = array();

	private function login( string $user ): array {
		if ( ! isset( $this->logins[ $user ] ) ) {
			$this->logins[ $user ] = $this->http_login( uid( $user ) );
		}
		return $this->logins[ $user ];
	}

	private function get( string $path, string $as = '' ): array {
		return $this->http( 'GET', $path, $as ? array( 'login' => $this->login( $as ) ) : array() );
	}

	private function api( string $method, string $path, string $as, $body = null ): array {
		$opts = array(
			'login'      => $this->login( $as ),
			'rest_nonce' => true,
		);
		if ( null !== $body ) {
			$opts['body'] = $body;
			$opts['json'] = true;
		}
		return $this->http( $method, '/wp-json/acme-members/v1' . $path, $opts );
	}

	public function test_connections_level_on_front_end_surfaces(): void {
		$res = $this->api( 'GET', '/members/me/connections', 'bob' );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( array( uid( 'carol' ) ), $res['json']['incoming'] );

		$res = $this->api( 'PATCH', '/members/me', 'carol', array( 'field_visibility' => array( 'phone' => 'connections', 'city' => 'connections' ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$carol = uid( 'carol' );

		// Warm whatever caches exist with viewers who may see more.
		$this->get( '/members/', 'gina' );
		$this->get( '/members/', 'admin' );

		foreach ( array( 'gina' => true, 'alice' => false, 'bob' => false, 'sue' => false, '' => false ) as $viewer => $sees ) {
			$label = 'as ' . ( $viewer ?: 'anonymous' );
			$page  = $this->get( '/members/carol/', $viewer );
			$this->assertSame( 200, $page['status'] );
			$this->assertSame( $sees, false !== strpos( $page['body'], '+49 341 555 0103' ), "profile page $label" );

			$card = author_card( $this->get( '/author/carol/', $viewer )['body'] );
			$this->assertNotNull( $card );
			$this->assertSame( $sees, isset( $card['phone'] ), "author card $label" );
			$this->assertSame( $sees, isset( $card['city'] ), "author card city $label" );

			$cards = cards( $this->get( '/members/', $viewer )['body'] );
			$this->assertArrayHasKey( $carol, $cards );
			$this->assertSame( $sees, isset( $cards[ $carol ]['phone'] ), "directory $label" );
		}

		$feed = $this->get( '/feed/', 'gina' )['body'];
		$this->assertStringNotContainsString( '<acme:city>Leipzig', $feed, 'feeds are public data only' );

		// Removing the connection is reflected immediately (no stale caches).
		$res = $this->api( 'DELETE', '/members/me/connections/' . $carol, 'gina' );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$cards = cards( $this->get( '/members/', 'gina' )['body'] );
		$this->assertArrayNotHasKey( 'phone', $cards[ $carol ] );
		$this->assertArrayNotHasKey( 'phone', (array) author_card( $this->get( '/author/carol/', 'gina' )['body'] ) );
		$this->assertStringNotContainsString( '+49 341 555 0103', $this->get( '/members/carol/', 'gina' )['body'] );

		// The buddy import ran once: the removed connection doesn't come back.
		$this->get( '/' );
		$res = $this->api( 'GET', '/members/me/connections', 'gina' );
		$this->assertSame( array( uid( 'frank' ) ), $res['json']['connections'] );
		wp_cache_flush();
		$res = $this->wp_cli( 'eval "echo 1;"' );
		$this->assertSame( 0, $res['exit'] );
		$res = $this->api( 'GET', '/members/me/connections', 'carol' );
		$this->assertSame( array(), $res['json']['connections'] );
	}

	public function test_profile_form_offers_the_new_level(): void {
		$login = $this->login( 'henry' );
		$page  = $this->http( 'GET', '/edit-profile/', array( 'login' => $login ) );
		$x     = xpath( $page['body'] );
		$this->assertSame( 1, $x->query( '//select[@name="visibility"]/option[@value="connections"]' )->length );
		$this->assertSame( 1, $x->query( '//select[@name="field_visibility[city]"]/option[@value="connections"]' )->length );

		// Save city as connections-only through the form.
		$form = $x->query( '//form[contains(@class,"acme-profile-form")]' )->item( 0 );
		$data = array();
		foreach ( $x->query( './/input[@name]', $form ) as $in ) {
			$data[ $in->getAttribute( 'name' ) ] = $in->getAttribute( 'value' );
		}
		foreach ( $x->query( './/textarea[@name]', $form ) as $ta ) {
			$data[ $ta->getAttribute( 'name' ) ] = $ta->textContent;
		}
		foreach ( $x->query( './/select[@name]', $form ) as $sel ) {
			$opt                                  = $x->query( './/option[@selected]', $sel );
			$data[ $sel->getAttribute( 'name' ) ] = $opt->length ? $opt->item( 0 )->getAttribute( 'value' ) : 'public';
		}
		$data['field_visibility[city]'] = 'connections';
		$res                            = $this->http( 'POST', html_entity_decode( $form->getAttribute( 'action' ) ), array( 'login' => $login, 'body' => $data, 'headers' => array( 'Referer' => home_url( '/edit-profile/' ) ) ) );
		$this->assertContains( $res['status'], array( 200, 302, 303 ) );

		// Alice is connected with Henry (forum buddies), Gina isn't.
		$this->assertStringContainsString( 'acme-member-field--city', $this->get( '/members/henry/', 'alice' )['body'] );
		$this->assertStringNotContainsString( 'acme-member-field--city', $this->get( '/members/henry/', 'gina' )['body'] );
		$this->assertStringNotContainsString( 'acme-member-field--city', $this->get( '/members/henry/' )['body'] );
	}
}
