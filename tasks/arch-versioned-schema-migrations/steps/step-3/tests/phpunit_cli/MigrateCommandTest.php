<?php
/**
 * 1.7: wp acme-crm migrate status|run|rollback.
 */

use WPSB\CRM\CrmTestCase;
use function WPSB\CRM\columns;
use function WPSB\CRM\contact_by_email;
use function WPSB\CRM\contacts_table;
use function WPSB\CRM\db_version;
use function WPSB\CRM\expected_split;
use function WPSB\CRM\has_index_on;
use function WPSB\CRM\index_first_columns;
use function WPSB\CRM\install_test_migrations;
use function WPSB\CRM\log_entries;
use function WPSB\CRM\notes_table;
use function WPSB\CRM\raw_option;
use function WPSB\CRM\set_raw_option;
use function WPSB\CRM\test_log;

class MigrateCommandTest extends CrmTestCase {

	private function cmd( string $args ): array {
		$r = $this->wp_cli( 'acme-crm migrate ' . $args );
		wp_cache_flush();
		$r['lines'] = array_values( array_filter( array_map( 'trim', explode( "\n", $r['stdout'] ) ), 'strlen' ) );
		return $r;
	}

	private function status_json(): array {
		$r    = $this->cmd( 'status --format=json' );
		$json = json_decode( trim( $r['stdout'] ), true );
		$this->assertIsArray( $json, 'status --format=json must print a JSON array: ' . $r['stdout'] . $r['stderr'] );
		$out = array();
		foreach ( $json as $row ) {
			$out[ (int) $row['version'] ] = $row['status'];
		}
		return array( $r['exit'], $out, $json );
	}

	/** Everything a dry run must not touch. */
	private function fingerprint(): array {
		global $wpdb;
		$options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'acme\\_crm%' ORDER BY option_name", ARRAY_A );
		$rows    = $wpdb->get_results( 'SELECT * FROM ' . contacts_table() . ' ORDER BY id', ARRAY_A );
		return array(
			'contacts_columns' => columns( contacts_table() ),
			'contacts_indexes' => index_first_columns( contacts_table() ),
			'notes_indexes'    => index_first_columns( notes_table() ),
			'options'          => $options,
			'contacts'         => md5( serialize( $rows ) ),
		);
	}

