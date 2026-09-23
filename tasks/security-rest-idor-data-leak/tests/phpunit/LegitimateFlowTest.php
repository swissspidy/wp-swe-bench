<?php
/**
 * Legitimate helpdesk flows that must keep working (pass-to-pass): opening a
 * ticket, the response shape, notifications, the owner reading their own ticket,
 * agents triaging and adding notes.
 */

use function WPSB\Support\ticket_id;
use function WPSB\Support\user_id;

class LegitimateFlowTest extends WPSB\TestCase {

	private function req( string $method, string $route, int $as_user = 0, array $query = array(), $body = null ): array {
		wp_set_current_user( $as_user );
		$response = $this->rest_dispatch( $method, $route, $query, $body );
		return array( $response->get_status(), $response->get_data() );
	}

	public function test_customer_can_open_a_ticket_and_read_it(): void {
		$carol = user_id( 'carol' );

		list( $status, $data ) = $this->req(
			'POST',
			'/acme-support/v1/tickets',
			$carol,
			array(),
			array(
				'subject'     => 'Cannot download my invoice',
				'description' => 'The download link 404s.',
			)
		);
		$this->assertSame( 201, $status );
		$this->assertSame( 'Cannot download my invoice', $data['subject'] );
		$this->assertSame( 'acme_open', $data['status'] );
		$this->assertSame( $carol, $data['customer'] );

		// Every documented field is present with the right type.
		foreach ( array( 'id', 'subject', 'description', 'status', 'priority', 'customer', 'customer_email', 'agent', 'created', 'updated' ) as $field ) {
			$this->assertArrayHasKey( $field, $data );
		}
		$this->assertIsInt( $data['id'] );
		$this->assertIsInt( $data['agent'] );

		// The owner can read it back.
		list( $status, $read ) = $this->req( 'GET', '/acme-support/v1/tickets/' . $data['id'], $carol );
		$this->assertSame( 200, $status );
		$this->assertSame( $data['id'], $read['id'] );
	}

	public function test_opening_a_ticket_notifies_the_support_inbox(): void {
		$this->clear_mails();
		$carol = user_id( 'carol' );

		$this->req(
			'POST',
			'/acme-support/v1/tickets',
			$carol,
			array(),
			array(
				'subject'     => 'Urgent: outage',
				'description' => 'The site is down.',
			)
		);

		$to = array();
		foreach ( $this->mails() as $mail ) {
			foreach ( (array) ( $mail['to'] ?? array() ) as $addr ) {
				$to[] = $addr;
			}
		}
		$this->assertContains( 'support@acme.example', $to, 'A new ticket must notify the support inbox' );
	}

	public function test_agent_can_triage_and_add_an_internal_note(): void {
		$a   = ticket_id( 'Cannot log in' );
		$amy = user_id( 'amy' );

		// Triage.
		list( $status, $data ) = $this->req( 'PATCH', "/acme-support/v1/tickets/$a", $amy, array(), array( 'priority' => 'urgent' ) );
		$this->assertSame( 200, $status );
		$this->assertSame( 'urgent', $data['priority'] );

		// Add an internal note.
		list( $status, $reply ) = $this->req(
			'POST',
			"/acme-support/v1/tickets/$a/replies",
			$amy,
			array(),
			array(
				'body'     => 'Checked the logs, escalating to engineering.',
				'internal' => true,
			)
		);
		$this->assertSame( 201, $status );
		$this->assertTrue( (bool) $reply['internal'] );

		// The agent sees it in the thread.
		list( $status, $list ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/replies", $amy );
		$this->assertSame( 200, $status );
		$found = false;
		foreach ( $list as $r ) {
			$found = $found || ( 'Checked the logs, escalating to engineering.' === $r['body'] );
		}
		$this->assertTrue( $found );
	}

	public function test_customer_reply_is_public_even_if_internal_requested(): void {
		$a     = ticket_id( 'Cannot log in' );
		$carol = user_id( 'carol' );

		// A customer must never be able to create an internal note.
		list( $status, $reply ) = $this->req(
			'POST',
			"/acme-support/v1/tickets/$a/replies",
			$carol,
			array(),
			array(
				'body'     => 'Any update?',
				'internal' => true,
			)
		);
		$this->assertSame( 201, $status );
		$this->assertFalse( (bool) $reply['internal'], 'A customer reply must be public' );
	}
}
