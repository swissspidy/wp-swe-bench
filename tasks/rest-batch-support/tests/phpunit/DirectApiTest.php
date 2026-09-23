<?php
/**
 * Single (non-batch) requests: existing clients must keep working exactly as before.
 */

class DirectApiTest extends AcmeTasksCase {

	public function test_json_client_crud_roundtrip(): void {
		$owner  = $this->user( 'author' );
		$member = $this->user( 'contributor' );
		$list   = $this->make_list( $owner, 'Roundtrip', array( 'members' => array( $member ), 'color' => '#AA0000' ) );

		$res = $this->api( $owner, 'GET', "/lists/$list" );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( '#aa0000', $res['json']['color'] );
		$this->assertSame( array( $member ), $res['json']['members'] );
		$this->assertSame( $owner, $res['json']['owner'] );

		$task = $this->make_task(
			$owner,
			$list,
			'  Write <b>report</b> ',
			array(
				'notes'    => "Line one\nLine two",
				'due_date' => '2026-12-24',
				'assignee' => $member,
			)
		);
		$this->assertSame( 'Write report', $task['title'] );
		$this->assertSame( "Line one\nLine two", $task['notes'] );
		$this->assertSame( 'open', $task['status'] );
		$this->assertFalse( $task['completed'] );
		$this->assertSame( 0, $task['position'] );
		$this->assertSame( '2026-12-24', $task['due_date'] );
		$this->assertSame( $member, $task['assignee'] );
		$this->assertSame( $list, $task['list_id'] );
		$this->assertSame( $owner, $task['created_by'] );
		$this->assertNull( $task['completed_at'] );

		$id  = $task['id'];
		$res = $this->api( $member, 'PATCH', "/tasks/$id", array( 'status' => 'done' ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 'done', $res['json']['status'] );
		$this->assertTrue( $res['json']['completed'] );
		$this->assertNotNull( $res['json']['completed_at'] );

		// The admin screen still toggles through the deprecated flag.
		$res = $this->api( $member, 'POST', "/tasks/$id", array( 'completed' => false ), false, array( 'X-HTTP-Method-Override' => 'PATCH' ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 'open', $res['json']['status'] );
		$this->assertNull( $res['json']['completed_at'] );

		$res = $this->api( $owner, 'PUT', "/tasks/$id", array( 'due_date' => '', 'assignee' => 0 ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertNull( $res['json']['due_date'] );
		$this->assertSame( 0, $res['json']['assignee'] );
		$this->assertSame( 'Write report', $res['json']['title'] );

		$res = $this->api( $member, 'DELETE', "/tasks/$id" );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertTrue( $res['json']['deleted'] );
		$this->assertSame( $id, $res['json']['previous']['id'] );
		$this->assertSame( 'Write report', $res['json']['previous']['title'] );

		$this->assertSame( 404, $this->api( $owner, 'GET', "/tasks/$id" )['status'] );
		$this->assertSame( 404, $this->api( $owner, 'DELETE', "/tasks/$id" )['status'] );
		$this->assertSame( array(), $this->tasks( $list ) );
	}

	public function test_form_encoded_clients_still_work(): void {
		$owner  = $this->user( 'author' );
		$member = $this->user( 'author' );
		$list   = $this->make_list( $owner, 'Zapier inbox', array( 'members' => array( $member ) ) );
		$this->make_task( $owner, $list, 'Existing' );

		$res = $this->api(
			$owner,
			'POST',
			"/lists/$list/tasks",
			array(
				'title'    => 'From Zapier',
				'notes'    => 'via webhook',
				'due_date' => '2027-01-31',
				'assignee' => (string) $member,
				'position' => '',
			),
			true
		);
		$this->assertSame( 201, $res['status'], $res['body'] );
		$this->assertSame( 'From Zapier', $res['json']['title'] );
		$this->assertSame( '2027-01-31', $res['json']['due_date'] );
		$this->assertSame( $member, $res['json']['assignee'] );
		$this->assertSame( 1, $res['json']['position'] );

		$id  = $res['json']['id'];
		$res = $this->api( $owner, 'POST', "/tasks/$id", array( 'title' => 'Renamed by Zapier', 'completed' => '1' ), true );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 'Renamed by Zapier', $res['json']['title'] );
		$this->assertSame( 'done', $res['json']['status'] );

		$res = $this->api( $owner, 'POST', "/lists/$list/tasks", array( 'title' => '' ), true );
		$this->assertSame( 400, $res['status'], $res['body'] );

		$res = $this->api( $owner, 'POST', '/lists', array( 'title' => 'Form list', 'color' => '#00FF00' ), true );
		$this->assertSame( 201, $res['status'], $res['body'] );
		$this->assertSame( '#00ff00', $res['json']['color'] );

		$this->assertSame( array( 'Existing', 'Renamed by Zapier' ), array_column( $this->tasks( $list ), 'title' ) );
	}

	/**
	 * @return array<string, array{0: string, 1: array}>
	 */
	public static function invalid_task_input(): array {
		return array(
			'missing title'     => array( 'create', array( 'notes' => 'no title' ) ),
			'blank title'       => array( 'create', array( 'title' => "   \t " ) ),
			'title too long'    => array( 'create', array( 'title' => str_repeat( 'x', 201 ) ) ),
			'unknown status'    => array( 'update', array( 'status' => 'archived' ) ),
			'bad completed'     => array( 'update', array( 'completed' => 'maybe' ) ),
			'impossible date'   => array( 'create', array( 'title' => 'x', 'due_date' => '2026-02-30' ) ),
			'US date'           => array( 'update', array( 'due_date' => '10/01/2026' ) ),
			'outsider assignee' => array( 'update', array( 'assignee' => 'OUTSIDER' ) ),
			'unknown assignee'  => array( 'create', array( 'title' => 'x', 'assignee' => 987654 ) ),
			'negative position' => array( 'update', array( 'position' => -1 ) ),
			'text position'     => array( 'create', array( 'title' => 'x', 'position' => 'top' ) ),
			'empty title edit'  => array( 'update', array( 'title' => '' ) ),
		);
	}

	/**
	 * @dataProvider invalid_task_input
	 */
	public function test_invalid_task_input_is_rejected_without_changes( string $op, array $body ): void {
		$owner    = $this->user( 'author' );
		$outsider = $this->user( 'author' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'One', 'Two' ) );
		$before = $this->tasks( $list );
		array_walk_recursive(
			$body,
			static function ( &$v ) use ( $outsider ) {
				if ( 'OUTSIDER' === $v ) {
					$v = $outsider;
				}
			}
		);

		if ( 'update' === $op ) {
			$res = $this->api( $owner, 'PATCH', '/tasks/' . $ids['Two'], $body );
		} else {
			$res = $this->api( $owner, 'POST', "/lists/$list/tasks", $body );
		}
		$this->assertSame( 400, $res['status'], $res['body'] );
		$this->assertSame( $before, $this->tasks( $list ) );
	}

	public function test_invalid_list_input_is_rejected(): void {
		$owner      = $this->user( 'author' );
		$subscriber = $this->user( 'subscriber' );
		$this->assertSame( 400, $this->api( $owner, 'POST', '/lists', array( 'title' => 'Red', 'color' => 'red' ) )['status'] );
		$this->assertSame( 400, $this->api( $owner, 'POST', '/lists', array( 'title' => 'Subs', 'members' => array( $subscriber ) ) )['status'] );
		$this->assertSame( 400, $this->api( $owner, 'POST', '/lists', array( 'color' => '#000000' ) )['status'] );
		$list = $this->make_list( $owner, 'Valid' );
		$this->assertSame( 400, $this->api( $owner, 'PATCH', "/lists/$list", array( 'title' => ' ' ) )['status'] );
		$this->assertSame( 400, $this->api( $owner, 'PATCH', "/lists/$list", array( 'color' => '#12345' ) )['status'] );
		$this->assertSame( 400, $this->api( $owner, 'PATCH', "/lists/$list", array( 'archived' => 'perhaps' ) )['status'] );
		$res = $this->api( $owner, 'GET', "/lists/$list" );
		$this->assertSame( 'Valid', $res['json']['title'] );
		$this->assertFalse( $res['json']['archived'] );
		$titles = array_column( $this->api( $owner, 'GET', '/lists' )['json'], 'title' );
		$this->assertNotContains( 'Red', $titles );
		$this->assertNotContains( 'Subs', $titles );
	}

	public function test_permissions_of_direct_requests(): void {
		$owner    = $this->user( 'author' );
		$member   = $this->user( 'contributor' );
		$outsider = $this->user( 'author' );
		$editor   = $this->user( 'editor' );
		$sub      = $this->user( 'subscriber' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'Secret' ), array( 'members' => array( $member ) ) );
		$task = $ids['Secret'];

		foreach ( array(
			array( 'GET', "/lists/$list", null ),
			array( 'GET', "/lists/$list/tasks", null ),
			array( 'POST', "/lists/$list/tasks", array( 'title' => 'Intruder' ) ),
			array( 'PATCH', "/tasks/$task", array( 'title' => 'Hacked' ) ),
			array( 'DELETE', "/tasks/$task", null ),
			array( 'PATCH', "/lists/$list", array( 'title' => 'Hacked' ) ),
			array( 'DELETE', "/lists/$list?force=true", null ),
		) as $r ) {
			$this->assertSame( 403, $this->api( $outsider, $r[0], $r[1], $r[2] )['status'], "outsider {$r[0]} {$r[1]}" );
			$this->assertSame( 401, $this->api( null, $r[0], $r[1], $r[2] )['status'], "logged out {$r[0]} {$r[1]}" );
		}
		$this->assertSame( 403, $this->api( $sub, 'POST', '/lists', array( 'title' => 'Nope' ) )['status'] );
		$this->assertSame( 403, $this->api( $sub, 'GET', '/lists' )['status'] );

		// Members work on tasks but may not change or delete the list itself.
		$this->assertSame( 403, $this->api( $member, 'PATCH', "/lists/$list", array( 'title' => 'Mine now' ) )['status'] );
		$this->assertSame( 403, $this->api( $member, 'DELETE', "/lists/$list" )['status'] );
		$this->assertSame( 200, $this->api( $member, 'PATCH', "/tasks/$task", array( 'notes' => 'member note' ) )['status'] );

		// Editors can work on every list.
		$this->assertSame( 200, $this->api( $editor, 'PATCH', "/lists/$list", array( 'color' => '#111111' ) )['status'] );

		$this->assertSame( 404, $this->api( $owner, 'POST', '/lists/999999/tasks', array( 'title' => 'x' ) )['status'] );
		$this->assertSame( 404, $this->api( $owner, 'PATCH', '/tasks/999999', array( 'title' => 'x' ) )['status'] );

		$tasks = $this->tasks( $list );
		$this->assertCount( 1, $tasks );
		$this->assertSame( 'Secret', $tasks[0]['title'] );
		$this->assertSame( 'member note', $tasks[0]['notes'] );
	}

	public function test_list_delete_trashes_or_deletes_permanently(): void {
		$owner = $this->user( 'author' );
		list( $trash_list ) = $this->list_with_tasks( $owner, array( 'Keep me' ) );
		list( $gone_list, $ids ) = $this->list_with_tasks( $owner, array( 'Delete me' ) );

		$res = $this->api( $owner, 'DELETE', "/lists/$trash_list" );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 'trash', $res['json']['status'] );
		clean_post_cache( $trash_list );
		$this->assertSame( 'trash', get_post_status( $trash_list ) );

		$res = $this->api( $owner, 'DELETE', "/lists/$gone_list?force=true" );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertTrue( $res['json']['deleted'] );
		$this->assertSame( $gone_list, $res['json']['previous']['id'] );
		clean_post_cache( $gone_list );
		$this->assertNull( get_post( $gone_list ) );
		$this->assertSame( 404, $this->api( 1, 'GET', '/tasks/' . $ids['Delete me'] )['status'] );
	}

