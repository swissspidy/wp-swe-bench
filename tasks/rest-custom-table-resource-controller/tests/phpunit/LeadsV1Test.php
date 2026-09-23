<?php
/**
 * v1: existing behaviour for the CRM sync and the lead form, and the SQL injection fix.
 */

use function WPSB\Leads\dispatch;
use function WPSB\Leads\hdr;
use function WPSB\Leads\ids;
use function WPSB\Leads\lead_queries;
use function WPSB\Leads\table;
use function WPSB\Leads\user_id;

class LeadsV1Test extends WPSB\TestCase {

	public function test_v1_pages_for_the_crm_sync(): void {
		$this->login_as( user_id( 'mona' ) );
		$res = dispatch( 'GET', '/acme-leads/v1/leads', array( 'page' => 2, 'per_page' => 10, 'orderby' => 'name', 'order' => 'asc' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( ids( 'ORDER BY name ASC LIMIT 10 OFFSET 10' ), array_column( $res->get_data(), 'id' ) );
		$this->assertSame( '2000', hdr( $res, 'X-WP-Total' ) );
		$this->assertSame( '200', hdr( $res, 'X-WP-TotalPages' ) );
		$this->assertEqualsCanonicalizing(
			array( 'id', 'name', 'email', 'company', 'status', 'source', 'score', 'owner', 'created' ),
			array_keys( $res->get_data()[0] )
		);
	}

	public function test_v1_status_filter_and_uppercase_order(): void {
		$this->login_as( 1 );
		$res = dispatch( 'GET', '/acme-leads/v1/leads', array( 'status' => 'won', 'per_page' => 100, 'orderby' => 'score', 'order' => 'DESC' ) );
		$this->assertSame( 200, $res->get_status() );
		$items = $res->get_data();
		$this->assertNotEmpty( $items );
		$scores = array_column( $items, 'score' );
		$sorted = $scores;
		rsort( $sorted );
		$this->assertSame( $sorted, $scores );
		$this->assertSame( array( 'won' ), array_values( array_unique( array_column( $items, 'status' ) ) ) );
		$this->assertSame( (string) count( ids( "WHERE status = 'won'" ) ), hdr( $res, 'X-WP-Total' ) );

		$res = dispatch( 'GET', '/acme-leads/v1/leads', array( 'per_page' => 5, 'orderby' => 'created_at', 'order' => 'asc' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( '2024-01-01 08:00:00', $res->get_data()[0]['created'] );
	}

	public function test_v1_is_for_managers_only(): void {
		$this->assertSame( 401, dispatch( 'GET', '/acme-leads/v1/leads' )->get_status() );
		$this->login_as( user_id( 'rita' ) );
		$this->assertSame( 403, dispatch( 'GET', '/acme-leads/v1/leads' )->get_status() );
	}

	public function test_lead_form_submissions(): void {
		$this->clear_mails();
		$res = dispatch( 'POST', '/acme-leads/v1/leads', array(), array( 'name' => 'Form Person', 'email' => 'form.person@bigcorp.example', 'company' => 'BigCorp', 'message' => 'Call me' ) );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . table() . ' WHERE email = %s', 'form.person@bigcorp.example' ), ARRAY_A );
		$this->assertNotNull( $row );
		$this->assertSame( 'new', $row['status'] );
		$this->assertSame( 'form', $row['source'] );
		$this->assertSame( 70, (int) $row['score'] );
		$this->assertSame( 'Call me', $row['notes'] );
		$subjects = array_column( $this->mails(), 'subject' );
		$this->assertContains( 'New lead: Form Person', $subjects );

		$bot = dispatch( 'POST', '/acme-leads/v1/leads', array(), array( 'name' => 'Bot', 'email' => 'bot@spam.example', 'website' => 'http://spam.example' ) );
		$this->assertSame( 201, $bot->get_status() );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . table() . ' WHERE email = %s', 'bot@spam.example' ) ) );
	}

	public static function injection_probes(): array {
		return array(
			'stacked query'       => array( 'orderby', 'name; DROP TABLE wp_acme_leads' ),
			'boolean blind'       => array( 'orderby', "(CASE WHEN (SELECT substr(user_pass,1,1) FROM wp_users WHERE ID=1)='\$' THEN name ELSE email END)" ),
			'subquery column'     => array( 'orderby', 'name,(SELECT user_pass FROM wp_users LIMIT 1)' ),
			'comment'             => array( 'orderby', 'score/**/ASC,(SELECT 1 FROM wp_users)' ),
			'order subquery'      => array( 'order', 'ASC, (SELECT user_pass FROM wp_users LIMIT 1)' ),
			'order limit comment' => array( 'order', 'DESC LIMIT 1 -- ' ),
			'unknown column'      => array( 'orderby', 'notes' ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'injection_probes' )]
	public function test_v1_rejects_sql_in_sort_parameters( string $param, string $payload ): void {
		$this->login_as( user_id( 'mona' ) );
		$run = $this->count_queries( fn() => dispatch( 'GET', '/acme-leads/v1/leads', array( $param => $payload, 'per_page' => 5 ) ) );
		$this->assertSame( 400, $run['result']->get_status(), 'Unsupported sort parameters must be rejected' );
		foreach ( lead_queries( $run['queries'] ) as $sql ) {
			$this->assertStringNotContainsString( $payload, $sql, 'Request value reached the SQL' );
			$this->assertStringNotContainsStringIgnoringCase( 'user_pass', $sql );
		}
		$this->assertSame( 2000, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . table() ) );
	}
}
