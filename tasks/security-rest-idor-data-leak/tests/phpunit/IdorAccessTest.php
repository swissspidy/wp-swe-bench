<?php
/**
 * Per-object access control across every read path (custom routes and the
 * always-on core routes), for each role.
 *
 * Seeded data:
 *  - "Cannot log in to my account"  -> Carol's ticket (id A), with an internal
 *    note, a public reply and an attachment.
 *  - "Question about my last invoice" -> Dave's ticket (id B), internal note + attachment.
 */

use function WPSB\Support\attachment_id;
use function WPSB\Support\internal_reply_id;
use function WPSB\Support\ticket_id;
use function WPSB\Support\user_id;

class IdorAccessTest extends WPSB\TestCase {

	private function req( string $method, string $route, int $as_user = 0, array $query = array(), $body = null ): array {
		wp_set_current_user( $as_user );
		$response = $this->rest_dispatch( $method, $route, $query, $body );
		return array( $response->get_status(), $response->get_data(), $response );
	}

	// ---------------------------------------------------------------------
	// Custom collection: customers see only their own tickets.
	// ---------------------------------------------------------------------

	public function test_custom_collection_is_scoped_per_user(): void {
		$carol = user_id( 'carol' );
		$dave  = user_id( 'dave' );

		// Logged out: 401.
		list( $status ) = $this->req( 'GET', '/acme-support/v1/tickets' );
		$this->assertSame( 401, $status, 'Anonymous listing must be unauthorized' );

		// Carol sees only her tickets.
		list( $status, $data ) = $this->req( 'GET', '/acme-support/v1/tickets', $carol, array( 'per_page' => 100 ) );
		$this->assertSame( 200, $status );
		$this->assertNotEmpty( $data );
		foreach ( $data as $row ) {
			$this->assertSame( $carol, $row['customer'], 'Carol must not see other customers\' tickets' );
		}

		// Dave never sees Carol's tickets.
		list( $status, $data ) = $this->req( 'GET', '/acme-support/v1/tickets', $dave, array( 'per_page' => 100 ) );
		$this->assertSame( 200, $status );
		foreach ( $data as $row ) {
			$this->assertSame( $dave, $row['customer'] );
		}

		// An unrelated subscriber sees nothing.
		list( $status, $data ) = $this->req( 'GET', '/acme-support/v1/tickets', user_id( 'sam' ), array( 'per_page' => 100 ) );
		$this->assertSame( 200, $status );
		$this->assertSame( array(), $data, 'A logged-in non-customer must not see any tickets' );
	}

