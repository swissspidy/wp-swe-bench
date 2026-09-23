<?php
/**
 * The daily retention clean-up (run in-process, rolled back after each test).
 */

use function WPSB\Loyalty\ledger_rows;
use function WPSB\Loyalty\subscriber;
use function WPSB\Loyalty\subs;

class RetentionTest extends WPSB\TestCase {

	private function run_cleanup(): void {
		$this->assertTrue( has_action( 'acme_loyalty_retention_cleanup' ) !== false, 'Nothing is hooked to acme_loyalty_retention_cleanup' );
		do_action( 'acme_loyalty_retention_cleanup' );
		wp_cache_flush();
	}

	private function set_months( $months ): void {
		$settings = get_option( 'acme_loyalty_settings' );
		$settings['retention_months'] = $months;
		// Bypass the settings sanitization: this is what the stored option looks like.
		remove_all_filters( 'sanitize_option_acme_loyalty_settings' );
		update_option( 'acme_loyalty_settings', $settings );
	}

	private function present( array $emails ): array {
		$out = array();
		foreach ( $emails as $e ) {
			$out[ $e ] = null !== subscriber( $e );
		}
		return $out;
	}

	private const EMAILS = array( 'old.pending@example.com', 'recent.pending@example.com', 'old.unsub@example.com', 'recent.unsub@example.com', 'legacy.unsub@example.com', 'loyal.fan@example.com', 'Guest.Gina@Example.NET', 'Jane.Doe@Example.com', 'marco@example.org' );

	public function test_default_retention_is_24_months_for_existing_settings(): void {
		$this->assertArrayNotHasKey( 'retention_months', get_option( 'acme_loyalty_settings' ), 'fixture: the stored option predates the setting' );
		$ledger_before = ledger_rows();
		$subs_before   = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . subs() );

		$this->run_cleanup();

		$this->assertSame(
			array(
				'old.pending@example.com'    => false,
				'recent.pending@example.com' => true,
				'old.unsub@example.com'      => false,
				'recent.unsub@example.com'   => true,
				'legacy.unsub@example.com'   => false,
				'loyal.fan@example.com'      => true,
				'Guest.Gina@Example.NET'     => true,
				'Jane.Doe@Example.com'       => true,
				'marco@example.org'          => true,
			),
			$this->present( self::EMAILS )
		);
		$this->assertSame( $subs_before - 3, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . subs() ), 'Only the three expired sign-ups may be deleted' );

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-24 months' ) );
		$margin = 3 * DAY_IN_SECONDS;
		$after  = ledger_rows();
		$this->assertSame( array_keys( $ledger_before ), array_keys( $after ), 'Ledger rows must never be deleted' );
		$cleared = 0;
		foreach ( $ledger_before as $id => $row ) {
			foreach ( array( 'user_id', 'email', 'points', 'reason', 'order_id', 'note', 'created_at' ) as $col ) {
				$this->assertSame( (string) $row[ $col ], (string) $after[ $id ][ $col ], "ledger $id $col must not change" );
			}
			$age = strtotime( $row['created_at'] . ' UTC' );
			if ( $age < strtotime( $cutoff . ' UTC' ) - $margin ) {
				$this->assertSame( '', $after[ $id ]['ip_address'], "IP of old ledger row $id ({$row['created_at']}) must be cleared" );
				++$cleared;
			} elseif ( $age > strtotime( $cutoff . ' UTC' ) + $margin ) {
				$this->assertSame( $row['ip_address'], $after[ $id ]['ip_address'], "IP of recent ledger row $id ({$row['created_at']}) must stay" );
			}
		}
		$this->assertGreaterThan( 100, $cleared );
	}

	public function test_shorter_retention_period(): void {
		$this->set_months( 4 );
		$this->run_cleanup();
		$this->assertSame(
			array(
				'old.pending@example.com'    => false,
				'recent.pending@example.com' => true, // 2 months.
				'old.unsub@example.com'      => false,
				'recent.unsub@example.com'   => true, // 3 months.
				'legacy.unsub@example.com'   => false,
				'loyal.fan@example.com'      => true,
				'Guest.Gina@Example.NET'     => false, // Unsubscribed 5 months ago (signed up in 2019).
				'Jane.Doe@Example.com'       => true,
				'marco@example.org'          => true, // Pending since 3 weeks.
			),
			$this->present( self::EMAILS )
		);
		// Janet's purchase from last month keeps its IP, her 2020 welcome bonus doesn't.
		$janet = get_user_by( 'login', 'janet' )->ID;
		$ips   = array_column( ledger_rows( 'user_id = ' . $janet ), 'ip_address', 'created_at' );
		ksort( $ips );
		$this->assertSame( array( '', '192.0.2.11' ), array_values( $ips ) );
	}

	public function test_zero_keeps_everything(): void {
		$this->set_months( 0 );
		$before = array( ledger_rows(), $this->present( self::EMAILS ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . subs() ) );
		$this->run_cleanup();
		$this->assertEquals( $before, array( ledger_rows(), $this->present( self::EMAILS ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . subs() ) ) );
	}
}
