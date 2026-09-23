<?php
/**
 * Personal data export: the registered exporters, driven page by page like core does.
 */

use function WPSB\Loyalty\group;
use function WPSB\Loyalty\ledger_rows;
use function WPSB\Loyalty\order_id;
use function WPSB\Loyalty\pairs;
use function WPSB\Loyalty\run_exporters;
use function WPSB\Loyalty\subscriber;
use function WPSB\Loyalty\user_id;
use function WPSB\Loyalty\value;
use const WPSB\Loyalty\GINA;
use const WPSB\Loyalty\JANE;
use const WPSB\Loyalty\MARCO;

class ExportTest extends WPSB\TestCase {

	private static array $cache = array();

	private function export( string $email ): array {
		if ( ! isset( self::$cache[ $email ] ) ) {
			self::$cache[ $email ] = run_exporters( $email );
		}
		return self::$cache[ $email ];
	}

	public function test_every_export_step_returns_at_most_100_items(): void {
		foreach ( array( JANE, GINA, MARCO ) as $email ) {
			foreach ( $this->export( $email )['log'] as list( $key, $page, $count ) ) {
				$this->assertLessThanOrEqual( 100, $count, "exporter $key page $page returned $count items for $email" );
			}
		}
	}

	public function test_membership_profile_of_a_2x_member(): void {
		$items = group( $this->export( JANE )['items'], 'acme-loyalty-membership' );
		$this->assertCount( 1, $items, 'Expected exactly one membership item' );
		$item = $items[0];
		$this->assertSame( 'acme-loyalty-member-' . user_id( 'jane' ), $item['item_id'] );
		$this->assertSame( 'Loyalty membership', $item['group_label'] );
		$this->assertSame( 'Gold', value( $item, 'Tier' ) );
		$this->assertSame( '+1 555 0100', value( $item, 'Phone' ) );
		$this->assertSame( 'JANE2019', value( $item, 'Referral code' ) );
		$this->assertSame( 'Email, SMS', value( $item, 'Preferred contact channels' ) );
		$this->assertSame( 'Downtown', value( $item, 'Favourite store' ) );
		$this->assertNotNull( value( $item, 'Birthday' ) );
		$this->assertStringContainsString( '1988', (string) value( $item, 'Birthday' ) );
		$this->assertNotNull( value( $item, 'Member since' ) );
		$this->assertStringContainsString( '2019', (string) value( $item, 'Member since' ) );
		$balance = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT SUM(points) FROM ' . WPSB\Loyalty\ledger() . ' WHERE user_id = %d', user_id( 'jane' ) ) );
		$this->assertSame( $balance, (int) preg_replace( '/[^\d-]/', '', (string) value( $item, 'Points balance' ) ) );
	}

	public function test_membership_profile_saved_by_1x_is_exported(): void {
		$items = group( $this->export( MARCO )['items'], 'acme-loyalty-membership' );
		$this->assertCount( 1, $items );
		$item = $items[0];
		$this->assertSame( 'acme-loyalty-member-' . user_id( 'marco' ), $item['item_id'] );
		$this->assertStringContainsString( '1979', (string) value( $item, 'Birthday' ), '1.x birthday (acme_loyalty_dob) missing' );
		$this->assertSame( 'SMS, Post', value( $item, 'Preferred contact channels' ) );
		$this->assertSame( 'Harbour', value( $item, 'Favourite store' ) );
		$this->assertSame( '+1 555 0142', value( $item, 'Phone' ) );
	}

