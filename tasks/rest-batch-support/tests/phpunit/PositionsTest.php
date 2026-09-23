<?php
/**
 * Positions stay 0..n-1 in display order after every create/move/delete (direct requests).
 */

class PositionsTest extends AcmeTasksCase {

	public function test_delete_closes_the_gap(): void {
		$owner = $this->user( 'author' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C', 'D' ) );

		$this->assertSame( 200, $this->api( $owner, 'DELETE', '/tasks/' . $ids['B'] )['status'] );
		$this->assertOrder( array( 'A', 'C', 'D' ), $list );

		// Appending after a delete must not collide with an existing position.
		$e = $this->make_task( $owner, $list, 'E' );
		$this->assertSame( 3, $e['position'] );
		$this->assertOrder( array( 'A', 'C', 'D', 'E' ), $list );

		$this->assertSame( 200, $this->api( $owner, 'DELETE', '/tasks/' . $ids['A'] )['status'] );
		$this->assertOrder( array( 'C', 'D', 'E' ), $list );
	}

	public function test_positions_past_the_end_mean_last(): void {
		$owner = $this->user( 'author' );
		list( $list, $ids ) = $this->list_with_tasks( $owner, array( 'A', 'B', 'C' ) );

		$far = $this->make_task( $owner, $list, 'Far', array( 'position' => 99 ) );
		$this->assertSame( 3, $far['position'] );
		$this->assertOrder( array( 'A', 'B', 'C', 'Far' ), $list );

		$res = $this->api( $owner, 'PATCH', '/tasks/' . $ids['A'], array( 'position' => 50 ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 3, $res['json']['position'] );
		$this->assertOrder( array( 'B', 'C', 'Far', 'A' ), $list );

		$z = $this->make_task( $owner, $list, 'Z' );
		$this->assertSame( 4, $z['position'] );
		$this->assertOrder( array( 'B', 'C', 'Far', 'A', 'Z' ), $list );
	}

	public function test_legacy_list_with_gaps_is_normalized_by_the_first_change(): void {
		$alice = $this->seeded_user( 'alice' );
		$list  = $this->seeded_list( 'onboarding-checklist' );

		// Seeded with positions 0, 2, 2, 5, 9 (order: position, then creation).
		$this->assertSame(
			array( 'Order laptop', 'Create accounts', 'Print badge', 'Intro meeting', 'Read the handbook' ),
			array_column( $this->tasks( $list ), 'title' )
		);

		$handbook = $this->tasks( $list )[4]['id'];
		$res      = $this->api( $alice, 'PATCH', "/tasks/$handbook", array( 'position' => 1 ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertSame( 1, $res['json']['position'] );
		$this->assertOrder( array( 'Order laptop', 'Read the handbook', 'Create accounts', 'Print badge', 'Intro meeting' ), $list );

		$new = $this->make_task( $alice, $list, 'Lunch with the team' );
		$this->assertSame( 5, $new['position'] );
		$this->assertOrder( array( 'Order laptop', 'Read the handbook', 'Create accounts', 'Print badge', 'Intro meeting', 'Lunch with the team' ), $list );
	}
}
