<?php
/**
 * Public PHP API, hooks, REST and query performance (in-process, rolled back).
 */

use function WPSB\ActivityLog\autoload_bytes;
use function WPSB\ActivityLog\expected_by_id;
use function WPSB\ActivityLog\expected_entries;
use function WPSB\ActivityLog\expected_query;
use function WPSB\ActivityLog\ids;
use function WPSB\ActivityLog\user_id;

class ApiTest extends WPSB\TestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		WPSB\ActivityLog\delete_probes();
	}

	/** @return array<string, array{0: array}> */
	public static function queries(): array {
		$mid = expected_entries()[8000]['time'];
		return array(
			'first page'                => array( array() ),
			'page 7 of 25'              => array( array( 'per_page' => 25, 'page' => 7 ) ),
			'past the end'              => array( array( 'per_page' => 50, 'page' => 9999 ) ),
			'action'                    => array( array( 'action' => 'post_trashed', 'per_page' => 30, 'page' => 3 ) ),
			'several actions'           => array( array( 'action' => array( 'plugin_activated', 'plugin_deactivated' ), 'per_page' => -1 ) ),
			'mapped 1.x action'         => array( array( 'action' => 'user_registered', 'order' => 'ASC', 'per_page' => 40 ) ),
			'user'                      => array( array( 'user_id' => 7, 'per_page' => 15, 'page' => 4 ) ),
			'deleted user'              => array( array( 'user_id' => 999, 'per_page' => 10 ) ),
			'object'                    => array( array( 'object_type' => 'backup', 'per_page' => 12, 'page' => 2 ) ),
			'object id'                 => array( array( 'object_type' => 'form', 'object_id' => 3, 'per_page' => -1 ) ),
			'since/until'               => array( array( 'since' => $mid - 86400 * 3, 'until' => $mid, 'per_page' => -1 ) ),
			'search'                    => array( array( 'search' => 'invoice', 'per_page' => 20, 'page' => 2 ) ),
			'search is case-insensitive' => array( array( 'search' => 'SPRING SALE', 'per_page' => -1 ) ),
			'search literal %'          => array( array( 'search' => '100%', 'action' => array( 'user_registered', 'post_published' ), 'per_page' => -1 ) ),
			'search literal _'          => array( array( 'search' => 'r_s', 'per_page' => -1 ) ),
			'search quote'              => array( array( 'search' => "o'neil", 'per_page' => -1 ) ),
			'search unicode'            => array( array( 'search' => 'Straße', 'user_id' => 1, 'per_page' => -1 ) ),
			'oldest first'              => array( array( 'order' => 'ASC', 'per_page' => 25, 'page' => 2 ) ),
			'combined'                  => array( array( 'action' => 'user_login', 'user_id' => 2, 'since' => $mid, 'order' => 'ASC', 'per_page' => 10, 'page' => 3 ) ),
		);
	}

	#[PHPUnit\Framework\Attributes\DataProvider( 'queries' )]
	public function test_queries_return_the_same_results( array $args ): void {
		$expected = expected_query( $args );
		$this->assertNotSame( 0, $expected['total'], 'fixture sanity' );
		$actual = acme_activity_get_entries( $args );
		$this->assertSame( ids( $expected['entries'] ), ids( $actual ) );
		foreach ( $actual as $entry ) {
			$this->assertEquals( expected_by_id()[ $entry['id'] ], $entry );
		}
		$this->assertSame( $expected['total'], acme_activity_count_entries( $args ) );
	}

	public function test_log_function_and_hooks(): void {
		$this->login_as( user_id( 'priya' ) );
		$seen = array();
		add_action(
			'acme_activity_logged',
			$logged = static function ( $entry ) use ( &$seen ) {
				$seen[] = $entry;
			}
		);
		add_filter(
			'acme_activity_entry_data',
			$veto = static function ( $entry ) {
				return 'wpsb_vetoed' === $entry['action'] ? false : $entry;
			}
		);

		$id = acme_activity_log(
			'wpsb_custom',
			array(
				'object_type' => 'invoice',
				'object_id'   => 77,
				'message'     => '<em>Paid</em> invoice 77',
				'context'     => array( 'amount' => 19.5, 'lines' => array( 'a', 'b' ) ),
			)
		);
		$this->assertIsInt( $id );
		$this->assertGreaterThan( max( array_keys( expected_by_id() ) ), $id );
		$this->assertFalse( acme_activity_log( 'wpsb_vetoed' ) );
		$this->assertFalse( acme_activity_log( 'plugin_deactivated', array( 'message' => 'not tracked on this site' ) ) );
		$this->assertFalse( acme_activity_log( '' ) );

		remove_action( 'acme_activity_logged', $logged );
		remove_filter( 'acme_activity_entry_data', $veto );

		$this->assertCount( 1, $seen );
		$this->assertSame( $id, $seen[0]['id'] );

		$entry = acme_activity_get_entry( $id );
		$this->assertSame( 'wpsb_custom', $entry['action'] );
		$this->assertSame( user_id( 'priya' ), $entry['user_id'] );
		$this->assertSame( 'invoice', $entry['object_type'] );
		$this->assertSame( 77, $entry['object_id'] );
		$this->assertSame( 'Paid invoice 77', $entry['message'] );
		$this->assertEquals( array( 'amount' => 19.5, 'lines' => array( 'a', 'b' ) ), $entry['context'] );
		$this->assertEqualsWithDelta( time(), $entry['time'], 60 );

		$newest = acme_activity_get_entries( array( 'per_page' => 1 ) );
		$this->assertSame( $id, $newest[0]['id'] ?? null, 'New entries come first' );

		// Explicit time: sorted into the right place.
		$entries = expected_entries();
		$i       = 100;
		while ( $entries[ $i - 1 ]['time'] === $entries[ $i ]['time'] ) {
			++$i;
		}
		$old  = acme_activity_log( 'wpsb_custom', array( 'time' => $entries[ $i ]['time'], 'message' => 'backdated' ) );
		$page = acme_activity_get_entries( array( 'until' => $entries[ $i ]['time'], 'per_page' => 3 ) );
		$this->assertSame( array( $old, $entries[ $i ]['id'], $entries[ $i + 1 ]['id'] ), ids( $page ), 'Ties on time are ordered by ID, newest first' );

		$this->assertSame( 2, acme_activity_delete_entries( array( $id, $old, 99999999 ) ) );
		$this->assertNull( acme_activity_get_entry( $id ) );
	}

	public function test_tracked_events_are_logged(): void {
		$this->login_as( user_id( 'olivia' ) );
		$post_id = $this->create_post( array( 'post_title' => 'Tracked post', 'post_status' => 'draft', 'post_author' => user_id( 'olivia' ) ) );
		wp_publish_post( $post_id );
		wp_trash_post( $post_id );

		$entries = acme_activity_get_entries( array( 'object_id' => $post_id, 'object_type' => 'post', 'per_page' => -1 ) );
		$this->assertSame( array( 'post_trashed', 'post_published' ), array_column( $entries, 'action' ) );
		$this->assertSame( user_id( 'olivia' ), $entries[1]['user_id'] );
		$this->assertStringContainsString( 'Tracked post', $entries[1]['message'] );
	}

	public function test_logging_does_not_touch_the_options_table(): void {
		$this->login_as( 1 );
		$before = autoload_bytes( 'acme_activity' );
		$q      = $this->count_queries(
			static function () {
				for ( $i = 0; $i < 25; $i++ ) {
					acme_activity_log( 'wpsb_bulk', array( 'message' => str_repeat( 'x', 500 ) . $i ) );
				}
				acme_activity_update_user_state( 1, array( 'last_seen' => time() ) );
			}
		);
		$writes = array_filter( $q['queries'], static fn( $sql ) => preg_match( '/^\s*(INSERT|UPDATE|REPLACE|DELETE)\b/i', $sql ) && false !== stripos( $sql, 'options' ) );
		$writes = array_map( static fn( $sql ) => substr( $sql, 0, 160 ), $writes );
		$this->assertSame( array(), array_values( $writes ), 'Logging and screen state must not write to the options table' );
		$this->assertLessThan( 2000, autoload_bytes( 'acme_activity' ) );
		$this->assertLessThanOrEqual( $before + 200, autoload_bytes( 'acme_activity' ) );
	}

	public function test_reading_a_page_is_cheap(): void {
		global $wpdb;
		acme_activity_get_entries( array( 'per_page' => 1 ) ); // warm-up.
		$q = $this->count_queries(
			static function () {
				return array(
					acme_activity_get_entries( array( 'per_page' => 25, 'page' => 40, 'action' => array( 'user_login', 'post_published' ) ) ),
					acme_activity_count_entries( array( 'action' => array( 'user_login', 'post_published' ) ) ),
					acme_activity_get_entry( 5003 ),
				);
			}
		);
		$this->assertCount( 25, $q['result'][0] );
		$this->assertLessThanOrEqual( 5, $q['count'], "Too many queries:\n" . implode( "\n", $q['queries'] ) );
		foreach ( $q['queries'] as $sql ) {
			$this->assertStringNotContainsString( $wpdb->options, $sql, 'Entries must not be read from options any more' );
		}
		$paged = array_filter( $q['queries'], static fn( $sql ) => false !== stripos( $sql, 'acme_activity_log' ) && preg_match( '/\bLIMIT\b/i', $sql ) );
		$this->assertNotEmpty( $paged, 'A page of entries must be fetched with a LIMIT, not by loading the whole log' );
	}

	public function test_user_state_api(): void {
		$uid = $this->create_user( 'administrator' );
		$this->assertSame( array( 'per_page' => 20, 'hidden_columns' => array(), 'last_seen' => 0 ), acme_activity_get_user_state( $uid ) );
		$state = acme_activity_update_user_state( $uid, array( 'per_page' => 50, 'hidden_columns' => array( 'ip', 'nope' ) ) );
		$this->assertSame( array( 'per_page' => 50, 'hidden_columns' => array( 'ip' ), 'last_seen' => 0 ), $state );
		acme_activity_update_user_state( $uid, array( 'per_page' => 33 ) );
		$this->assertSame( 20, acme_activity_get_user_state( $uid )['per_page'], 'Invalid values fall back to the default' );
		$this->login_as( $uid );
		$this->assertSame( array( 'ip' ), acme_activity_get_user_state()['hidden_columns'] );
	}

	public function test_rest_endpoint(): void {
		$this->login_as( 1 );
		$res = $this->rest( 'GET', '/acme-activity/v1/entries', array( 'action' => 'backup_completed', 'per_page' => 7, 'page' => 3 ) );
		$this->assertSame( 200, $res->get_status() );
		$exp = expected_query( array( 'action' => 'backup_completed', 'per_page' => 7, 'page' => 3 ) );
		$this->assertSame( ids( $exp['entries'] ), array_column( $res->get_data(), 'id' ) );
		$this->assertSame( (string) $exp['total'], (string) $res->get_headers()['X-WP-Total'] );
		$first = $res->get_data()[0];
		$this->assertSame( gmdate( 'Y-m-d\TH:i:s', $exp['entries'][0]['time'] ), $first['date_gmt'] );

		$one = $this->rest( 'GET', '/acme-activity/v1/entries/3' );
		$this->assertSame( 200, $one->get_status() );
		$this->assertSame( expected_by_id()[3]['message'], $one->get_data()['message'] );
		$this->assertSame( 404, $this->rest( 'GET', '/acme-activity/v1/entries/99999999' )->get_status() );

		$this->login_as( user_id( 'olivia' ) );
		$this->assertSame( 403, $this->rest( 'GET', '/acme-activity/v1/entries' )->get_status() );
	}

	public function test_daily_cleanup_applies_the_retention_setting(): void {
		$settings = get_option( 'acme_activity_settings' );
		$this->assertIsArray( $settings );

		$crons = _get_cron_array();
		$daily = array();
		foreach ( $crons as $events ) {
			foreach ( $events as $hook => $instances ) {
				foreach ( $instances as $event ) {
					if ( 'daily' === ( $event['schedule'] ?? false ) && ! in_array( $hook, array( 'wp_scheduled_delete', 'delete_expired_transients', 'wp_scheduled_auto_draft_delete', 'recovery_mode_clean_expired_keys', 'wp_delete_temp_updater_backups' ), true ) ) {
						$daily[ $hook ] = $event['args'];
					}
				}
			}
		}
		$this->assertNotEmpty( $daily, 'The cleanup must be scheduled to run once a day' );

		$entries = expected_entries();
		$cutoff  = $entries[6000]['time'];
		$days    = (int) ceil( ( time() - $cutoff ) / DAY_IN_SECONDS );
		$keep    = count( array_filter( $entries, static fn( $e ) => $e['time'] >= time() - $days * DAY_IN_SECONDS ) );

		// Disabled (0): nothing happens.
		update_option( 'acme_activity_settings', array_merge( $settings, array( 'retention_days' => 0 ) ) );
		foreach ( $daily as $hook => $args ) {
			do_action_ref_array( $hook, $args );
		}
		$this->assertSame( count( $entries ), acme_activity_count_entries() );

		update_option( 'acme_activity_settings', array_merge( $settings, array( 'retention_days' => $days ) ) );
		foreach ( $daily as $hook => $args ) {
			do_action_ref_array( $hook, $args );
		}
		$this->assertSame( $keep, acme_activity_count_entries() );
		$this->assertSame( ids( array_slice( $entries, 0, 20 ) ), ids( acme_activity_get_entries() ) );
	}
}
