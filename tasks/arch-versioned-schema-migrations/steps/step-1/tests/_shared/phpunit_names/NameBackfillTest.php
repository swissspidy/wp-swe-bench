<?php
/**
 * 1.6: first/last names — batched, resumable backfill that coexists with live traffic.
 */

use WPSB\CRM\CrmTestCase;
use function WPSB\CRM\columns;
use function WPSB\CRM\contact_by_email;
use function WPSB\CRM\contacts_table;
use function WPSB\CRM\db_version;
use function WPSB\CRM\delete_raw_option;
use function WPSB\CRM\set_raw_option;
use function WPSB\CRM\expected_split;
use function WPSB\CRM\has_index_on;
use function WPSB\CRM\log_entries;
use function WPSB\CRM\migration_log;

class NameBackfillTest extends CrmTestCase {

	/** id => [full_name, created_at] in the pristine database. */
	private function originals(): array {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( 'SELECT id, full_name, created_at FROM ' . contacts_table() . ' ORDER BY id', ARRAY_A ) as $row ) {
			$out[ (int) $row['id'] ] = array( $row['full_name'], $row['created_at'] );
		}
		return $out;
	}

	/** Rows that have a first or last name. */
	private function named_ids(): array {
		global $wpdb;
		if ( ! in_array( 'first_name', columns( contacts_table() ), true ) ) {
			return array();
		}
		return array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . contacts_table() . " WHERE first_name <> '' OR last_name <> ''" ) );
	}

	/** A contact the backfill has not reached yet (highest such ID). */
	private function unreached_id( array $exclude = array() ): int {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . contacts_table() . " WHERE first_name = '' AND last_name = '' AND TRIM(full_name) <> '' ORDER BY id DESC" ) );
		$ids = array_values( array_diff( $ids, $exclude ) );
		$this->assertNotEmpty( $ids, 'Expected contacts that the backfill has not reached yet after the first request' );
		return $ids[0];
	}

	public function test_backfill_is_batched_resumable_and_safe_with_live_edits(): void {
		global $wpdb;
		$originals = $this->originals();
		$this->assertCount( 1200, $originals );

		// First request after the deploy.
		$this->assertSame( 200, $this->visit( '/' )['status'] );
		$this->assertContains( 'first_name', columns( contacts_table() ), 'Migration 3 must run on the first request' );
		$this->assertContains( 'full_name', columns( contacts_table() ), 'full_name must stay until the backfill is complete' );
		$done = $this->named_ids();
		$this->assertLessThanOrEqual( 500, count( $done ), 'A request may backfill at most 500 contacts' );
		$this->assertLessThan( 4, (int) db_version(), 'Migration 4 is not applied before every contact is done' );
		$this->assertSame( array(), log_entries( 4, 'applied' ) );

		// Live traffic in the middle of the backfill, while another request holds the
		// migration lock (so these requests don't advance the backfill themselves).
		set_raw_option( 'acme_crm_migration_lock', time() );
		$read_id  = $this->unreached_id();
		$edit_id  = $this->unreached_id( array( $read_id ) );
		$legacy_id = $this->unreached_id( array( $read_id, $edit_id ) );
		$half_id  = $this->unreached_id( array( $read_id, $edit_id, $legacy_id ) );

		$r = $this->rest_as( 'sally', 'GET', "/contacts/$read_id" );
		$this->assertSame( 200, $r['status'], $r['body'] );
		list( $f, $l ) = expected_split( $originals[ $read_id ][0] );
		$this->assertSame( $f, $r['json']['first_name'], 'Contacts not reached yet must read as already split' );
		$this->assertSame( $l, $r['json']['last_name'] );
		$this->assertSame( trim( "$f $l" ), $r['json']['name'] );

		$r = $this->rest_as( 'sally', 'GET', '/contacts?search=' . rawurlencode( trim( "$f $l" ) ) );
		$this->assertContains( $read_id, array_column( (array) $r['json'], 'id' ), 'Search must find contacts the backfill has not reached yet' );

		$r = $this->rest_as( 'sally', 'PATCH', "/contacts/$edit_id", array( 'first_name' => 'Johann Sebastian', 'last_name' => 'Bach' ) );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$r = $this->rest_as( 'sally', 'PATCH', "/contacts/$legacy_id", array( 'name' => 'Wolfgang Amadeus Mozart' ) );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( 'Wolfgang Amadeus', $r['json']['first_name'] );
		$this->assertSame( 'Mozart', $r['json']['last_name'] );
		$r = $this->rest_as( 'sally', 'PATCH', "/contacts/$half_id", array( 'last_name' => 'Schumann' ) );
		$this->assertSame( 200, $r['status'], $r['body'] );
		list( $half_first ) = expected_split( $originals[ $half_id ][0] );
		$this->assertSame( $half_first, $r['json']['first_name'], 'Updating only the last name keeps the first name' );
		$r = $this->rest_as( 'sally', 'POST', '/contacts', array( 'name' => 'Clara Wieck', 'email' => 'clara@example.test' ) );
		$this->assertSame( 201, $r['status'], $r['body'] );
		$clara = (int) $r['json']['id'];
		delete_raw_option( 'acme_crm_migration_lock' );

		// Keep serving requests until everything is migrated.
		$requests = 1;
		$prev     = $this->named_ids();
		while ( 5 !== db_version() && $requests < 8 ) {
			$this->assertSame( 200, $this->visit( 0 === $requests % 2 ? '/' : '/wp-json/' )['status'] );
			++$requests;
			if ( in_array( 'full_name', columns( contacts_table() ), true ) ) {
				$now = $this->named_ids();
				$this->assertLessThanOrEqual( 500, count( array_diff( $now, $prev ) ), "Request $requests backfilled more than 500 contacts" );
				$prev = $now;
			}
		}
		$this->assertSame( 5, db_version(), "The backfill must finish (after $requests requests)" );
		$this->assertLessThanOrEqual( 5, $requests, 'The backfill must continue where it stopped (1,200 contacts, 500 per request)' );

		// Schema.
		$cols = columns( contacts_table() );
		$this->assertContains( 'first_name', $cols );
		$this->assertContains( 'last_name', $cols );
		$this->assertNotContains( 'full_name', $cols, 'Migration 5 drops full_name' );
		$this->assertTrue( has_index_on( contacts_table(), 'last_name' ), 'index on last_name' );
		foreach ( array( 3, 4, 5 ) as $v ) {
			$this->assertCount( 1, log_entries( $v, 'applied' ), "migration $v logged once: " . wp_json_encode( migration_log() ) );
		}

		// Data.
		$rows = $wpdb->get_results( 'SELECT id, first_name, last_name, updated_at FROM ' . contacts_table() . ' ORDER BY id', OBJECT_K );
		$this->assertSame( 'Johann Sebastian', $rows[ $edit_id ]->first_name, 'Edits made during the backfill must not be overwritten' );
		$this->assertSame( 'Bach', $rows[ $edit_id ]->last_name );
		$this->assertSame( 'Wolfgang Amadeus', $rows[ $legacy_id ]->first_name );
		$this->assertSame( 'Mozart', $rows[ $legacy_id ]->last_name );
		$this->assertSame( 'Schumann', $rows[ $half_id ]->last_name );
		$this->assertSame( array( 'Clara', 'Wieck' ), array( $rows[ $clara ]->first_name, $rows[ $clara ]->last_name ) );

		$wrong = array();
		$touched = array();
		foreach ( $originals as $id => list( $full, $created ) ) {
			if ( in_array( $id, array( $edit_id, $legacy_id, $half_id ), true ) ) {
				continue;
			}
			$expected = expected_split( $full );
			if ( array( $rows[ $id ]->first_name, $rows[ $id ]->last_name ) !== $expected ) {
				$wrong[] = "#$id '$full' => '{$rows[ $id ]->first_name}' / '{$rows[ $id ]->last_name}' (expected '{$expected[0]}' / '{$expected[1]}')";
			}
			if ( $rows[ $id ]->updated_at !== $created ) {
				$touched[] = $id;
			}
		}
		$this->assertSame( array(), array_slice( $wrong, 0, 15 ), count( $wrong ) . ' contacts were split incorrectly' );
		$this->assertSame( array(), array_slice( $touched, 0, 15 ), 'The backfill must not change updated_at' );
	}

	public function test_named_examples(): void {
		$this->migrate_fully();
		$cases = array(
			'named01' => array( 'Ludwig', 'van Beethoven' ),
			'named02' => array( 'Juan', 'de la Cruz' ),
			'named03' => array( 'Vincent', 'van der Berg' ),
			'named04' => array( 'Van', 'Morrison' ),
			'named05' => array( 'Mary Ann', 'Smith' ),
			'named06' => array( 'Martin Luther', 'King Jr.' ),
			'named07' => array( 'John', 'Smith III' ),
			'named08' => array( 'Bob', 'Jr.' ),
			'named09' => array( 'Cher', '' ),
			'named10' => array( 'John', 'Smith' ),
			'named11' => array( 'Dick', 'van Dyke' ),
			'named12' => array( 'Grace Brewster', 'Hopper' ),
			'named13' => array( '', '' ),
			'named14' => array( '', '' ),
			'named17' => array( 'de', 'Gaulle' ),
			'named19' => array( 'José María Álvarez', 'del Castillo' ),
			'named22' => array( 'Sammy', 'Davis jr' ),
			'named26' => array( 'Hans De', 'Vries' ),
			'named27' => array( 'Conan', "O'Brien" ),
			'named28' => array( 'Lee Ann', 'Marie' ),
			'named30' => array( 'Emma', 'de la Fontaine Jr.' ),
			'named31' => array( 'Karl', 'der Große' ),
		);
		foreach ( $cases as $who => $expected ) {
			$c = contact_by_email( "$who@example.test" );
			$this->assertSame( $expected, array( $c['first_name'], $c['last_name'] ), "$who@example.test" );
		}
	}
}