	public function test_points_history_is_complete_paged_and_exported_once(): void {
		$export = $this->export( JANE );
		$items  = group( $export['items'], 'acme-loyalty-points' );
		$jane   = user_id( 'jane' );
		$rows   = ledger_rows( 'user_id = ' . $jane );
		$this->assertCount( 250, $rows );

		$ids = array_map( static fn( $i ) => $i['item_id'], $items );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ), 'Ledger entries exported more than once' );
		$expected = array_map( static fn( $id ) => 'acme-loyalty-ledger-' . $id, array_keys( $rows ) );
		sort( $ids );
		sort( $expected );
		$this->assertSame( $expected, $ids, 'Every ledger entry (also those with her old address) must be exported exactly once' );

		$points_pages = array_filter( $export['log'], static fn( $l ) => $l[2] > 0 );
		$this->assertGreaterThanOrEqual( 3, count( $points_pages ), 'Expected the export to be paged' );

		$this->assertSame( 'Loyalty points history', $items[0]['group_label'] );
		// Spot-check one entry.
		$some = null;
		foreach ( $rows as $id => $row ) {
			if ( 'redeem' === $row['reason'] ) {
				$some = $row;
				break;
			}
		}
		$item = array_values( array_filter( $items, static fn( $i ) => 'acme-loyalty-ledger-' . $some['id'] === $i['item_id'] ) )[0];
		$this->assertSame( '-100', preg_replace( '/[^\d-]/', '', (string) value( $item, 'Points' ) ) );
		$this->assertSame( $some['ip_address'], value( $item, 'IP address' ) );
		$this->assertSame( 'Free bag of Harbour Blend', value( $item, 'Note' ) );
		$this->assertNotNull( value( $item, 'Date' ) );
		$this->assertNotNull( value( $item, 'Reason' ) );
	}

	public function test_newsletter_rows_are_matched_case_insensitively_but_exactly(): void {
		$items = group( $this->export( JANE )['items'], 'acme-loyalty-newsletter' );
		$row   = subscriber( 'Jane.Doe@Example.com' );
		$this->assertNotNull( $row );
		$this->assertSame( array( 'acme-loyalty-subscriber-' . $row['id'] ), array_map( static fn( $i ) => $i['item_id'], $items ), 'Only the 1.x row "Jane.Doe@Example.com" belongs to Jane' );
		$this->assertSame( 'Newsletter subscription', $items[0]['group_label'] );
		$this->assertSame( 'Jane.Doe@Example.com', value( $items[0], 'Email' ) );
		$this->assertSame( 'Jane', value( $items[0], 'First name' ) );
		$this->assertSame( '203.0.113.99', value( $items[0], 'IP address' ) );
		$this->assertNotNull( value( $items[0], 'Status' ) );
		$this->assertNotNull( value( $items[0], 'Subscribed on' ) );
		$this->assertNotNull( value( $items[0], 'Signup source' ) );
		$this->assertNull( value( $items[0], 'Unsubscribed on' ), 'Empty values must be left out' );
	}

	public function test_newsletter_row_linked_to_the_account_is_exported(): void {
		$items = group( run_exporters( 'sam@example.org' )['items'], 'acme-loyalty-newsletter' );
		$this->assertCount( 1, $items );
		$this->assertSame( 'sam.work@example.org', value( $items[0], 'Email' ) );
	}

	public function test_orders_with_notes(): void {
		$items   = group( $this->export( JANE )['items'], 'acme-loyalty-orders' );
		$numbers = array();
		foreach ( $items as $item ) {
			$numbers[ value( $item, 'Order number' ) ] = $item;
		}
		ksort( $numbers );
		$this->assertSame( array( 'AC-0950', 'AC-1001', 'AC-1002', 'AC-1003' ), array_keys( $numbers ), 'Account orders + her guest order from 2018 (different casing), nobody else\'s' );
		$this->assertSame( 'acme-loyalty-order-' . order_id( 'AC-1001' ), $numbers['AC-1001']['item_id'] );
		$this->assertSame( 'Orders', $numbers['AC-1001']['group_label'] );

		$p = pairs( $numbers['AC-1001'] );
		$this->assertSame( array( 'Leave at the back door' ), $p['Customer note'] ?? null, '1.x delivery note is a customer note' );
		$this->assertSame( array( 'Your order has shipped' ), $p['Store note'] ?? null );
		$this->assertStringNotContainsString( 'grinder setting', wp_json_encode( $numbers['AC-1001'] ), 'Internal staff notes must not be exported' );
		$this->assertSame( '32.50', value( $numbers['AC-1001'], 'Total' ) );
		$this->assertNotNull( value( $numbers['AC-1001'], 'Status' ) );
		$this->assertNotNull( value( $numbers['AC-1001'], 'Order date' ) );

		$this->assertSame( array( 'Deliver to reception desk' ), pairs( $numbers['AC-0950'] )['Customer note'] ?? null );
		$this->assertSame( array( 'Ring twice, flat 4B' ), pairs( $numbers['AC-1003'] )['Customer note'] ?? null );
	}

	public function test_guest_without_account(): void {
		$items = $this->export( GINA )['items'];
		$this->assertCount( 0, group( $items, 'acme-loyalty-membership' ) );
		$this->assertCount( 0, group( $items, 'acme-loyalty-points' ) );
		$news = group( $items, 'acme-loyalty-newsletter' );
		$this->assertCount( 1, $news );
		$this->assertSame( 'Guest.Gina@Example.NET', value( $news[0], 'Email' ) );
		$this->assertNotNull( value( $news[0], 'Unsubscribed on' ) );
		$orders = array_map( static fn( $i ) => value( $i, 'Order number' ), group( $items, 'acme-loyalty-orders' ) );
		sort( $orders );
		$this->assertSame( array( 'AC-1020', 'AC-1021' ), $orders );
	}

	public function test_ledger_of_a_deleted_account_is_found_by_email(): void {
		$items = group( run_exporters( 'former.member@example.com' )['items'], 'acme-loyalty-points' );
		$this->assertCount( 3, $items );
	}

	public function test_lookalike_addresses_and_unknown_people_get_nothing(): void {
		foreach ( array( 'jane.doe@example.co', 'nobody@example.com', 'jane@example.com' ) as $email ) {
			$items = array_filter( run_exporters( $email )['items'], static fn( $i ) => 0 === strpos( $i['group_id'], 'acme-loyalty-' ) );
			if ( 'jane.doe@example.co' === $email ) {
				$groups = array_count_values( array_map( static fn( $i ) => $i['group_id'], $items ) );
				ksort( $groups );
				$this->assertSame( array( 'acme-loyalty-newsletter' => 1, 'acme-loyalty-orders' => 1 ), $groups );
			} else {
				$this->assertSame( array(), array_values( $items ), "Nothing belongs to $email" );
			}
		}
		// Janet's export never contains Jane's data.
		$janet = wp_json_encode( run_exporters( 'janet.doe@example.com' )['items'] );
		$this->assertStringNotContainsString( 'JANE2019', $janet );
		$this->assertStringNotContainsString( 'Leave at the back door', $janet );
		$this->assertStringContainsString( 'Janet note', $janet );
	}
}
