<?php
/**
 * wp-admin "Contact entries" screen and CSV export.
 */

use function WPSB\Contact\cls;
use function WPSB\Contact\entries_table;
use function WPSB\Contact\page_id;
use function WPSB\Contact\xpath;

class EntriesAdminTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private static bool $seeded = false;

	private function user( string $login ): int {
		return (int) get_user_by( 'login', $login )->ID;
	}

	/** 45 regular entries + 2 nasty ones (47), directly in the table. */
	private function seed(): void {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$ok = $wpdb->query( 'DELETE FROM ' . entries_table() );
		$wpdb->suppress_errors( false );
		$this->assertNotFalse( $ok, 'Table ' . entries_table() . ' must exist' );
		$post = page_id( 'get-in-touch' );
		$base = strtotime( '2026-01-01 08:00:00 UTC' );
		$add  = function ( int $n, string $email, array $fields ) use ( $wpdb, $post, $base ) {
			$ok = $wpdb->insert(
				entries_table(),
				array(
					'post_id'    => $post,
					'form_id'    => 'lp-quote',
					'email'      => $email,
					'fields'     => wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
					'created_at' => gmdate( 'Y-m-d H:i:s', $base + $n * HOUR_IN_SECONDS ),
				)
			);
			$this->assertNotFalse( $ok, 'insert failed: ' . $wpdb->last_error );
		};
		for ( $i = 1; $i <= 45; $i++ ) {
			$email = sprintf( 'person%02d@example.com', $i );
			$add(
				$i,
				$email,
				array(
					'name'       => sprintf( 'Person %02d', $i ),
					'email'      => $email,
					'company'    => '',
					'service'    => 0 === $i % 3 ? 'Hosting' : 'Design',
					'message'    => 'Message number ' . $i,
					'newsletter' => 'No',
				)
			);
		}
		$add(
			46,
			'evil@example.com',
			array(
				'name'       => '<script>alert("xss")</script>',
				'email'      => 'evil@example.com',
				'company'    => '',
				'service'    => 'Design',
				'message'    => '<img src=x onerror=alert(1)>',
				'newsletter' => 'No',
			)
		);
		$add(
			47,
			'formula@example.com',
			array(
				'name'       => '=HYPERLINK("http://evil.example","click")',
				'email'      => 'formula@example.com',
				'company'    => '+49 30 1234',
				'service'    => 'Design',
				'message'    => "@SUM(A1:A2)\nLine 2, \"quoted\"",
				'newsletter' => 'Yes',
			)
		);
	}

	protected function setUp(): void {
		parent::setUp();
		if ( ! self::$seeded ) {
			$this->seed();
			self::$seeded = true;
		}
	}

	private function screen( string $login, string $query = '' ): array {
		$opts = array( 'login' => $this->http_login( $this->user( $login ) ) );
		return $this->http( 'GET', '/wp-admin/admin.php?page=acme-contact-entries' . $query, $opts ) + array( 'opts' => $opts );
	}

	/** Entry rows of the list table: text of each row. */
	private function rows( string $html ): array {
		$x   = xpath( $html );
		$out = array();
		foreach ( $x->query( '//table[' . cls( 'wp-list-table' ) . ']/tbody/tr' ) as $tr ) {
			if ( false !== strpos( ' ' . $tr->getAttribute( 'class' ) . ' ', ' no-items ' ) ) {
				continue;
			}
			$out[] = trim( preg_replace( '/\s+/', ' ', $tr->textContent ) );
		}
		return $out;
	}

	private function export_link( string $html ): string {
		$x    = xpath( $html );
		$link = $x->query( '//a[' . cls( 'acme-contact-export' ) . ']' );
		$this->assertSame( 1, $link->length, 'Export CSV link (a.acme-contact-export)' );
		return html_entity_decode( $link->item( 0 )->getAttribute( 'href' ) );
	}

	private function parse_csv( string $csv ): array {
		$fh = fopen( 'php://memory', 'r+' );
		fwrite( $fh, $csv );
		rewind( $fh );
		$rows = array();
		while ( ( $row = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
			if ( array( null ) !== $row ) {
				$rows[] = $row;
			}
		}
		fclose( $fh );
		return $rows;
	}

	public function test_access_by_role(): void {
		foreach ( array( 'admin', 'eve' ) as $login ) {
			$res = $this->screen( $login );
			$this->assertSame( 200, $res['status'], "$login may see entries" );
			$this->assertNotEmpty( $this->rows( $res['body'] ) );
		}
		foreach ( array( 'al', 'cora', 'sub' ) as $login ) {
			$res = $this->screen( $login );
			$this->assertSame( 403, $res['status'], "$login must not see entries" );
			$this->assertStringNotContainsString( 'person45@example.com', $res['body'] );
		}
		$res = $this->http( 'GET', '/wp-admin/admin.php?page=acme-contact-entries' );
		$this->assertContains( $res['status'], array( 302, 403 ) );
		$this->assertStringNotContainsString( 'person45@example.com', $res['body'] );
	}

	public function test_list_is_paginated_newest_first(): void {
		$rows = $this->rows( $this->screen( 'admin' )['body'] );
		$this->assertCount( 20, $rows );
		$this->assertStringContainsString( 'formula@example.com', $rows[0] );
		$this->assertStringContainsString( 'evil@example.com', $rows[1] );
		$this->assertStringContainsString( 'person45@example.com', $rows[2] );

		$rows = $this->rows( $this->screen( 'admin', '&paged=2' )['body'] );
		$this->assertCount( 20, $rows );
		$this->assertStringContainsString( 'person27@example.com', $rows[0] );

		$rows = $this->rows( $this->screen( 'admin', '&paged=3' )['body'] );
		$this->assertCount( 7, $rows );
		$this->assertStringContainsString( 'person01@example.com', $rows[6] );
	}

	public function test_search(): void {
		$rows = $this->rows( $this->screen( 'admin', '&s=PERSON07' )['body'] );
		$this->assertCount( 1, $rows );
		$this->assertStringContainsString( 'person07@example.com', $rows[0] );

		$rows = $this->rows( $this->screen( 'admin', '&s=hosting' )['body'] );
		$this->assertCount( 15, $rows, 'Field values are searchable' );

		$rows = $this->rows( $this->screen( 'admin', '&s=' . rawurlencode( 'Message number 44' ) )['body'] );
		$this->assertCount( 1, $rows );
	}

	public function test_entries_are_displayed_safely(): void {
		$res = $this->screen( 'admin', '&s=evil%40example.com' );
		$this->assertCount( 1, $this->rows( $res['body'] ) );
		$this->assertStringNotContainsString( '<script>alert("xss")</script>', $res['body'] );
		$this->assertStringNotContainsString( '<img src=x', $res['body'] );
		$this->assertStringContainsString( '&lt;script&gt;', $res['body'] );
	}

	public function test_csv_export(): void {
		$page = $this->screen( 'admin' );
		$link = $this->export_link( $page['body'] );
		$res  = $this->http( 'GET', $link, $page['opts'] );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'text/csv', $res['headers']['content-type'] ?? '' );
		$rows   = $this->parse_csv( $res['body'] );
		$header = array_shift( $rows );
		$this->assertSame( array( 'id', 'created_at', 'post_id', 'form_id', 'email' ), array_slice( $header, 0, 5 ) );
		foreach ( array( 'name', 'company', 'service', 'message', 'newsletter' ) as $col ) {
			$this->assertContains( $col, $header );
		}
		$this->assertCount( 47, $rows, 'All entries, not only the first page' );

		$by_email = array();
		foreach ( $rows as $row ) {
			$this->assertCount( count( $header ), $row );
			$assoc                       = array_combine( $header, $row );
			$by_email[ $assoc['email'] ] = $assoc;
		}
		$this->assertSame( 'Person 07', $by_email['person07@example.com']['name'] );
		$this->assertSame( 'lp-quote', $by_email['person07@example.com']['form_id'] );
		$this->assertSame( (string) page_id( 'get-in-touch' ), $by_email['person07@example.com']['post_id'] );
		$f = $by_email['formula@example.com'];
		$this->assertSame( '\'=HYPERLINK("http://evil.example","click")', $f['name'] );
		$this->assertSame( "'+49 30 1234", $f['company'] );
		$this->assertSame( "'@SUM(A1:A2)\nLine 2, \"quoted\"", $f['message'] );
		$this->assertSame( 'Yes', $f['newsletter'] );
		$this->assertSame( '<script>alert("xss")</script>', $by_email['evil@example.com']['name'], 'CSV contains the raw value' );

		// Export of a search.
		$page = $this->screen( 'admin', '&s=person0' );
		$res  = $this->http( 'GET', $this->export_link( $page['body'] ), $page['opts'] );
		$rows = $this->parse_csv( $res['body'] );
		array_shift( $rows );
		$this->assertCount( 9, $rows );

		// Editors can export too.
		$page = $this->screen( 'eve' );
		$res  = $this->http( 'GET', $this->export_link( $page['body'] ), $page['opts'] );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'person45@example.com', $res['body'] );
	}

	public function test_authors_cannot_export(): void {
		$page = $this->screen( 'admin' );
		$link = $this->export_link( $page['body'] );
		foreach ( array( 'al', 'sub' ) as $login ) {
			$res = $this->http( 'GET', $link, array( 'login' => $this->http_login( $this->user( $login ) ) ) );
			$this->assertStringNotContainsString( 'person45@example.com', $res['body'], "$login must not get the export" );
			$this->assertNotSame( 200, $res['status'] );
		}
		$res = $this->http( 'GET', $link );
		$this->assertStringNotContainsString( 'person45@example.com', $res['body'] );
	}
}
