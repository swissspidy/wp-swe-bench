<?php
/**
 * Pending migrations run on the first web request after the deploy; migration 2 upgrades
 * 1.2-era and fresh-1.4 schemas; fresh activations install everything.
 */

use WPSB\CRM\CrmTestCase;
use function WPSB\CRM\columns;
use function WPSB\CRM\contact_by_email;
use function WPSB\CRM\contacts_table;
use function WPSB\CRM\db_version;
use function WPSB\CRM\debug_log_offset;
use function WPSB\CRM\delete_raw_option;
use function WPSB\CRM\has_index_on;
use function WPSB\CRM\latest;
use function WPSB\CRM\log_entries;
use function WPSB\CRM\migration_log;
use function WPSB\CRM\notes_table;
use function WPSB\CRM\php_problems_since;
use function WPSB\CRM\raw_option;
use function WPSB\CRM\table_exists;

class MigrationOnDeployTest extends CrmTestCase {

	public function test_pristine_database_is_the_old_schema(): void {
		// Sanity of the starting state (1.2-era tables, no version).
		$this->assertNull( db_version() );
		$this->assertNotContains( 'stage', columns( contacts_table() ) );
		$this->assertNotContains( 'updated_at', columns( contacts_table() ) );
		global $wpdb;
		$this->assertSame( 1200, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . contacts_table() ) );
	}

	public function test_first_web_request_after_deploy_applies_migration_2(): void {
		global $wpdb;
		$r = $this->visit( '/' );
		$this->assertSame( 200, $r['status'] );
		$this->assertGreaterThanOrEqual( 2, (int) db_version(), 'The first web request after the deploy must run the pending migrations' );

		$cols = columns( contacts_table() );
		$this->assertContains( 'stage', $cols );
		$this->assertContains( 'updated_at', $cols );
		$this->assertTrue( has_index_on( contacts_table(), 'email' ), 'index on contacts.email' );
		$this->assertTrue( has_index_on( contacts_table(), 'stage' ), 'index on contacts.stage' );
		$this->assertTrue( has_index_on( notes_table(), 'contact_id' ), 'index on notes.contact_id' );
		$this->assertNull( raw_option( 'acme_crm_migration_lock' ), 'The lock must be released after the run' );

		$applied = log_entries( 2, 'applied' );
		$this->assertCount( 1, $applied, 'Migration 2 must be logged once as applied: ' . wp_json_encode( migration_log() ) );
		$this->assertIsInt( $applied[0]['time'] );
		$this->assertGreaterThan( time() - 600, $applied[0]['time'] );

		$this->migrate_fully();
		$this->assertCount( 1, log_entries( 2, 'applied' ), 'Each migration runs once' );
		$this->assertSame( array(), array_values( array_filter( migration_log(), static fn( $e ) => 'failed' === ( $e['status'] ?? '' ) ) ) );

		// Existing data: stage from the legacy status, updated_at from created_at.
		$this->assertSame( 1200, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . contacts_table() ) );
		$this->assertSame( 'customer', contact_by_email( 'named01@example.test' )['stage'] );
		$this->assertSame( 'lead', contact_by_email( 'named02@example.test' )['stage'] );
		$this->assertSame( 'churned', contact_by_email( 'named03@example.test' )['stage'] );
		$this->assertSame( 'lead', contact_by_email( 'named15@example.test' )['stage'], 'unknown legacy status "vip" maps to lead' );
		$mapped = $wpdb->get_results( 'SELECT status, stage, COUNT(*) AS n FROM ' . contacts_table() . ' GROUP BY status, stage ORDER BY status, stage', ARRAY_A );
		$pairs  = array();
		foreach ( $mapped as $row ) {
			$pairs[ $row['status'] ][ $row['stage'] ] = (int) $row['n'];
		}
		$this->assertSame( array( 'churned' ), array_keys( $pairs['inactive'] ) );
		$this->assertSame( array( 'customer' ), array_keys( $pairs['customer'] ) );
		$this->assertSame( array( 'lead' ), array_keys( $pairs['lead'] ) );
		$this->assertSame( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . contacts_table() . ' WHERE updated_at <> created_at' ), 'updated_at of existing contacts = created_at' );
	}

	public function test_saving_contacts_works_after_the_migration(): void {
		$this->migrate_fully();

		// Sales app (REST).
		$r = $this->rest_as(
			'sally',
			'POST',
			'/contacts',
			array(
				'name'    => 'Ada Lovelace',
				'email'   => 'ada@example.test',
				'company' => 'Analytical Engines',
			)
		);
		$this->assertSame( 201, $r['status'], $r['body'] );
		$this->assertSame( 'lead', $r['json']['stage'] );
		$this->assertSame( 'Ada Lovelace', $r['json']['name'] );
		$this->assertNotNull( contact_by_email( 'ada@example.test' ) );

		$r = $this->rest_as( 'sally', 'PATCH', '/contacts/' . $r['json']['id'], array( 'stage' => 'prospect' ) );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( 'prospect', $r['json']['stage'] );

		// Stage filter.
		global $wpdb;
		$customers = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . contacts_table() . ' WHERE stage = %s', 'customer' ) );
		$this->assertGreaterThan( 200, $customers );
		$r = $this->rest_as( 'sally', 'GET', '/contacts?stage=customer&per_page=5' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( (string) $customers, $r['headers']['x-wp-total'] ?? null );
		$this->assertSame( array( 'customer' ), array_values( array_unique( array_column( $r['json'], 'stage' ) ) ) );

		// Website form.
		$r = $this->submit_contact_form(
			array(
				'name'    => 'Grace Hopper',
				'email'   => 'grace@example.test',
				'message' => 'Please call me about COBOL.',
			)
		);
		$this->assertSame( 302, $r['status'] );
		$this->assertStringContainsString( 'acme_crm_sent=1', $r['headers']['location'] ?? '', 'The form submission must succeed' );
		$c = contact_by_email( 'grace@example.test' );
		$this->assertNotNull( $c );
		$this->assertSame( 'website', $c['source'] );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . notes_table() . ' WHERE contact_id = %d', $c['id'] ) ) );

		// Stats.
		$stats = $this->wp_cli( 'acme-crm stats --format=json' );
		$this->assertSame( 0, $stats['exit'], $stats['stderr'] );
		$this->assertSame( 1202, array_sum( array_column( json_decode( $stats['stdout'], true ), 'contacts' ) ) );
	}

	public function test_fresh_14_install_without_version_is_migrated_without_data_changes(): void {
		global $wpdb;
		$contacts = contacts_table();
		$notes    = notes_table();
		$wpdb->query( "DROP TABLE $contacts" );
		$wpdb->query( "DROP TABLE $notes" );
		// The 1.4.0 activation DDL.
		$wpdb->query(
			"CREATE TABLE $contacts (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				full_name varchar(191) NOT NULL DEFAULT '',
				email varchar(191) NOT NULL DEFAULT '',
				phone varchar(50) NOT NULL DEFAULT '',
				company varchar(191) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'lead',
				stage varchar(20) NOT NULL DEFAULT 'lead',
				owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
				source varchar(50) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY email (email),
				KEY stage (stage)
			)"
		);
		$wpdb->query(
			"CREATE TABLE $notes (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				author_id bigint(20) unsigned NOT NULL DEFAULT 0,
				body text NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY (id),
				KEY contact_id (contact_id)
			)"
		);
		$rows = array(
			array( 'Fresh Prospect', 'fresh1@example.test', 'lead', 'prospect', '2025-05-01 10:00:00', '2025-06-01 10:00:00' ),
			array( 'Fresh Customer', 'fresh2@example.test', 'lead', 'customer', '2025-05-02 10:00:00', '2025-06-02 10:00:00' ),
			array( 'Fresh Lead', 'fresh3@example.test', 'customer', 'lead', '2025-05-03 10:00:00', '2025-06-03 10:00:00' ),
		);
		foreach ( $rows as $row ) {
			$wpdb->insert(
				$contacts,
				array(
					'full_name'  => $row[0],
					'email'      => $row[1],
					'status'     => $row[2],
					'stage'      => $row[3],
					'created_at' => $row[4],
					'updated_at' => $row[5],
				)
			);
		}
		$wpdb->insert(
			$notes,
			array(
				'contact_id' => 1,
				'body'       => 'hello',
				'created_at' => '2025-06-01 10:00:00',
			)
		);
		delete_raw_option( 'acme_crm_db_version' );

		$offset = debug_log_offset();
		$this->migrate_fully();
		$this->assertSame( array(), php_problems_since( $offset ), 'Migrating a fresh 1.4 install must not cause errors' );
		$this->assertCount( 1, log_entries( 2, 'applied' ) );

		$this->assertSame( 'prospect', contact_by_email( 'fresh1@example.test' )['stage'], 'Existing stages must not be recomputed' );
		$this->assertSame( 'customer', contact_by_email( 'fresh2@example.test' )['stage'] );
		$this->assertSame( 'lead', contact_by_email( 'fresh3@example.test' )['stage'] );
		$this->assertSame( '2025-06-01 10:00:00', contact_by_email( 'fresh1@example.test' )['updated_at'], 'Existing updated_at values must be kept' );
		$this->assertSame( 3, (int) $wpdb->get_var( "SELECT COUNT(*) FROM $contacts" ) );
	}

	public function test_activation_on_a_site_without_tables_runs_every_migration(): void {
		global $wpdb;
		$this->assertSame( 0, $this->wp_cli( 'plugin deactivate acme-crm' )['exit'] );
		wp_cache_flush();
		$wpdb->query( 'DROP TABLE ' . contacts_table() );
		$wpdb->query( 'DROP TABLE ' . notes_table() );
		foreach ( array( 'acme_crm_db_version', 'acme_crm_version', 'acme_crm_migration_log', 'acme_crm_migration_lock' ) as $o ) {
			delete_raw_option( $o );
		}

		$r = $this->wp_cli( 'plugin activate acme-crm' );
		$this->assertSame( 0, $r['exit'], $r['stdout'] . $r['stderr'] );
		wp_cache_flush();
		$this->assertTrue( table_exists( contacts_table() ) && table_exists( notes_table() ), 'Activation must create the tables' );
		$this->assertSame( latest(), db_version(), 'A fresh install is at the latest version' );
		$this->assertContains( 'stage', columns( contacts_table() ) );
		$this->assertContains( 'updated_at', columns( contacts_table() ) );
		$this->assertTrue( has_index_on( notes_table(), 'contact_id' ) );

		$r = $this->rest_as(
			'sally',
			'POST',
			'/contacts',
			array(
				'name'  => 'First Customer',
				'email' => 'first@example.test',
			)
		);
		$this->assertSame( 201, $r['status'], $r['body'] );
	}
}