	/** id => [first, last] */
	private function names(): array {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( 'SELECT id, first_name, last_name FROM ' . contacts_table() . ' ORDER BY id', ARRAY_A ) as $row ) {
			$out[ (int) $row['id'] ] = array( $row['first_name'], $row['last_name'] );
		}
		return $out;
	}

	public function test_wp_cli_does_not_migrate_and_status_reports_pending(): void {
		$r = $this->wp_cli( 'option get blogname' );
		$this->assertSame( 0, $r['exit'] );
		wp_cache_flush();
		$this->assertNull( db_version(), 'WP-CLI must not run migrations automatically' );
		$this->assertNotContains( 'stage', columns( contacts_table() ) );

		list( $exit, $status, $json ) = $this->status_json();
		$this->assertSame( 2, $exit, 'status exits with 2 while migrations are pending' );
		$this->assertSame( array( 1 => 'applied', 2 => 'pending', 3 => 'pending', 4 => 'pending', 5 => 'pending' ), $status );
		foreach ( $json as $row ) {
			$this->assertIsInt( $row['version'] );
			$this->assertIsString( $row['description'] );
			$this->assertNotSame( '', trim( $row['description'] ) );
		}

		$r = $this->cmd( 'status' );
		$this->assertSame( 2, $r['exit'] );
		$this->assertContains( 'Database version: 1 (latest: 5)', $r['lines'] );
		$this->assertNull( db_version() );
	}

	public function test_run_dry_run_changes_nothing(): void {
		$before = $this->fingerprint();
		$r      = $this->cmd( 'run --dry-run' );
		$this->assertSame( 0, $r['exit'], $r['stderr'] );
		$would = array_values( array_filter( $r['lines'], static fn( $l ) => 0 === strpos( $l, 'Would apply migration ' ) ) );
		$this->assertCount( 4, $would, $r['stdout'] );
		foreach ( array( 2, 3, 4, 5 ) as $i => $v ) {
			$this->assertStringStartsWith( "Would apply migration $v: ", $would[ $i ] );
		}
		$this->assertSame( 'Success: Dry run: 4 migration(s) pending.', end( $r['lines'] ) );
		$this->assertEquals( $before, $this->fingerprint(), 'A dry run must not change anything' );
	}

	public function test_run_applies_everything_including_the_whole_backfill(): void {
		global $wpdb;
		$originals = $wpdb->get_results( 'SELECT id, full_name FROM ' . contacts_table() . ' ORDER BY id', ARRAY_A );

		$r = $this->cmd( 'run' );
		$this->assertSame( 0, $r['exit'], $r['stdout'] . $r['stderr'] );
		$applied = array_values( array_filter( $r['lines'], static fn( $l ) => 0 === strpos( $l, 'Applied migration ' ) ) );
		$this->assertCount( 4, $applied, $r['stdout'] );
		foreach ( array( 2, 3, 4, 5 ) as $i => $v ) {
			$this->assertStringStartsWith( "Applied migration $v: ", $applied[ $i ] );
		}
		$this->assertSame( 'Success: Database is at version 5.', end( $r['lines'] ) );
		$this->assertSame( 5, db_version() );
		$this->assertNotContains( 'full_name', columns( contacts_table() ) );
		$this->assertNull( raw_option( 'acme_crm_migration_lock' ) );
		foreach ( array( 2, 3, 4, 5 ) as $v ) {
			$this->assertCount( 1, log_entries( $v, 'applied' ) );
		}
		$names = $this->names();
		foreach ( $originals as $row ) {
			$this->assertSame( expected_split( $row['full_name'] ), $names[ (int) $row['id'] ], "#{$row['id']} {$row['full_name']}" );
		}

		list( $exit, $status ) = $this->status_json();
		$this->assertSame( 0, $exit, 'status exits with 0 when up to date' );
		$this->assertSame( array( 'applied' ), array_values( array_unique( $status ) ) );

		$r = $this->cmd( 'run' );
		$this->assertSame( 0, $r['exit'] );
		$this->assertSame( 'Success: Database is already at version 5.', end( $r['lines'] ) );
	}

	public function test_rollback_pauses_web_migrations_and_round_trips(): void {
		$this->assertSame( 0, $this->cmd( 'run' )['exit'] );
		$names = $this->names();

		$before = $this->fingerprint();
		$r      = $this->cmd( 'rollback --dry-run' );
		$this->assertSame( 0, $r['exit'], $r['stderr'] );
		$this->assertStringStartsWith( 'Success: Dry run: would roll back migration 5 (', end( $r['lines'] ) );
		$this->assertEquals( $before, $this->fingerprint(), 'A dry run must not change anything' );

		$r = $this->cmd( 'rollback' );
		$this->assertSame( 0, $r['exit'], $r['stdout'] . $r['stderr'] );
		$this->assertSame( 'Success: Rolled back migration 5; database is at version 4.', end( $r['lines'] ) );
		$this->assertSame( 4, db_version() );
		$this->assertContains( 'full_name', columns( contacts_table() ) );
		$this->assertCount( 1, log_entries( 5, 'rolled_back' ) );
		global $wpdb;
		$full = $wpdb->get_col( 'SELECT full_name FROM ' . contacts_table() . ' ORDER BY id' );
		$this->assertSame( 'Ludwig van Beethoven', $full[0] );
		$this->assertSame( 'John Smith', $full[9], 'full_name is rebuilt from first and last name' );

		// Web requests must not re-apply it.
		$this->visit( '/' );
		$this->visit( '/wp-json/' );
		$this->assertSame( 4, db_version(), 'After a rollback, web requests must not re-apply migrations' );
		$r = $this->cmd( 'status' );
		$this->assertSame( 2, $r['exit'] );
		$this->assertContains( 'Automatic migrations are paused.', $r['lines'] );
		$this->assertContains( 'Database version: 4 (latest: 5)', $r['lines'] );

		// The CRM keeps working at version 4.
		$g = $this->rest_as( 'sally', 'GET', '/contacts/1' );
		$this->assertSame( 200, $g['status'] );
		$this->assertSame( 'Ludwig van Beethoven', $g['json']['name'] );
		$p = $this->rest_as( 'sally', 'POST', '/contacts', array( 'first_name' => 'Rolled', 'last_name' => 'Back', 'email' => 'rolled@example.test' ) );
		$this->assertSame( 201, $p['status'], $p['body'] );

		// Two more steps back: the names live only in full_name again.
		$r = $this->cmd( 'rollback' );
		$this->assertSame( 'Success: Rolled back migration 4; database is at version 3.', end( $r['lines'] ), $r['stderr'] );
		$r = $this->cmd( 'rollback' );
		$this->assertSame( 'Success: Rolled back migration 3; database is at version 2.', end( $r['lines'] ), $r['stderr'] );
		$cols = columns( contacts_table() );
		$this->assertNotContains( 'first_name', $cols );
		$this->assertNotContains( 'last_name', $cols );
		$this->assertContains( 'full_name', $cols );
		$this->assertSame( 'Rolled Back', contact_by_email( 'rolled@example.test' )['full_name'] );
		$this->visit( '/' );
		$this->assertSame( 2, db_version() );

		// Forward again.
		$r = $this->cmd( 'run' );
		$this->assertSame( 0, $r['exit'], $r['stdout'] . $r['stderr'] );
		$this->assertSame( 'Success: Database is at version 5.', end( $r['lines'] ) );
		$again = $this->names();
		foreach ( $names as $id => $pair ) {
			$this->assertSame( $pair, $again[ $id ], "contact #$id after rollback + run" );
		}
		$rolled = contact_by_email( 'rolled@example.test' );
		$this->assertSame( array( 'Rolled', 'Back' ), array( $rolled['first_name'], $rolled['last_name'] ) );

		// Web migrations are live again.
		install_test_migrations( array( 90 => array( 'mode' => 'ok' ) ) );
		$this->visit( '/' );
		$this->assertSame( 90, db_version(), '`migrate run` must resume automatic migrations' );
	}

	public function test_rollback_of_migration_2_and_irreversible_migration_1(): void {
		global $wpdb;
		$this->assertSame( 0, $this->cmd( 'run' )['exit'] );
		$wpdb->update( contacts_table(), array( 'stage' => 'prospect' ), array( 'email' => 'named02@example.test' ) );
		$wpdb->update( contacts_table(), array( 'stage' => 'churned' ), array( 'email' => 'named04@example.test' ) );

		foreach ( array( 5, 4, 3, 2 ) as $v ) {
			$r = $this->cmd( 'rollback' );
			$this->assertSame( 0, $r['exit'], $r['stdout'] . $r['stderr'] );
			$prev = $v - 1;
			$this->assertSame( "Success: Rolled back migration $v; database is at version $prev.", end( $r['lines'] ) );
		}
		$this->assertSame( 1, db_version() );
		$cols = columns( contacts_table() );
		$this->assertNotContains( 'stage', $cols );
		$this->assertNotContains( 'updated_at', $cols );
		$this->assertFalse( has_index_on( contacts_table(), 'email' ) );
		$this->assertFalse( has_index_on( contacts_table(), 'stage' ) );
		$this->assertFalse( has_index_on( notes_table(), 'contact_id' ) );
		$this->assertSame( 'customer', contact_by_email( 'named01@example.test' )['status'] );
		$this->assertSame( 'lead', contact_by_email( 'named02@example.test' )['status'], 'prospect → lead' );
		$this->assertSame( 'inactive', contact_by_email( 'named04@example.test' )['status'], 'churned → inactive' );

		$r = $this->cmd( 'rollback' );
		$this->assertSame( 1, $r['exit'] );
		$this->assertStringContainsString( 'Error: Migration 1 cannot be rolled back.', $r['stderr'] );
		$this->assertSame( 1, db_version() );

		$r = $this->cmd( 'run' );
		$this->assertSame( 0, $r['exit'], $r['stdout'] . $r['stderr'] );
		$this->assertSame( 5, db_version() );
		$this->assertSame( 'churned', contact_by_email( 'named04@example.test' )['stage'] );
	}

	public function test_failures_locks_and_add_on_rollbacks(): void {
		$this->assertSame( 0, $this->cmd( 'run' )['exit'] );
		install_test_migrations(
			array(
				90 => array(
					'mode'    => 'throw',
					'message' => 'kaboom',
				),
				91 => array( 'mode' => 'ok' ),
			)
		);
		$r = $this->cmd( 'run' );
		$this->assertSame( 1, $r['exit'] );
		$this->assertStringContainsString( 'Error: Migration 90 failed: kaboom', $r['stderr'] );
		$this->assertSame( 5, db_version() );
		$this->assertCount( 1, log_entries( 90, 'failed' ) );
		list( $exit, $status ) = $this->status_json();
		$this->assertSame( 2, $exit );
		$this->assertSame( 'failed', $status[90] );
		$this->assertSame( 'pending', $status[91] );

		// Fixed: an explicit run retries.
		set_raw_option(
			'wpsb_crm_test_migrations',
			array(
				90 => array( 'mode' => 'ok' ),
				91 => array( 'mode' => 'ok' ),
				92 => array( 'mode' => 'ok' ),
			)
		);
		$r = $this->cmd( 'run' );
		$this->assertSame( 0, $r['exit'], $r['stderr'] );
		$this->assertSame( array( 'Applied migration 90: Test migration 90', 'Applied migration 91: Test migration 91', 'Applied migration 92: Test migration 92' ), array_values( array_filter( $r['lines'], static fn( $l ) => 0 === strpos( $l, 'Applied' ) ) ) );
		$this->assertSame( 92, db_version() );

		// Add-on migration without a down step.
		$before = $this->fingerprint();
		$r      = $this->cmd( 'rollback' );
		$this->assertSame( 1, $r['exit'] );
		$this->assertStringContainsString( 'Error: Migration 92 cannot be rolled back.', $r['stderr'] );
		$this->assertEquals( $before, $this->fingerprint() );

		// Add-on migration with a down step.
		set_raw_option(
			'wpsb_crm_test_migrations',
			array(
				90 => array( 'mode' => 'ok' ),
				91 => array( 'mode' => 'ok' ),
				92 => array( 'mode' => 'ok' ),
				93 => array(
					'mode' => 'ok',
					'down' => true,
				),
			)
		);
		$this->assertSame( 0, $this->cmd( 'run' )['exit'] );
		$r = $this->cmd( 'rollback' );
		$this->assertSame( 0, $r['exit'], $r['stderr'] );
		$this->assertSame( 'Success: Rolled back migration 93; database is at version 92.', end( $r['lines'] ) );
		$this->assertSame( array( 93 ), test_log( 'down' ) );

		// Locks.
		$lock = time() - 60;
		set_raw_option( 'acme_crm_migration_lock', $lock );
		$r = $this->cmd( 'run' );
		$this->assertSame( 1, $r['exit'], 'run must not proceed while another run holds the lock' );
		$this->assertSame( 92, db_version() );
		$r = $this->cmd( 'rollback' );
		$this->assertSame( 1, $r['exit'], 'rollback must not proceed while another run holds the lock' );
		$this->assertSame( 92, db_version() );
		$this->assertSame( $lock, (int) raw_option( 'acme_crm_migration_lock' ) );

		set_raw_option( 'acme_crm_migration_lock', time() - 3600 );
		$r = $this->cmd( 'run' );
		$this->assertSame( 0, $r['exit'], 'a stale lock is taken over: ' . $r['stderr'] );
		$this->assertSame( 93, db_version() );
		$this->assertNull( raw_option( 'acme_crm_migration_lock' ) );
	}
}
