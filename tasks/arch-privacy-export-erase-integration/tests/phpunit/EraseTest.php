<?php
/**
 * Personal data erasure: the registered erasers, driven page by page like core does.
 * Each test runs in a transaction (rolled back).
 */

use function WPSB\Loyalty\ledger;
use function WPSB\Loyalty\ledger_rows;
use function WPSB\Loyalty\loyalty_meta_keys;
use function WPSB\Loyalty\order_id;
use function WPSB\Loyalty\order_state;
use function WPSB\Loyalty\run_erasers;
use function WPSB\Loyalty\subscriber;
use function WPSB\Loyalty\subs;
use function WPSB\Loyalty\user_id;
use const WPSB\Loyalty\GINA;
use const WPSB\Loyalty\JANE;
use const WPSB\Loyalty\MARCO;

class EraseTest extends WPSB\TestCase {

	private function others_snapshot(): array {
		global $wpdb;
		$janet = user_id( 'janet' );
		$marco = user_id( 'marco' );
		return array(
			'janet_ledger' => ledger_rows( 'user_id = ' . $janet ),
			'marco_ledger' => ledger_rows( 'user_id = ' . $marco ),
			'janet_meta'   => loyalty_meta_keys( $janet ),
			'marco_meta'   => loyalty_meta_keys( $marco ),
			'lookalikes'   => array_map( static fn( $e ) => subscriber( $e ), array( 'mary-jane.doe@example.com', 'jane.doe@example.com.au', 'jane.doe@example.co', 'marco@example.org', 'sam.work@example.org' ) ),
			'orders'       => array_map( static fn( $n ) => order_state( order_id( $n ) ), array( 'AC-1100', 'AC-1101', 'AC-1010', 'AC-1020' ) ),
			'subs_count'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . subs() ),
		);
	}

	public function test_erasing_a_member_with_a_long_history(): void {
		global $wpdb;
		$jane   = user_id( 'jane' );
		$before = ledger_rows( 'user_id = ' . $jane );
		$this->assertCount( 250, $before );
		$others = $this->others_snapshot();
		$ac1003 = order_state( order_id( 'AC-1003' ) );

		$result = run_erasers( JANE );

		// Profile.
		wp_cache_flush();
		$this->assertSame( array(), loyalty_meta_keys( $jane ), 'All loyalty user meta must be deleted' );
		$this->assertInstanceOf( WP_User::class, get_userdata( $jane ), 'The account itself stays' );

		// Ledger: all 250 rows kept, anonymized, amounts unchanged.
		$after = ledger_rows( 'id IN (' . implode( ',', array_keys( $before ) ) . ')' );
		$this->assertCount( 250, $after, 'Ledger rows must never be deleted' );
		foreach ( $before as $id => $row ) {
			$this->assertSame( '0', (string) $after[ $id ]['user_id'], "ledger $id still linked" );
			$this->assertSame( '', (string) $after[ $id ]['email'], "ledger $id email" );
			$this->assertSame( '', (string) $after[ $id ]['ip_address'], "ledger $id ip" );
			$this->assertSame( '', (string) $after[ $id ]['note'], "ledger $id note" );
			foreach ( array( 'points', 'reason', 'order_id', 'created_at' ) as $col ) {
				$this->assertSame( (string) $row[ $col ], (string) $after[ $id ][ $col ], "ledger $id $col must not change" );
			}
		}
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ledger() . ' WHERE LOWER(email) IN (%s, %s)', 'jane.doe@example.com', 'jane@oldmail.example' ) ) );

		// Newsletter: the 1.x mixed-case row is gone.
		$this->assertNull( subscriber( 'Jane.Doe@Example.com' ) );

		// Finished orders: customer notes gone (incl. 1.x note), staff notes kept, unlinked.
		$ac1001 = order_state( order_id( 'AC-1001' ) );
		$this->assertSame( '', $ac1001['legacy'], '1.x delivery note must be removed' );
		$this->assertSame( array( 'Customer called about the grinder setting', 'Your order has shipped' ), array_column( (array) $ac1001['notes'], 'text' ) );
		$this->assertSame( 'deleted@site.invalid', $ac1001['email'] );
		$this->assertSame( 0, $ac1001['customer'] );
		$this->assertSame( array( 'AC-1001', 'completed', '32.50' ), array( $ac1001['number'], $ac1001['status'], $ac1001['total'] ) );

		$ac1002 = order_state( order_id( 'AC-1002' ) );
		$this->assertSame( array(), array_column( (array) $ac1002['notes'], 'text' ) );
		$this->assertSame( 'deleted@site.invalid', $ac1002['email'] );
		$this->assertSame( 0, $ac1002['customer'] );

		$ac0950 = order_state( order_id( 'AC-0950' ) );
		$this->assertSame( array(), array_column( (array) $ac0950['notes'], 'text' ), 'Guest order with "JANE.DOE@example.com" belongs to Jane' );
		$this->assertSame( 'deleted@site.invalid', $ac0950['email'] );

		// Processing order untouched.
		$this->assertSame( $ac1003, order_state( order_id( 'AC-1003' ) ), 'Orders still being processed must not change' );

		// Report.
		$this->assertTrue( $result['removed'] );
		$this->assertTrue( $result['retained'] );
		$this->assertContains( 'Points history entries were anonymized and kept for accounting.', $result['messages'] );
		$this->assertContains( 'Order AC-1003 is still being processed; it was not changed.', $result['messages'] );
		foreach ( $result['messages'] as $m ) {
			$this->assertStringNotContainsString( 'AC-1001', $m );
		}

		// Nobody else changed.
		$this->assertEquals( $others['janet_ledger'], ledger_rows( 'user_id = ' . user_id( 'janet' ) ) );
		$this->assertEquals( $others['marco_ledger'], ledger_rows( 'user_id = ' . user_id( 'marco' ) ) );
		$this->assertEquals( $others['janet_meta'], loyalty_meta_keys( user_id( 'janet' ) ) );
		$this->assertEquals( $others['marco_meta'], loyalty_meta_keys( user_id( 'marco' ) ) );
		$this->assertEquals( $others['lookalikes'], array_map( static fn( $e ) => subscriber( $e ), array( 'mary-jane.doe@example.com', 'jane.doe@example.com.au', 'jane.doe@example.co', 'marco@example.org', 'sam.work@example.org' ) ) );
		$this->assertEquals( $others['orders'], array_map( static fn( $n ) => order_state( order_id( $n ) ), array( 'AC-1100', 'AC-1101', 'AC-1010', 'AC-1020' ) ) );
		$this->assertSame( $others['subs_count'] - 1, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . subs() ) );
	}

	public function test_no_erasure_step_processes_more_than_100_rows(): void {
		$jane      = user_id( 'jane' );
		$ids       = array_keys( ledger_rows( 'user_id = ' . $jane ) );
		$list      = implode( ',', $ids );
		$anonymous = static fn() => (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . ledger() . " WHERE id IN ($list) AND user_id = 0" );
		$steps     = 0;
		$prev      = 0;
		$max_step  = 0;
		run_erasers(
			JANE,
			function () use ( &$prev, &$max_step, &$steps, $anonymous ) {
				$now      = $anonymous();
				$max_step = max( $max_step, $now - $prev );
				$prev     = $now;
				++$steps;
			}
		);
		$this->assertSame( 250, $anonymous(), 'All 250 entries must be anonymized in the end' );
		$this->assertLessThanOrEqual( 100, $max_step, 'A single eraser step anonymized more than 100 ledger rows' );
	}

	public function test_guest_erasure(): void {
		$gina_row = subscriber( 'Guest.Gina@Example.NET' );
		$this->assertNotNull( $gina_row );
		$ac1021 = order_state( order_id( 'AC-1021' ) );

		$result = run_erasers( GINA );

		$this->assertNull( subscriber( 'Guest.Gina@Example.NET' ) );
		$ac1020 = order_state( order_id( 'AC-1020' ) );
		$this->assertSame( array( 'Out of stock, substituted' ), array_column( (array) $ac1020['notes'], 'text' ) );
		$this->assertSame( 'deleted@site.invalid', $ac1020['email'] );
		$this->assertSame( $ac1021, order_state( order_id( 'AC-1021' ) ) );
		$this->assertTrue( $result['removed'] );
		$this->assertTrue( $result['retained'] );
		$this->assertContains( 'Order AC-1021 is still being processed; it was not changed.', $result['messages'] );
		$this->assertNotContains( 'Points history entries were anonymized and kept for accounting.', $result['messages'], 'Gina has no ledger entries' );
	}

	public function test_member_with_1x_profile(): void {
		$marco = user_id( 'marco' );
		$this->assertNotEmpty( get_user_meta( $marco, 'acme_loyalty_prefs', true ) );
		run_erasers( MARCO );
		wp_cache_flush();
		$this->assertSame( array(), loyalty_meta_keys( $marco ), '1.x keys (acme_loyalty_dob, acme_loyalty_prefs) must be deleted too' );
		$this->assertNull( subscriber( 'marco@example.org' ) );
		$this->assertSame( 0, count( ledger_rows( 'user_id = ' . $marco ) ) );
		$this->assertSame( array(), array_column( (array) order_state( order_id( 'AC-1010' ) )['notes'], 'text' ) );
	}

	public function test_subscription_linked_to_the_account_and_deleted_accounts(): void {
		run_erasers( 'sam@example.org' );
		$this->assertNull( subscriber( 'sam.work@example.org' ), 'Subscriber rows linked to the account belong to the person' );

		$result = run_erasers( 'former.member@example.com' );
		$this->assertSame( 0, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . ledger() . " WHERE LOWER(email) = 'former.member@example.com' OR user_id = 987654" ) );
		$this->assertSame( 3, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . ledger() . " WHERE user_id = 0 AND email = '' AND created_at LIKE '2020-0%'" ) );
		$this->assertTrue( $result['retained'] );
	}

	public function test_nothing_happens_for_unknown_people(): void {
		global $wpdb;
		$before = array(
			(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . subs() ),
			$wpdb->get_var( 'SELECT GROUP_CONCAT(user_id) FROM ' . ledger() ),
		);
		$result = run_erasers( 'jane@example.com' );
		$this->assertFalse( $result['removed'] );
		$this->assertFalse( $result['retained'] );
		$this->assertSame( $before, array( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . subs() ), $wpdb->get_var( 'SELECT GROUP_CONCAT(user_id) FROM ' . ledger() ) ) );
	}
}