	public function test_agents_see_all_tickets(): void {
		$total = (int) ( new WP_Query(
			array(
				'post_type'      => 'acme_ticket',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		) )->found_posts;

		list( $status, $data ) = $this->req( 'GET', '/acme-support/v1/tickets', user_id( 'amy' ), array( 'per_page' => 100 ) );
		$this->assertSame( 200, $status );
		$this->assertCount( $total, $data, 'Agents must see every ticket' );
	}

	// ---------------------------------------------------------------------
	// Custom single item + fields.
	// ---------------------------------------------------------------------

	public function test_single_ticket_is_owner_or_agent_only(): void {
		$a     = ticket_id( 'Cannot log in' );
		$carol = user_id( 'carol' );

		// Owner reads it, including the private contact e-mail.
		list( $status, $data ) = $this->req( 'GET', "/acme-support/v1/tickets/$a", $carol );
		$this->assertSame( 200, $status );
		$this->assertSame( 'carol@carol-industries.example', $data['customer_email'] );

		// Agent reads it.
		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a", user_id( 'amy' ) );
		$this->assertSame( 200, $status );

		// Another customer: 404 (not 403 – existence must not leak).
		list( $status, $data ) = $this->req( 'GET', "/acme-support/v1/tickets/$a", user_id( 'dave' ) );
		$this->assertSame( 404, $status, 'A customer must not read another customer\'s ticket' );

		// A random subscriber: 404.
		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a", user_id( 'sam' ) );
		$this->assertSame( 404, $status );

		// An editor (privileged, but not a support agent): 404.
		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a", user_id( 'eddie' ) );
		$this->assertSame( 404, $status );

		// Logged out: 401.
		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a" );
		$this->assertSame( 401, $status );
	}

	// ---------------------------------------------------------------------
	// Internal notes only reach agents.
	// ---------------------------------------------------------------------

	public function test_internal_notes_hidden_from_customers(): void {
		$a = ticket_id( 'Cannot log in' );

		// The owner reads the conversation but never the internal notes.
		list( $status, $data ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/replies", user_id( 'carol' ) );
		$this->assertSame( 200, $status );
		foreach ( $data as $reply ) {
			$this->assertFalse( (bool) $reply['internal'], 'Internal notes must not reach the customer' );
			$this->assertStringNotContainsStringIgnoringCase( 'INTERNAL', $reply['body'] );
		}

		// The agent sees the internal note.
		list( $status, $data ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/replies", user_id( 'amy' ) );
		$this->assertSame( 200, $status );
		$has_internal = false;
		foreach ( $data as $reply ) {
			$has_internal = $has_internal || (bool) $reply['internal'];
		}
		$this->assertTrue( $has_internal, 'Agents must see internal notes' );

		// A non-owner customer cannot list the replies at all.
		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/replies", user_id( 'dave' ) );
		$this->assertSame( 404, $status );
	}

	// ---------------------------------------------------------------------
	// Only agents may change a ticket.
	// ---------------------------------------------------------------------

	public function test_only_agents_can_change_a_ticket(): void {
		$a = ticket_id( 'Cannot log in' );

		// The owner cannot change status.
		list( $status ) = $this->req( 'PATCH', "/acme-support/v1/tickets/$a", user_id( 'carol' ), array(), array( 'status' => 'acme_solved' ) );
		$this->assertSame( 403, $status, 'Customers must not be able to change a ticket' );

		// A different customer just gets a 404.
		list( $status ) = $this->req( 'PATCH', "/acme-support/v1/tickets/$a", user_id( 'dave' ), array(), array( 'status' => 'acme_solved' ) );
		$this->assertSame( 404, $status );

		// The agent can.
		list( $status, $data ) = $this->req( 'PATCH', "/acme-support/v1/tickets/$a", user_id( 'amy' ), array(), array( 'status' => 'acme_pending' ) );
		$this->assertSame( 200, $status );
		$this->assertSame( 'acme_pending', $data['status'] );

		// Invalid status is rejected.
		list( $status ) = $this->req( 'PATCH', "/acme-support/v1/tickets/$a", user_id( 'amy' ), array(), array( 'status' => 'bogus' ) );
		$this->assertSame( 400, $status );
	}

	// ---------------------------------------------------------------------
	// Attachments follow the ticket's access rules.
	// ---------------------------------------------------------------------

	public function test_attachments_route_is_scoped(): void {
		$a = ticket_id( 'Cannot log in' );

		list( $status, $data ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/attachments", user_id( 'carol' ) );
		$this->assertSame( 200, $status );
		$this->assertNotEmpty( $data );

		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/attachments", user_id( 'dave' ) );
		$this->assertSame( 404, $status );

		list( $status ) = $this->req( 'GET', "/acme-support/v1/tickets/$a/attachments", user_id( 'sam' ) );
		$this->assertSame( 404, $status );
	}

	// ---------------------------------------------------------------------
	// The core content routes must not leak tickets or replies.
	// ---------------------------------------------------------------------

	public function test_core_ticket_routes_do_not_leak(): void {
		$a = ticket_id( 'Cannot log in' );

		// Anonymous.
		$this->assertNotLeaked( $this->req( 'GET', '/wp/v2/acme_ticket', 0, array( 'per_page' => 100 ) ), 'anon collection' );
		$this->assertNotLeaked( $this->req( 'GET', "/wp/v2/acme_ticket/$a", 0 ), 'anon single' );
		$this->assertNotLeaked( $this->req( 'GET', "/wp/v2/acme_ticket/$a", 0, array( '_embed' => 1 ) ), 'anon single embed' );
		$this->assertNotLeaked( $this->req( 'GET', '/wp/v2/acme_reply', 0, array( 'per_page' => 100 ) ), 'anon replies' );

		// Author filter.
		$this->assertNotLeaked( $this->req( 'GET', '/wp/v2/acme_ticket', 0, array( 'author' => user_id( 'carol' ) ) ), 'anon author filter' );

		// A logged-in subscriber must not reach them either.
		$sam = user_id( 'sam' );
		$this->assertNotLeaked( $this->req( 'GET', '/wp/v2/acme_ticket', $sam, array( 'per_page' => 100 ) ), 'subscriber collection' );
		$this->assertNotLeaked( $this->req( 'GET', "/wp/v2/acme_ticket/$a", $sam ), 'subscriber single' );
		$this->assertNotLeaked( $this->req( 'GET', '/wp/v2/acme_reply', $sam, array( 'per_page' => 100 ) ), 'subscriber replies' );
	}

	public function test_core_search_does_not_surface_tickets(): void {
		$sam = user_id( 'sam' );
		foreach ( array( 0, $sam ) as $viewer ) {
			list( $status, $data ) = $this->req( 'GET', '/wp/v2/search', $viewer, array( 'search' => 'invoice' ) );
			$titles = array();
			foreach ( (array) $data as $row ) {
				if ( is_array( $row ) && isset( $row['title'] ) ) {
					$titles[] = $row['title'];
				}
			}
			$this->assertNotContains( 'Question about my last invoice', $titles, 'Ticket subjects must not appear in site search' );
		}
	}

	public function test_core_revisions_and_autosaves_do_not_leak(): void {
		$a   = ticket_id( 'Cannot log in' );
		$sam = user_id( 'sam' );

		foreach ( array( 0, $sam ) as $viewer ) {
			$this->assertNotLeaked( $this->req( 'GET', "/wp/v2/acme_ticket/$a/revisions", $viewer ), 'revisions' );
			$this->assertNotLeaked( $this->req( 'GET', "/wp/v2/acme_ticket/$a/autosaves", $viewer ), 'autosaves' );
		}
	}

	public function test_core_media_route_does_not_leak_attachments(): void {
		$a   = ticket_id( 'Cannot log in' );
		$att = attachment_id( $a );
		$this->assertGreaterThan( 0, $att );

		// A random logged-in user cannot read the ticket's attachment.
		list( $status ) = $this->req( 'GET', "/wp/v2/media/$att", user_id( 'sam' ) );
		$this->assertNotSame( 200, $status, 'A ticket attachment must not be readable by unrelated users' );

		// Nor can a different customer.
		list( $status ) = $this->req( 'GET', "/wp/v2/media/$att", user_id( 'dave' ) );
		$this->assertNotSame( 200, $status );

		// Anonymous.
		list( $status ) = $this->req( 'GET', "/wp/v2/media/$att", 0 );
		$this->assertNotSame( 200, $status );

		// The collection filtered by the ticket must not expose it either.
		list( $status, $data ) = $this->req( 'GET', '/wp/v2/media', user_id( 'sam' ), array( 'parent' => $a, 'per_page' => 100 ) );
		$ids = array();
		foreach ( (array) $data as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) ) {
				$ids[] = $row['id'];
			}
		}
		$this->assertNotContains( $att, $ids );
	}

	/**
	 * Assert a core-route response did not return ticket/reply data.
	 *
	 * @param array  $result [status, data, response].
	 * @param string $label  Diagnostic label.
	 */
	private function assertNotLeaked( array $result, string $label ): void {
		list( $status, $data ) = $result;
		if ( 200 !== $status ) {
			$this->assertTrue( true );
			return;
		}
		// A 200 is only acceptable if it carries no ticket/reply rows.
		if ( is_array( $data ) && isset( $data[0] ) ) {
			$this->fail( "Leak via $label: core route returned " . count( $data ) . ' item(s)' );
		}
		if ( is_array( $data ) && ( isset( $data['id'] ) || isset( $data['title'] ) || isset( $data['content'] ) ) ) {
			$this->fail( "Leak via $label: core route returned an item" );
		}
		$this->assertTrue( true );
	}
}
