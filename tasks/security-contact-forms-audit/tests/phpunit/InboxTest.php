<?php
/**
 * The Submissions screen keeps working: pagination, search, filters, views, single view, legacy rows.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\form_id;
use function WPSB\Forms\row;
use function WPSB\Forms\sub_id;
use function WPSB\Forms\xpath;

class InboxTest extends HttpCase {

	private function row_ids( string $html ): array {
		$ids = array();
		foreach ( xpath( $html )->query( "//input[@type='checkbox' and @name='submission[]']" ) as $cb ) {
			$ids[] = (int) $cb->getAttribute( 'value' );
		}
		return $ids;
	}

	private function total( string $html ): string {
		preg_match( '/class="displaying-num">([^<]*)</', $html, $m );
		return trim( $m[1] ?? '' );
	}

	public function test_pagination_search_filters_and_views(): void {
		global $wpdb;
		$res = $this->inbox( 'admin' );
		$this->assertSame( '45 items', $this->total( $res['body'] ) );
		$ids = $this->row_ids( $res['body'] );
		$this->assertCount( 20, $ids );
		$this->assertSame( array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}acme_form_submissions ORDER BY created_at DESC, id DESC LIMIT 20" ) ), $ids, 'newest first' );

		$res = $this->inbox( 'admin', array( 'paged' => 3 ) );
		$this->assertCount( 5, $this->row_ids( $res['body'] ) );

		$res = $this->inbox( 'admin', array( 's' => 'visitor1' ) );
		$this->assertSame( '10 items', $this->total( $res['body'] ) );

		$res = $this->inbox( 'admin', array( 's' => 'Lena' ) );
		$this->assertSame( array( sub_id( 'legacy1@example.org' ) ), $this->row_ids( $res['body'] ), 'search finds 1.x rows' );
		$this->assertPresent( 'Lena Legacy', $res['body'], 'response' );

		$res = $this->inbox( 'erin', array( 'form_id' => form_id( 'job-application' ) ) );
		$this->assertEqualsCanonicalizing( array( sub_id( 'anna@example.org' ), sub_id( 'ben@example.org' ) ), $this->row_ids( $res['body'] ) );

		$res = $this->inbox( 'erin', array( 'status' => 'new' ) );
		$new = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}acme_form_submissions WHERE status = 'new'" );
		$this->assertSame( $new . ' items', $this->total( $res['body'] ) );

		$res = $this->inbox( 'admin', array( 'orderby' => 'email', 'order' => 'asc', 'form_id' => form_id( 'newsletter' ) ) );
		$this->assertSame( array( sub_id( 'news1@example.org' ), sub_id( 'news2@example.org' ), sub_id( 'news3@example.org' ) ), $this->row_ids( $res['body'] ) );
		$this->assertPresent( 'News2', $res['body'], '1.x form definitions still label the values' );
	}

	public function test_single_view_and_legacy_rows(): void {
		$id = sub_id( 'visitor30@example.org' );
		$this->assertSame( 'new', row( $id )->status );
		$res = $this->view( 'erin', $id );
		$this->assertPresent( 'Visitor 30', $res['body'], 'response' );
		$this->assertPresent( 'Hello, this is question number 30.', $res['body'], 'response' );
		$this->assertSame( 1, xpath( $res['body'] )->query( "//a[@href='https://visitor30.example']" )->length, 'website link' );
		$this->assertSame( 'read', row( $id )->status, 'opening marks it read' );

		$res = $this->view( 'admin', sub_id( 'legacy1@example.org' ) );
		$this->assertPresent( 'Lena Legacy', $res['body'], 'response' );
		$this->assertPresent( 'Sent with the old 1.x form.', $res['body'], 'response' );

		$res = $this->view( 'admin', sub_id( 'anna@example.org' ) );
		foreach ( array( 'Anna Andersson', 'cv-anna.pdf', 'portfolio.html', 'I would love to join the design team.' ) as $needle ) {
			$this->assertPresent( $needle, $res['body'], 'response' );
		}
	}

	public function test_inbox_is_for_inbox_users(): void {
		foreach ( array( 'sally', 'arthur', 'connie' ) as $login ) {
			$res = $this->get( $login, '/wp-admin/admin.php?page=acme-forms-submissions' );
			$this->assertSame( 403, $res['status'], $login );
			$this->assertAbsent( 'visitor01@example.org', $res['body'], 'response' );
			$res = $this->get( $login, '/wp-admin/admin.php?page=acme-forms-submissions&action=view&submission=' . sub_id( 'anna@example.org' ) );
			$this->assertSame( 403, $res['status'], $login );
			$this->assertAbsent( 'Anna Andersson', $res['body'], 'response' );
		}
		$res = $this->get( null, '/wp-admin/admin.php?page=acme-forms-submissions' );
		$this->assertContains( $res['status'], array( 302, 401, 403 ) );
	}
}