	public function test_moves_on_a_clean_list(): void {
		$owner = $this->user( 'author' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C' ) );
		$this->assertOrder( array( 'A', 'B', 'C' ), $list );

		$d = $this->make_task( $owner, $list, 'D', array( 'position' => 1 ) );
		$this->assertSame( 1, $d['position'] );
		$this->assertOrder( array( 'A', 'D', 'B', 'C' ), $list );

		$res = $this->api( $owner, 'PATCH', '/tasks/' . $ids['C'], array( 'position' => 0 ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( 0, $res['json']['position'] );
		$this->assertOrder( array( 'C', 'A', 'D', 'B' ), $list );

		$this->api( $owner, 'PATCH', '/tasks/' . $ids['C'], array( 'position' => 2 ) );
		$this->assertOrder( array( 'A', 'D', 'C', 'B' ), $list );

		$res = $this->api( $owner, 'POST', "/lists/$list/reorder", array( 'order' => array( $ids['B'], $ids['A'] ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertOrder( array( 'B', 'A', 'D', 'C' ), $list );
	}

	public function test_activity_feed_and_assignment_mails(): void {
		$owner  = $this->user( 'author' );
		$member = $this->user( 'author' );
		$list   = $this->make_list( $owner, 'Activity', array( 'members' => array( $member ) ) );

		$task = $this->make_task( $owner, $list, 'Assigned directly', array( 'assignee' => $member ) );
		$this->api( $member, 'PATCH', '/tasks/' . $task['id'], array( 'status' => 'done' ) );
		$other = $this->make_task( $owner, $list, 'Unassigned' );
		$this->api( $owner, 'PATCH', '/tasks/' . $other['id'], array( 'assignee' => $member ) );
		$this->api( $owner, 'DELETE', '/tasks/' . $other['id'] );

		$actions = array_column( $this->activity( $list ), 'action' );
		$this->assertSame( array( 'task_deleted', 'task_updated', 'task_created', 'task_completed', 'task_created' ), $actions );

		$mails = $this->mails_to( $member );
		$this->assertCount( 2, $mails );
		$this->assertStringContainsString( 'Assigned directly', $mails[0]['subject'] );
		$this->assertStringContainsString( 'Unassigned', $mails[1]['subject'] );
	}
}
