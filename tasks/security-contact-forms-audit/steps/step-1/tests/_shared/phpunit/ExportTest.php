<?php
/**
 * F-3: the CSV export and its date range.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\by_email;
use function WPSB\Forms\count_rows;
use function WPSB\Forms\form_id;
use function WPSB\Forms\parse_csv;

class ExportTest extends HttpCase {

	private function ids_between( int $form, ?string $from, ?string $to ): array {
		global $wpdb;
		$sql = $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}acme_form_submissions WHERE form_id = %d", $form );
		if ( $from ) {
			$sql .= $wpdb->prepare( ' AND created_at >= %s', "$from 00:00:00" );
		}
		if ( $to ) {
			$sql .= $wpdb->prepare( ' AND created_at <= %s', "$to 23:59:59" );
		}
		return array_map( 'strval', $wpdb->get_col( $sql . ' ORDER BY id ASC' ) );
	}

	private function csv_ids( array $res ): array {
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertStringStartsWith( 'text/csv', $res['headers']['content-type'] ?? '' );
		$rows = parse_csv( $res['body'] );
		$this->assertSame( 'Submission ID', $rows[0][0] ?? null );
		return array_column( array_slice( $rows, 1 ), 0 );
	}

	public function test_export_with_and_without_date_range(): void {
		$contact = form_id( 'contact-us' );
		$form    = $this->export_form( 'admin' );

		$res = $this->export( 'admin', array( 'form_id' => $contact, 'from' => '', 'to' => '' ), $form );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$this->assertMatchesRegularExpression( '/attachment;\s*filename="?acme-form-' . $contact . '-\d{4}-\d{2}-\d{2}\.csv/', $res['headers']['content-disposition'] ?? '' );
		$rows = parse_csv( $res['body'] );
		$this->assertSame( array( 'Submission ID', 'Submitted (UTC)', 'Status', 'Your name', 'E-mail', 'Website', 'Topic', 'Message' ), $rows[0] );
		$this->assertSame( $this->ids_between( $contact, null, null ), array_column( array_slice( $rows, 1 ), 0 ), 'every submission of the form, oldest first' );
		$this->assertCount( 41, $rows );

		$by_id = array_column( array_slice( $rows, 1 ), null, 0 );
		$v05   = by_email( 'visitor05@example.org' );
		$this->assertSame( array( (string) $v05->id, $v05->created_at, 'read', 'Visitor 05', 'visitor05@example.org', '', 'Press', "Hello, this is question number 05.\nThanks!" ), $by_id[ $v05->id ] );
		$legacy = by_email( 'legacy1@example.org' );
		$this->assertSame( 'Lena Legacy', $by_id[ $legacy->id ][3], '1.x rows are exported' );

		// Ranges (both days inclusive).
		$this->assertSame( $this->ids_between( $contact, '2026-04-01', '2026-04-30' ), $this->csv_ids( $this->export( 'admin', array( 'form_id' => $contact, 'from' => '2026-04-01', 'to' => '2026-04-30' ), $form ) ) );
		$this->assertCount( 12, $this->ids_between( $contact, '2026-04-01', '2026-04-30' ) );
		$this->assertSame( $this->ids_between( $contact, '2026-06-20', null ), $this->csv_ids( $this->export( 'admin', array( 'form_id' => $contact, 'from' => '2026-06-20', 'to' => '' ), $form ) ) );
		$this->assertSame( $this->ids_between( $contact, null, '2026-01-15' ), $this->csv_ids( $this->export( 'admin', array( 'form_id' => $contact, 'to' => '2026-01-15' ), $form ) ) );
		$this->assertCount( 2, $this->ids_between( $contact, null, '2026-01-15' ) );

		// Editors export too; other forms have their own columns.
		$jobs = form_id( 'job-application' );
		$res  = $this->export( 'erin', array( 'form_id' => $jobs ) );
		$rows = parse_csv( $res['body'] );
		$this->assertSame( array( 'Submission ID', 'Submitted (UTC)', 'Status', 'Full name', 'E-mail', 'Portfolio website', 'CV', 'Work sample', 'Photo', 'Cover letter' ), $rows[0] );
		$this->assertCount( 3, $rows );
		$this->assertSame( 'cv-anna.pdf', $rows[1][6] );
		$this->assertSame( 'portfolio.html', $rows[1][7] );
	}

	public function test_malformed_dates_are_rejected(): void {
		$contact = form_id( 'contact-us' );
		$form    = $this->export_form( 'admin' );
		$before  = count_rows();
		$bad     = array(
			"2026-04-01' OR '1'='1",
			"2099-01-01' OR form_id > '0",
			'2026-04-01 00:00:00',
			'2026-02-30',
			'2026-4-1',
			'20260401',
			'yesterday',
			"2026-04-01'; DELETE FROM wp_acme_form_submissions; --",
		);
		foreach ( $bad as $value ) {
			foreach ( array( 'from', 'to' ) as $param ) {
				$other = 'from' === $param ? array( 'to' => '2026-04-30' ) : array( 'from' => '2026-04-01' );
				$res   = $this->export( 'admin', array( 'form_id' => $contact, $param => $value ) + $other, $form );
				$this->assertSame( 400, $res['status'], "$param=$value: " . substr( $res['body'], 0, 200 ) );
				$this->assertAbsent( 'Submission ID', $res['body'], "$param=$value: no CSV" );
				$this->assertAbsent( '@example.org', $res['body'], "$param=$value: no data" );
			}
		}
		$this->assertSame( $before, count_rows(), 'nothing changed' );
		$this->assertNotNull( by_email( 'sentinel@example.org' ) );
		$this->assertAbsent( 'database error', $this->new_debug_log(), 'no database errors' );
	}

	public function test_export_access(): void {
		$contact = form_id( 'contact-us' );
		$form    = $this->export_form( 'admin' );

		// Other accounts replaying the form.
		foreach ( array( 'sally', 'arthur' ) as $login ) {
			$res = $this->submit( $login, $form['method'], $form['action'], array_merge( $form['values'], array( 'form_id' => $contact ) ) );
			$this->assertSame( 403, $res['status'], $login );
			$this->assertAbsent( 'visitor', $res['body'], 'response' );
		}
		// Logged out.
		$res = $this->submit( null, $form['method'], $form['action'], array_merge( $form['values'], array( 'form_id' => $contact ) ) );
		$this->assertNotSame( 200, $res['status'] );
		$this->assertAbsent( 'visitor', $res['body'], 'response' );
		// Without the export form's token.
		$res = $this->get( 'admin', '/wp-admin/admin-post.php?action=acme_forms_export&form_id=' . $contact );
		$this->assertSame( 403, $res['status'] );
		$this->assertAbsent( 'visitor', $res['body'], 'response' );
	}
}
