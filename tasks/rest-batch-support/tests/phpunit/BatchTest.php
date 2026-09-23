<?php
/**
 * Batch requests through POST /wp-json/batch/v1 (the mobile app's sync).
 */

class BatchTest extends AcmeTasksCase {

	private function ids_by_title( int $list_id ): array {
		return array_column( $this->tasks( $list_id ), 'id', 'title' );
	}

	public function test_mixed_batch_returns_one_result_per_request_in_order(): void {
		$owner  = $this->user( 'author' );
		$member = $this->user( 'contributor' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C', 'D' ), array( 'members' => array( $member ) ) );
		$this->clear_mails();

		$res = $this->batch(
			$member,
			array(
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'E', 'assignee' => $owner, 'due_date' => '2026-12-01' ) ),
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'F', 'position' => 0 ) ),
				array( 'PATCH', '/tasks/' . $ids['B'], array( 'status' => 'done' ) ),
				array( 'PATCH', '/tasks/' . $ids['C'], array( 'title' => 'C renamed', 'position' => 1 ) ),
				array( 'DELETE', '/tasks/' . $ids['D'] ),
				array( 'POST', "/lists/$list/tasks", array( 'title' => '' ) ),
				array( 'PATCH', '/tasks/999999', array( 'title' => 'ghost' ) ),
				array( 'POST', '/lists', array( 'title' => 'Created in a batch', 'members' => array( $owner ) ) ),
				array( 'PATCH', "/lists/$list", array( 'title' => 'Taken over' ) ),
				array( 'POST', '/tasks/' . $ids['A'], array( 'completed' => true ) ),
			)
		);
		$responses = $this->responses( $res, 10 );
		$this->assertArrayNotHasKey( 'failed', $res['json'] );
		$this->assertSame( array( 201, 201, 200, 200, 200, 400, 404, 201, 403, 200 ), $this->statuses( $responses ), substr( $res['body'], 0, 4000 ) );

		$e = $responses[0]['body'];
		$this->assertSame( 'E', $e['title'] );
		$this->assertSame( 4, $e['position'] );
		$this->assertSame( $owner, $e['assignee'] );
		$this->assertSame( '2026-12-01', $e['due_date'] );
		$this->assertSame( $member, $e['created_by'] );
		$this->assertSame( 0, $responses[1]['body']['position'] );
		$this->assertSame( 'done', $responses[2]['body']['status'] );
		$this->assertSame( array( 'C renamed', 1 ), array( $responses[3]['body']['title'], $responses[3]['body']['position'] ) );
		$this->assertTrue( $responses[4]['body']['deleted'] );
		$this->assertSame( $ids['D'], $responses[4]['body']['previous']['id'] );
		$this->assertSame( 'D', $responses[4]['body']['previous']['title'] );
		$this->assertSame( 'Created in a batch', $responses[7]['body']['title'] );
		$this->assertSame( $member, $responses[7]['body']['owner'] );
		$this->assertSame( 'done', $responses[9]['body']['status'] );

		// Same response shape as a direct request.
		$direct = $this->api( $member, 'GET', '/tasks/' . $e['id'] );
		$this->assertSame( 200, $direct['status'] );
		$keys = array_keys( $direct['json'] );
		sort( $keys );
		$batch_keys = array_keys( $e );
		sort( $batch_keys );
		$this->assertSame( $keys, $batch_keys );

		$this->assertOrder( array( 'F', 'C renamed', 'A', 'B', 'E' ), $list );
		$tasks = array_column( $this->tasks( $list ), null, 'title' );
		$this->assertSame( 'done', $tasks['A']['status'] );
		$this->assertSame( 'done', $tasks['B']['status'] );
		$this->assertSame( 'open', $tasks['F']['status'] );

		$this->assertStringStartsWith( 'List ', $this->api( $owner, 'GET', "/lists/$list" )['json']['title'] );
		$this->assertContains( 'Created in a batch', array_column( $this->api( $owner, 'GET', '/lists' )['json'], 'title' ) );

		// Side effects happen per request: activity feed + the IT team's assignment mail.
		$actions = array_slice( array_column( $this->activity( $list ), 'action' ), 0, 6 );
		$this->assertSame( array( 'task_completed', 'task_deleted', 'task_updated', 'task_completed', 'task_created', 'task_created' ), $actions );
		$mails = $this->mails_to( $owner );
		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'E', $mails[0]['subject'] );
	}

	/**
	 * Invalid operations for the all-or-nothing test. Tokens: LIST, TASK_C, OUTSIDER, SUBSCRIBER.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function invalid_operations(): array {
		return array(
			'empty title'         => array( array( 'POST', '/lists/LIST/tasks', array( 'title' => '' ) ) ),
			'missing title'       => array( array( 'POST', '/lists/LIST/tasks', array( 'notes' => 'no title' ) ) ),
			'title too long'      => array( array( 'POST', '/lists/LIST/tasks', array( 'title' => str_repeat( 'y', 201 ) ) ) ),
			'impossible due date' => array( array( 'PATCH', '/tasks/TASK_C', array( 'due_date' => '2026-02-30' ) ) ),
			'unknown status'      => array( array( 'PATCH', '/tasks/TASK_C', array( 'status' => 'later' ) ) ),
			'outsider assignee'   => array( array( 'PATCH', '/tasks/TASK_C', array( 'assignee' => 'OUTSIDER' ) ) ),
			'assignee on create'  => array( array( 'POST', '/lists/LIST/tasks', array( 'title' => 'ok', 'assignee' => 'OUTSIDER' ) ) ),
			'negative position'   => array( array( 'PATCH', '/tasks/TASK_C', array( 'position' => -3 ) ) ),
			'bad completed flag'  => array( array( 'PATCH', '/tasks/TASK_C', array( 'completed' => 'sometimes' ) ) ),
			'bad list color'      => array( array( 'POST', '/lists', array( 'title' => 'Blue', 'color' => 'blue' ) ) ),
			'subscriber member'   => array( array( 'PATCH', '/lists/LIST', array( 'members' => array( 'SUBSCRIBER' ) ) ) ),
		);
	}

	/**
	 * @dataProvider invalid_operations
	 */
	public function test_require_all_validate_writes_nothing_when_one_request_is_invalid( array $invalid ): void {
		$owner    = $this->user( 'author' );
		$member   = $this->user( 'author' );
		$outsider = $this->user( 'author' );
		$sub      = $this->user( 'subscriber' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C' ), array( 'members' => array( $member ) ) );
		$this->clear_mails();

		$tokens  = array(
			'LIST'       => $list,
			'TASK_C'     => $ids['C'],
			'OUTSIDER'   => $outsider,
			'SUBSCRIBER' => $sub,
		);
		$invalid[1] = strtr( $invalid[1], array_map( 'strval', $tokens ) );
		array_walk_recursive(
			$invalid[2],
			static function ( &$v ) use ( $tokens ) {
				if ( is_string( $v ) && isset( $tokens[ $v ] ) ) {
					$v = $tokens[ $v ];
				}
			}
		);

		$tasks_before    = $this->tasks( $list );
		$activity_before = $this->activity( $list );
		$lists_before    = $this->api( $owner, 'GET', '/lists' )['json'];

		$res = $this->batch(
			$owner,
			array(
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'New', 'assignee' => $member ) ),
				array( 'PATCH', '/tasks/' . $ids['A'], array( 'status' => 'done', 'position' => 2 ) ),
				$invalid,
				array( 'DELETE', '/tasks/' . $ids['B'] ),
				array( 'POST', '/lists', array( 'title' => 'Should not exist' ) ),
				array( 'PATCH', "/lists/$list", array( 'title' => 'Renamed list' ) ),
			),
			'require-all-validate'
		);
		$responses = $this->responses( $res, 6 );
		$this->assertSame( 'validation', $res['json']['failed'] ?? null, substr( $res['body'], 0, 3000 ) );
		$this->assertSame( array( null, null, 400, null, null, null ), $this->statuses( $responses ), substr( $res['body'], 0, 3000 ) );
		$this->assertSame( array( null, null ), array( $responses[0], $responses[5] ) );

		// Nothing happened.
		$this->assertSame( $tasks_before, $this->tasks( $list ) );
		$this->assertSame( $activity_before, $this->activity( $list ) );
		$this->assertSame( $lists_before, $this->api( $owner, 'GET', '/lists' )['json'] );
		$this->assertSame( array(), $this->mails_to( $member ) );
	}

	public function test_require_all_validate_runs_everything_when_valid(): void {
		$owner  = $this->user( 'author' );
		$member = $this->user( 'author' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C' ), array( 'members' => array( $member ) ) );
		$this->clear_mails();

		$res       = $this->batch(
			$owner,
			array(
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'New', 'assignee' => $member ) ),
				array( 'PATCH', '/tasks/' . $ids['A'], array( 'status' => 'done', 'position' => 2 ) ),
				array( 'DELETE', '/tasks/' . $ids['B'] ),
				array( 'POST', '/lists', array( 'title' => 'Valid batch list' ) ),
				array( 'PATCH', "/lists/$list", array( 'title' => 'Renamed list' ) ),
			),
			'require-all-validate'
		);
		$responses = $this->responses( $res, 5 );
		$this->assertArrayNotHasKey( 'failed', $res['json'] );
		$this->assertSame( array( 201, 200, 200, 201, 200 ), $this->statuses( $responses ), substr( $res['body'], 0, 3000 ) );
		$this->assertOrder( array( 'C', 'A', 'New' ), $list );
		$this->assertSame( 'Renamed list', $this->api( $owner, 'GET', "/lists/$list" )['json']['title'] );
		$this->assertContains( 'Valid batch list', array_column( $this->api( $owner, 'GET', '/lists' )['json'], 'title' ) );
		$this->assertCount( 1, $this->mails_to( $member ) );
	}

	public function test_every_request_in_a_batch_is_authorized_on_its_own(): void {
		$me       = $this->user( 'author' );
		$them     = $this->user( 'author' );
		$their_mb = $this->user( 'contributor' );
		list( $mine, $my_ids )     = $this->list_with_tasks( $me, array( 'X' ) );
		list( $theirs, $their_ids ) = $this->list_with_tasks( $them, array( 'Y', 'Z' ), array( 'members' => array( $their_mb ) ) );
		$their_list_before = $this->api( $them, 'GET', "/lists/$theirs" )['json'];
		$their_tasks       = $this->tasks( $theirs );

		$res       = $this->batch(
			$me,
			array(
				array( 'POST', "/lists/$mine/tasks", array( 'title' => 'Mine' ) ),
				array( 'POST', "/lists/$theirs/tasks", array( 'title' => 'Sneaky' ) ),
				array( 'PATCH', '/tasks/' . $their_ids['Y'], array( 'title' => 'Hacked' ) ),
				array( 'DELETE', '/tasks/' . $their_ids['Z'] ),
				array( 'PATCH', "/lists/$theirs", array( 'members' => array( $me ) ) ),
				array( 'DELETE', "/lists/$theirs?force=true" ),
				array( 'PATCH', '/tasks/' . $my_ids['X'], array( 'status' => 'done' ) ),
			)
		);
		$responses = $this->responses( $res, 7 );
		$this->assertSame( array( 201, 403, 403, 403, 403, 403, 200 ), $this->statuses( $responses ), substr( $res['body'], 0, 3000 ) );

		$this->assertSame( $their_tasks, $this->tasks( $theirs ) );
		$after = $this->api( $them, 'GET', "/lists/$theirs" );
		$this->assertSame( 200, $after['status'] );
		$this->assertSame( $their_list_before['members'], $after['json']['members'] );
		$this->assertOrder( array( 'X', 'Mine' ), $mine );
		$this->assertSame( 'done', $this->tasks( $mine )[0]['status'] );

		// Permission failures are not validation failures.
		$res       = $this->batch(
			$me,
			array(
				array( 'POST', "/lists/$mine/tasks", array( 'title' => 'Also mine' ) ),
				array( 'PATCH', '/tasks/' . $their_ids['Y'], array( 'title' => 'Hacked again' ) ),
			),
			'require-all-validate'
		);
		$responses = $this->responses( $res, 2 );
		$this->assertArrayNotHasKey( 'failed', $res['json'] );
		$this->assertSame( array( 201, 403 ), $this->statuses( $responses ) );
		$this->assertSame( $their_tasks, $this->tasks( $theirs ) );

		// A member may work on tasks, but not change the list itself.
		$res       = $this->batch(
			$their_mb,
			array(
				array( 'PATCH', "/lists/$theirs", array( 'title' => 'Member list now' ) ),
				array( 'PATCH', '/tasks/' . $their_ids['Y'], array( 'notes' => 'checked by member' ) ),
			)
		);
		$responses = $this->responses( $res, 2 );
		$this->assertSame( array( 403, 200 ), $this->statuses( $responses ) );
		$this->assertSame( $their_list_before['title'], $this->api( $them, 'GET', "/lists/$theirs" )['json']['title'] );
	}

	public function test_logged_out_and_subscriber_batches_write_nothing(): void {
		$owner = $this->user( 'author' );
		$sub   = $this->user( 'subscriber' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B' ) );
		$before = $this->tasks( $list );

		$ops = array(
			array( 'POST', "/lists/$list/tasks", array( 'title' => 'Anonymous' ) ),
			array( 'PATCH', '/tasks/' . $ids['A'], array( 'title' => 'Defaced' ) ),
			array( 'DELETE', '/tasks/' . $ids['B'] ),
			array( 'POST', '/lists', array( 'title' => 'Spam list' ) ),
		);
		$res = $this->batch( null, $ops );
		$this->assertSame( array( 401, 401, 401, 401 ), $this->statuses( $this->responses( $res, 4 ) ), substr( $res['body'], 0, 2000 ) );

		$res = $this->batch( $sub, $ops );
		$this->assertSame( array( 403, 403, 403, 403 ), $this->statuses( $this->responses( $res, 4 ) ), substr( $res['body'], 0, 2000 ) );

		$this->assertSame( $before, $this->tasks( $list ) );
		$this->assertNotContains( 'Spam list', array_column( $this->api( 1, 'GET', '/lists' )['json'], 'title' ) );
	}

	public function test_positions_follow_the_order_of_the_batch(): void {
		$owner = $this->user( 'author' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C', 'D' ) );

		$res       = $this->batch(
			$owner,
			array(
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'E' ) ),
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'F', 'position' => 0 ) ),
				array( 'PATCH', '/tasks/' . $ids['A'], array( 'position' => 3 ) ),
				array( 'DELETE', '/tasks/' . $ids['B'] ),
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'G', 'position' => 99 ) ),
				array( 'PUT', '/tasks/' . $ids['D'], array( 'position' => 0, 'title' => 'D first' ) ),
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'H', 'position' => 2 ) ),
			)
		);
		$responses = $this->responses( $res, 7 );
		$this->assertSame( array( 201, 201, 200, 200, 201, 200, 201 ), $this->statuses( $responses ), substr( $res['body'], 0, 3000 ) );
		$positions = array_map( static fn( $r ) => $r['body']['position'] ?? null, $responses );
		$this->assertSame( array( 4, 0, 3, null, 5, 0, 2 ), $positions );
		$this->assertOrder( array( 'D first', 'F', 'H', 'C', 'A', 'E', 'G' ), $list );
	}

	public function test_batch_on_a_legacy_list_with_gaps(): void {
		$bob  = $this->seeded_user( 'bob' );
		$list = $this->seeded_list( 'office-move' );
		$ids  = $this->ids_by_title( $list );
		$this->assertSame( array( 'Pack desks', 'Label boxes', 'Book movers', 'Update address' ), array_keys( $ids ) );

		$res       = $this->batch(
			$bob,
			array(
				array( 'POST', "/lists/$list/tasks", array( 'title' => 'Clean kitchen' ) ),
				array( 'PATCH', '/tasks/' . $ids['Update address'], array( 'position' => 0 ) ),
				array( 'DELETE', '/tasks/' . $ids['Label boxes'] ),
			)
		);
		$responses = $this->responses( $res, 3 );
		$this->assertSame( array( 201, 200, 200 ), $this->statuses( $responses ), substr( $res['body'], 0, 3000 ) );
		$this->assertSame( 4, $responses[0]['body']['position'] );
		$this->assertOrder( array( 'Update address', 'Pack desks', 'Book movers', 'Clean kitchen' ), $list );
	}

	public function test_batches_of_up_to_50_requests_are_accepted_and_larger_ones_rejected(): void {
		$owner = $this->user( 'author' );
		$list  = $this->make_list( $owner, 'Big sync' );

		$ops = array();
		for ( $i = 1; $i <= 50; $i++ ) {
			$ops[] = array( 'POST', "/lists/$list/tasks", array( 'title' => sprintf( 'T%02d', $i ) ) );
		}
		$res       = $this->batch( $owner, $ops );
		$responses = $this->responses( $res, 50 );
		$this->assertSame( array_fill( 0, 50, 201 ), $this->statuses( $responses ) );
		$this->assertOrder( array_map( static fn( $i ) => sprintf( 'T%02d', $i ), range( 1, 50 ) ), $list );

		$other = $this->make_list( $owner, 'Too big' );
		$ops   = array();
		for ( $i = 1; $i <= 51; $i++ ) {
			$ops[] = array( 'POST', "/lists/$other/tasks", array( 'title' => sprintf( 'U%02d', $i ) ) );
		}
		$res = $this->batch( $owner, $ops );
		$this->assertSame( 400, $res['status'], substr( $res['body'], 0, 1000 ) );
		$this->assertSame( array(), $this->tasks( $other ) );
	}

	public function test_list_operations_in_a_batch(): void {
		$owner  = $this->user( 'author' );
		$member = $this->user( 'contributor' );
		list( $keep )           = $this->list_with_tasks( $owner, array( 'k1' ) );
		list( $trash )          = $this->list_with_tasks( $owner, array( 't1' ) );
		list( $gone, $gone_ids ) = $this->list_with_tasks( $owner, array( 'g1', 'g2' ) );

		$res       = $this->batch(
			$owner,
			array(
				array( 'POST', '/lists', array( 'title' => 'Batch list', 'color' => '#ABCDEF', 'members' => array( $member ) ) ),
				array( 'PATCH', "/lists/$keep", array( 'title' => 'Kept and renamed', 'archived' => true ) ),
				array( 'DELETE', "/lists/$trash" ),
				array( 'DELETE', "/lists/$gone?force=true" ),
			)
		);
		$responses = $this->responses( $res, 4 );
		$this->assertSame( array( 201, 200, 200, 200 ), $this->statuses( $responses ), substr( $res['body'], 0, 3000 ) );

		$created = $responses[0]['body'];
		$this->assertSame( '#abcdef', $created['color'] );
		$this->assertSame( array( $member ), $created['members'] );
		$this->assertSame( $owner, $created['owner'] );
		$this->assertTrue( $responses[1]['body']['archived'] );
		$this->assertSame( 'trash', $responses[2]['body']['status'] );
		$this->assertTrue( $responses[3]['body']['deleted'] ?? null, substr( $res['body'], 0, 3000 ) );
		$this->assertSame( $gone, $responses[3]['body']['previous']['id'] );

		clean_post_cache( $gone );
		clean_post_cache( $trash );
		$this->assertNull( get_post( $gone ), 'force=true must delete the list permanently' );
		$this->assertSame( 'trash', get_post_status( $trash ) );
		$this->assertSame( 404, $this->api( 1, 'GET', '/tasks/' . $gone_ids['g1'] )['status'] );

		$archived = array_column( $this->api( $owner, 'GET', '/lists', null )['json'], 'title' );
		$this->assertNotContains( 'Kept and renamed', $archived );
		$res = $this->http( 'GET', '/wp-json/acme-tasks/v1/lists?archived=true', array( 'login' => $this->login_for( $owner ), 'rest_nonce' => true ) );
		$this->assertContains( 'Kept and renamed', array_column( $res['json'], 'title' ) );
		$this->assertContains( 'Batch list', array_column( $res['json'], 'title' ) );
	}
}
