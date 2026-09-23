<?php
/**
 * The 3.0 storage migration on the pristine 2.3.1-era site: batching, interruption
 * and resume, data parity, autoload size, user state, legacy cleanup.
 *
 * The methods run in order and build on each other (committed data).
 */

use PHPUnit\Framework\Attributes\Depends;
use function WPSB\ActivityLog\autoload_bytes;
use function WPSB\ActivityLog\autoloaded_options;
use function WPSB\ActivityLog\expected_by_id;
use function WPSB\ActivityLog\expected_entries;
use function WPSB\ActivityLog\expected_user_state;
use function WPSB\ActivityLog\ids;
use function WPSB\ActivityLog\install_kill_switch;
use function WPSB\ActivityLog\kill_marker;
use function WPSB\ActivityLog\plugin_option_names;
use function WPSB\ActivityLog\remove_kill_switch;
use function WPSB\ActivityLog\table;
use function WPSB\ActivityLog\table_count;
use function WPSB\ActivityLog\table_exists;
use function WPSB\ActivityLog\wp_cli_env;
use const WPSB\ActivityLog\PROBE_ACTION;

class MigrationTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var int[] IDs of entries logged while the migration was running. */
	private static array $probe_ids = array();

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		install_kill_switch();
	}

	public static function tearDownAfterClass(): void {
		remove_kill_switch();
		parent::tearDownAfterClass();
	}

	private function migrate( array $env = array() ): array {
		@unlink( WPSB\ActivityLog\KILL_MARKER );
		return wp_cli_env( 'acme-activity migrate', $env );
	}

	public function test_a_normal_request_does_not_move_everything_at_once(): void {
		// The PHPUnit bootstrap was the first request after the update; this is the second one.
		$res = wp_cli_env( 'eval "echo \'ok\';"' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] . $res['stdout'] );
		$this->assertStringContainsString( 'ok', $res['stdout'] );

		$this->assertLessThanOrEqual(
			2000,
			table_count(),
			'Two ordinary requests after the update must not have moved (almost) the whole log: the migration has to work in batches'
		);
	}

	public function test_b_interrupted_migration_resumes_without_losing_or_duplicating(): void {
		global $wpdb;
		$total = count( expected_entries() );

		// 1st run: killed right after ~4,300 rows were written (before it can record its progress).
		$run1 = $this->migrate( array( 'WPSB_KILL_AFTER_ROWS' => '4321' ) );
		$mark = kill_marker();
		$this->assertNotNull( $mark, 'wp acme-activity migrate never wrote to the ' . table() . " table.\n" . $run1['stdout'] . $run1['stderr'] );
		$this->assertTrue( ! empty( $mark['killed'] ), 'The first migration run was expected to be interrupted mid-way: ' . wp_json_encode( $mark ) . "\n" . $run1['stdout'] . $run1['stderr'] );
		$this->assertNotSame( 0, $run1['exit'] );

		wp_cache_flush();
		$after_first = table_count();
		$this->assertGreaterThan( 0, $after_first );
		$this->assertLessThan( $total, $after_first, 'The interrupted run cannot have moved everything' );

		// The site keeps logging while the migration is running.
		$probe = wp_cli_env( 'eval "echo \'PROBE=\' . acme_activity_log( \'' . PROBE_ACTION . '\', array( \'user_id\' => 3, \'message\' => \'Logged while migrating\', \'context\' => array( \'phase\' => 1 ) ) );"' );
		$this->assertSame( 0, $probe['exit'], $probe['stderr'] . $probe['stdout'] );
		$this->assertMatchesRegularExpression( '/PROBE=(\d+)/', $probe['stdout'] );
		preg_match( '/PROBE=(\d+)/', $probe['stdout'], $m );
		self::$probe_ids[] = (int) $m[1];

		// 2nd run: killed before a write that would go beyond 2,500 more rows.
		$run2 = $this->migrate( array( 'WPSB_KILL_BEFORE_ROWS' => '2500' ) );
		$mark = kill_marker();
		$this->assertTrue( is_array( $mark ) && ! empty( $mark['killed'] ), 'The second migration run was expected to be interrupted too: ' . wp_json_encode( $mark ) . "\n" . $run2['stdout'] . $run2['stderr'] );

		$probe = wp_cli_env( 'eval "echo \'PROBE=\' . acme_activity_log( \'' . PROBE_ACTION . '\', array( \'user_id\' => 3, \'message\' => \'Logged while migrating\', \'context\' => array( \'phase\' => 2 ) ) );"' );
		$this->assertSame( 0, $probe['exit'], $probe['stderr'] . $probe['stdout'] );
		preg_match( '/PROBE=(\d+)/', $probe['stdout'], $m );
		$this->assertNotEmpty( $m );
		self::$probe_ids[] = (int) $m[1];

		// 3rd run finishes.
		$run3 = $this->migrate();
		$this->assertSame( 0, $run3['exit'], "wp acme-activity migrate failed:\n" . $run3['stdout'] . $run3['stderr'] );
		$this->assertNull( kill_marker(), 'Nothing may be killed when the kill switch is not armed' );

		wp_cache_flush();
		$ids = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . table() . ' ORDER BY id' ) );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );

		$legacy_ids = array_keys( expected_by_id() );
		sort( $legacy_ids );
		$missing = array_diff( $legacy_ids, $ids );
		$this->assertSame( array(), array_slice( array_values( $missing ), 0, 20 ), count( $missing ) . ' legacy entries are missing after the interrupted migration' );

		foreach ( self::$probe_ids as $probe_id ) {
			$this->assertNotContains( $probe_id, $legacy_ids, 'An entry logged during the migration got the ID of an existing entry' );
			$this->assertContains( $probe_id, $ids, 'An entry logged during the migration was lost' );
		}
		$this->assertCount( $total + count( self::$probe_ids ), $ids, 'Unexpected number of rows (duplicates?)' );
	}

	#[Depends( 'test_b_interrupted_migration_resumes_without_losing_or_duplicating' )]
	public function test_c_running_it_again_is_a_noop(): void {
		$before = table_count();
		// Any write to the log table kills the process: there must be nothing left to write.
		$run = $this->migrate( array( 'WPSB_KILL_BEFORE_ROWS' => '0' ) );
		$this->assertSame( 0, $run['exit'], "A second 'wp acme-activity migrate' must succeed without writing anything:\n" . $run['stdout'] . $run['stderr'] . wp_json_encode( kill_marker() ) );
		wp_cache_flush();
		$this->assertSame( $before, table_count() );
	}

	#[Depends( 'test_b_interrupted_migration_resumes_without_losing_or_duplicating' )]
	public function test_d_public_api_returns_the_same_entries(): void {
		$expected = expected_entries();
		$actual   = acme_activity_get_entries( array( 'per_page' => -1 ) );
		$actual   = array_values( array_filter( $actual, static fn( $e ) => PROBE_ACTION !== $e['action'] ) );

		$this->assertSame( count( $expected ), count( $actual ) );
		$this->assertSame( ids( $expected ), ids( $actual ), 'Order or IDs differ from 2.3.1' );

		$keys = array( 'id', 'time', 'user_id', 'action', 'object_type', 'object_id', 'message', 'ip', 'context' );
		foreach ( $actual as $i => $entry ) {
			$this->assertSame( $keys, array_keys( $entry ), 'Entry shape changed' );
			$exp = $expected[ $i ];
			$ctx = $entry['context'];
			unset( $entry['context'] );
			$exp_ctx = $exp['context'];
			unset( $exp['context'] );
			$this->assertSame( $exp, $entry, "Entry {$exp['id']} differs" );
			$this->assertEquals( $exp_ctx, $ctx, "Context of entry {$exp['id']} differs" );
		}

		$this->assertSame( count( $expected ) + count( self::$probe_ids ), acme_activity_count_entries() );

		// Single entries: a 1.x entry with a JSON context, a 2.x one with nested context, a duplicated one.
		foreach ( array( 3, 14, 5003, 14900, 17111 ) as $id ) {
			if ( ! isset( expected_by_id()[ $id ] ) ) {
				continue;
			}
			$this->assertEquals( expected_by_id()[ $id ], acme_activity_get_entry( $id ) );
		}
		$this->assertNull( acme_activity_get_entry( 999999 ) );

		$probe = acme_activity_get_entry( self::$probe_ids[0] );
		$this->assertSame( 'Logged while migrating', $probe['message'] ?? null );
		$this->assertSame( 3, $probe['user_id'] ?? null );
		$this->assertEquals( array( 'phase' => 1 ), $probe['context'] ?? null );
	}

	#[Depends( 'test_b_interrupted_migration_resumes_without_losing_or_duplicating' )]
	public function test_e_table_contract(): void {
		global $wpdb;
		$this->assertTrue( table_exists() );
		$columns = array_map( 'strtolower', $wpdb->get_col( 'SHOW COLUMNS FROM ' . table() ) );
		foreach ( array( 'id', 'logged_at', 'user_id', 'action', 'object_type', 'object_id', 'message', 'ip', 'context' ) as $col ) {
			$this->assertContains( $col, $columns );
		}

		foreach ( array( 3, 1000, 5003, 17400 ) as $id ) {
			$exp = expected_by_id()[ $id ] ?? null;
			if ( ! $exp ) {
				continue;
			}
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . table() . ' WHERE id = %d', $id ), ARRAY_A );
			$this->assertNotNull( $row, "Row $id missing" );
			$this->assertSame( gmdate( 'Y-m-d H:i:s', $exp['time'] ), $row['logged_at'], 'logged_at is the UTC date/time' );
			$this->assertSame( $exp['action'], $row['action'] );
			$this->assertSame( $exp['message'], $row['message'] );
			$this->assertSame( $exp['object_type'], $row['object_type'] );
			$this->assertEquals( $exp['user_id'], $row['user_id'] );
			$this->assertEquals( $exp['object_id'], $row['object_id'] );
			$this->assertSame( $exp['ip'], $row['ip'] );
			$this->assertEquals( $exp['context'], json_decode( (string) $row['context'], true ), 'context is stored as JSON' );
		}

		// Indexes for the screen's sort order and filters.
		$first_columns = array();
		foreach ( $wpdb->get_results( 'SHOW INDEX FROM ' . table(), ARRAY_A ) as $index ) {
			if ( 1 === (int) $index['Seq_in_index'] ) {
				$first_columns[] = strtolower( $index['Column_name'] );
			}
		}
		foreach ( array( 'logged_at', 'user_id', 'action' ) as $col ) {
			$this->assertContains( $col, $first_columns, "No index starting with $col" );
		}
	}

	#[Depends( 'test_b_interrupted_migration_resumes_without_losing_or_duplicating' )]
	public function test_f_autoloaded_options_are_small_again(): void {
		$all = autoloaded_options();
		$this->assertLessThan( 100000, strlen( serialize( $all ) ), 'wp_load_alloptions() is still huge' );
		$this->assertLessThan( 2000, autoload_bytes( 'acme_activity' ), 'The plugin must only autoload its small configuration' );
		$this->assertArrayHasKey( 'acme_activity_settings', $all, 'The settings (read on every request) stay autoloaded' );
	}

	#[Depends( 'test_b_interrupted_migration_resumes_without_losing_or_duplicating' )]
	public function test_g_legacy_options_are_removed(): void {
		$left = array_values(
			array_filter(
				plugin_option_names(),
				static fn( $name ) => 'acme_activity_log' === $name || preg_match( '/^acme_activity_log_archive_\d+$/', $name ) || preg_match( '/^acme_activity_ui_(\d+|state)$/', $name )
			)
		);
		$this->assertSame( array(), $left, 'Legacy log/screen-state options must be deleted once migrated' );
	}

	#[Depends( 'test_b_interrupted_migration_resumes_without_losing_or_duplicating' )]
	public function test_h_screen_state_moved_to_user_meta(): void {
		global $wpdb;
		$expected = expected_user_state();
		foreach ( array( 1, 2, 3, 4, 5, 6, 7 ) as $user_id ) {
			$this->assertEquals( $expected[ $user_id ], acme_activity_get_user_state( $user_id ), "Screen state of user $user_id" );
		}
		foreach ( array( 1, 2, 4, 6, 7 ) as $user_id ) {
			$meta = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $user_id, '%acme\_activity%' ) );
			$this->assertGreaterThan( 0, $meta, "User $user_id: screen state must be stored with the user" );
		}
		$this->assertSame( array(), array_values( preg_grep( '/^acme_activity_ui/', array_keys( autoloaded_options() ) ) ) );

		// Changing it keeps it out of the options table.
		$before = acme_activity_get_user_state( 4 );
		$new    = acme_activity_update_user_state( 4, array( 'per_page' => 100 ) );
		$this->assertSame( 100, $new['per_page'] );
		$this->assertSame( 100, acme_activity_get_user_state( 4 )['per_page'] );
		$this->assertSame( array(), array_values( preg_grep( '/^acme_activity_ui/', plugin_option_names() ) ) );
		acme_activity_update_user_state( 4, $before );
	}
}
