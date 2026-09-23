<?php
/**
 * The same access rules under the real REST auth stack (cookies + nonce) over HTTP.
 */

use function WPSB\Support\ticket_id;
use function WPSB\Support\user_id;

class IdorHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var int[] */
	private array $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created = array();
		parent::tearDown();
	}

	public function test_real_auth_enforces_ownership(): void {
		$carol = $this->http_login( user_id( 'carol' ) );
		$dave  = $this->http_login( user_id( 'dave' ) );

		// Carol opens a new ticket.
		$create = $this->http(
			'POST',
			'/wp-json/acme-support/v1/tickets',
			array(
				'login'      => $carol,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'subject'     => 'Password reset e-mail never arrives',
					'description' => 'Nothing shows up in my inbox or spam.',
				),
			)
		);
		$this->assertSame( 201, $create['status'], $create['body'] );
		$new_id = (int) $create['json']['id'];
		$this->created[] = $new_id;
		$this->assertSame( user_id( 'carol' ), (int) $create['json']['customer'] );

		// Carol can read it back.
		$read = $this->http( 'GET', "/wp-json/acme-support/v1/tickets/$new_id", array( 'login' => $carol, 'rest_nonce' => true ) );
		$this->assertSame( 200, $read['status'] );

		// Dave cannot read Carol's ticket.
		$other = $this->http( 'GET', "/wp-json/acme-support/v1/tickets/$new_id", array( 'login' => $dave, 'rest_nonce' => true ) );
		$this->assertSame( 404, $other['status'], 'Another customer must not read the ticket over HTTP' );

		// Nor can Dave read the pre-seeded Carol ticket.
		$a         = ticket_id( 'Cannot log in' );
		$seeded    = $this->http( 'GET', "/wp-json/acme-support/v1/tickets/$a", array( 'login' => $dave, 'rest_nonce' => true ) );
		$this->assertSame( 404, $seeded['status'] );

		// Logged out: 401.
		$anon = $this->http( 'GET', "/wp-json/acme-support/v1/tickets/$new_id" );
		$this->assertSame( 401, $anon['status'] );
	}

	public function test_core_routes_are_not_a_backdoor_over_http(): void {
		$a   = ticket_id( 'Cannot log in' );
		$sam = $this->http_login( user_id( 'sam' ) );

		$single = $this->http( 'GET', "/wp-json/wp/v2/acme_ticket/$a", array( 'login' => $sam, 'rest_nonce' => true ) );
		$this->assertNotSame( 200, $single['status'], 'The core CPT route must not expose a ticket over HTTP' );

		$replies = $this->http( 'GET', '/wp-json/wp/v2/acme_reply?per_page=100', array( 'login' => $sam, 'rest_nonce' => true ) );
		if ( 200 === $replies['status'] ) {
			$this->assertSame( array(), $replies['json'], 'The core reply route must not expose internal notes' );
		}
	}
}
