<?php
/**
 * Queued imports: upload, batches, results, legacy SKUs, file checks, permissions.
 */

class QueueTest extends ImporterCase {

	/** 250 rows: 245 new (BQ-*), 3 existing products with legacy/draft/private SKUs, 2 without SKU, blank line. */
	private function main_csv(): string {
		$rows   = self::product_rows( 'BQ', 120 );
		$rows[] = array( 'ACME-1002', 'Sledge hammer 5kg', '1.234,50', '9', '', '' );
		$rows[] = null;
		$rows[] = array( 'draft-2002', 'Hose reel XL', '59,00', '', '', '' );
		$rows[] = array( '', 'Row without SKU', '1', '1', '', '' );
		$rows[] = array( 'Priv-4001', '', '199.00', '', '', '' );
		$rows   = array_merge( $rows, self::product_rows( 'BQ', 120, 121 ) );
		$rows[] = array( 'BQ-0241', 'Multi-line product', '5', '1', 'publish', 'Special' );
		$rows[] = array( '   ', 'Another row without SKU', '', '', '', '' );
		$rows   = array_merge( $rows, self::product_rows( 'BQ', 4, 242 ) );
		$csv    = self::csv( $rows );
		// A quoted cell with line breaks does not start a new row.
		$csv    = str_replace( '"Multi-line product"', "\"Multi-line\nproduct\"", $csv, $replaced );
		$this->assertSame( 1, $replaced );
		return $csv;
	}

	public function test_upload_queues_and_cron_imports_in_batches(): void {
		$before_products = $this->count_products();
		$before          = time();
		$job             = $this->queue( $this->main_csv(), 'supplier-2026-09.csv' );

		$this->assertJobShape( $job );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( 'supplier-2026-09.csv', $job['file_name'] );
		$this->assertSame( 250, $job['total'], 'total counts product rows (not the header, blank lines or line breaks inside quoted cells)' );
		$this->assertSame( 0, $job['processed'] );
		$this->assertSame( 0, $job['progress'] );
		$this->assertSame( $this->admin_id(), $job['user'] );
		$this->assertGreaterThanOrEqual( $before - 2, $this->assertIsoDate( $job['created_at'] ) );
		$this->assertNull( $job['started_at'] );
		$this->assertNull( $job['finished_at'] );
		$this->assertSame( $before_products, $this->count_products(), 'nothing is imported during the upload request' );
		$this->assertSame( array(), $this->products_with_sku( 'BQ-0001' ) );

		// First cron round: one batch.
		$this->assertGreaterThan( 0, $this->cron_round(), 'a due cron event must exist right after the upload' );
		$job = $this->job( $job['id'] );
		$this->assertJobShape( $job );
		$this->assertSame( 'running', $job['status'] );
		$this->assertGreaterThan( 0, $job['processed'] );
		$this->assertLessThanOrEqual( 100, $job['processed'], 'one run processes at most one batch (100 rows)' );
		$this->assertIsoDate( $job['started_at'] );
		$this->assertNull( $job['finished_at'] );

		$job = $this->drain( $job['id'] );
		$this->assertJobShape( $job );
		$this->assertSame( 'completed', $job['status'] );
		$this->assertSame( 250, $job['processed'] );
		$this->assertSame( 245, $job['created'] );
		$this->assertSame( 3, $job['updated'] );
		$this->assertSame( 2, $job['skipped'] );
		$this->assertSame( 0, $job['failed'] );
		$this->assertSame( 100, $job['progress'] );
		$this->assertGreaterThanOrEqual( strtotime( $job['started_at'] ), $this->assertIsoDate( $job['finished_at'] ) );

		$this->assertEachSkuOnce( 'BQ-', 245 );
		$this->assertSame( $before_products + 245, $this->count_products() );

		// Mapping rules are unchanged.
		$p = $this->product( 'BQ-0007' );
		$this->assertSame( 'Product BQ 7', $p->post_title );
		$this->assertSame( 'draft', $p->post_status, 'default status for new products' );
		$this->assertSame( 1707, (int) $this->meta( $p->ID, '_acme_price' ) );
		$this->assertSame( 7, (int) $this->meta( $p->ID, '_acme_stock' ) );
		$this->assertSame( array( 'BQ', 'Imported' ), wp_get_object_terms( $p->ID, 'acme_product_cat', array( 'fields' => 'names', 'orderby' => 'name' ) ) );

		$multi = $this->product( 'BQ-0241' );
		$this->assertSame( 'publish', $multi->post_status );
		$this->assertSame( 'Multi-line product', $multi->post_title, 'the quoted line break belongs to the name cell' );
		$this->assertSame( 500, (int) $this->meta( $multi->ID, '_acme_price' ) );

		// Existing products (1.x lower-case SKU, draft, private) were updated, not duplicated.
		$legacy = $this->product( 'ACME-1002' );
		$this->assertSame( 'Sledge hammer 5kg', $legacy->post_title );
		$this->assertSame( 123450, (int) $this->meta( $legacy->ID, '_acme_price' ) );
		$this->assertSame( 'publish', $legacy->post_status );
		$draft = $this->product( 'DRAFT-2002' );
		$this->assertSame( 'Hose reel XL', $draft->post_title );
		$this->assertSame( 'draft', $draft->post_status );
		$private = $this->product( 'PRIV-4001' );
		$this->assertSame( 'Workbench (private)', $private->post_title, 'empty cells keep existing values' );
		$this->assertSame( 19900, (int) $this->meta( $private->ID, '_acme_price' ) );
		$this->assertSame( 'private', $private->post_status );

		// Nothing left to do for this import.
		$this->cron_round( HOUR_IN_SECONDS );
		$this->assertSame( $job, $this->job( $job['id'] ) );
	}

	public function test_reimporting_the_same_file_updates(): void {
		$csv   = self::csv( array_merge( self::product_rows( 'RI', 30 ), array( array( 'acme-1001', 'Claw hammer 16oz', '12,99', '41', '', '' ), array( 'ACME-1004', '', '', '13', '', '' ), array( 'Acme-1003', '', '9,49', '', '', '' ) ) ) );
		$first = $this->drain( $this->queue( $csv )['id'] );
		$this->assertSame( 30, $first['created'] );
		$this->assertSame( 3, $first['updated'], '1.x SKUs are matched case-insensitively (and ignoring stray spaces)' );
		$count = $this->count_products();

		$second = $this->drain( $this->queue( $csv )['id'] );
		$this->assertSame( 'completed', $second['status'] );
		$this->assertSame( 0, $second['created'] );
		$this->assertSame( 33, $second['updated'] );
		$this->assertSame( $count, $this->count_products(), 're-importing must not create products' );
		$this->assertEachSkuOnce( 'RI-', 30 );
		foreach ( array( 'ACME-1001', 'ACME-1003', 'ACME-1004' ) as $sku ) {
			$this->assertCount( 1, $this->products_with_sku( $sku ), $sku );
		}
		$this->assertSame( 13, (int) $this->meta( $this->product( 'ACME-1004' )->ID, '_acme_stock' ) );
		$this->assertSame( 949, (int) $this->meta( $this->product( 'ACME-1003' )->ID, '_acme_price' ) );
	}

	public function test_supplier_format_and_row_counting(): void {
		// Semicolon-delimited Excel export: BOM, CRLF, German headers, quoted line breaks, blank lines.
		$rows = array(
			array( 'SUP-0001', 'Schraube', '1,20', '10', "Zeile 1\r\nZeile 2" ),
			null,
			array( 'SUP-0002', 'Mutter', '0,80', '5', 'eins; zwei' ),
			array( 'SUP-0003', 'Dübel', '1.234,00', '-', "A \"quoted\"\r\nmulti\r\nline" ),
			null,
			null,
			array( 'SUP-0004', 'Winkel', '3,00', '0', '' ),
		);
		$csv  = "\xEF\xBB\xBF" . self::csv( $rows, array( 'Artikelnummer', 'Title', 'Preis', 'Qty', 'Description' ), ';', "\r\n" );
		$job  = $this->queue( $csv, 'lieferant.csv' );
		$this->assertSame( 4, $job['total'] );

		$job = $this->drain( $job['id'] );
		$this->assertSame( 4, $job['processed'] );
		$this->assertSame( 4, $job['created'] );
		$p = $this->product( 'SUP-0003' );
		$this->assertSame( 'Dübel', $p->post_title );
		$this->assertSame( 123400, (int) $this->meta( $p->ID, '_acme_price' ) );
		$this->assertSame( '', $this->meta( $p->ID, '_acme_stock' ) );
		$this->assertStringContainsString( 'multi', $p->post_content );
		$this->assertSame( 'eins; zwei', $this->product( 'SUP-0002' )->post_content );
	}

	public function test_row_data_filter_still_skips_rows(): void {
		$skip = static fn( $data ) => ( is_array( $data ) && str_ends_with( $data['sku'], '5' ) ) ? false : $data;
		add_filter( 'acme_importer_row_data', $skip );
		try {
			$job = $this->drain( $this->queue( self::csv( self::product_rows( 'FS', 20 ) ) )['id'] );
		} finally {
			remove_filter( 'acme_importer_row_data', $skip );
		}
		$this->assertSame( 20, $job['processed'] );
		$this->assertSame( 2, $job['skipped'] );
		$this->assertSame( 18, $job['created'] );
		$this->assertSame( array(), $this->products_with_sku( 'FS-0015' ) );
	}

	public function test_batch_size_setting(): void {
		$this->set_batch_size( 50 );
		$job = $this->queue( self::csv( self::product_rows( 'BS', 120 ) ) );
		$this->cron_round();
		$after_one = $this->job( $job['id'] )['processed'];
		$this->assertGreaterThan( 0, $after_one );
		$this->assertLessThanOrEqual( 50, $after_one, 'batch size 50' );
		$this->cron_round();
		$after_two = $this->job( $job['id'] )['processed'];
		$this->assertGreaterThan( $after_one, $after_two, 'the next run continues' );
		$this->assertLessThanOrEqual( $after_one + 50, $after_two );

		$job = $this->drain( $job['id'] );
		$this->assertSame( 120, $job['processed'] );
		$this->assertEachSkuOnce( 'BS-', 120 );
	}

	public function test_invalid_uploads_are_rejected(): void {
		wp_set_current_user( $this->admin_id() );
		$count = count( $this->rest_data( $this->rest( 'GET', '/acme-importer/v1/imports' ) ) );

		$auth = $this->http_login( $this->admin_id() );
		$r    = $this->multipart( '/wp-json/acme-importer/v1/imports', $auth, array( 'foo' => 'bar' ), array() );
		$this->assertSame( 400, $r['status'], $r['body'] );
		$this->assertSame( 'acme_importer_no_file', $r['json']['code'] ?? null );

		$r = $this->upload( "name,price\nHammer,12\n", 'no-sku.csv' );
		$this->assertSame( 400, $r['status'], $r['body'] );
		$this->assertSame( 'acme_importer_invalid_file', $r['json']['code'] ?? null );

		$r = $this->upload( "sku,name\nX-1,Hammer\n", 'products.php' );
		$this->assertSame( 400, $r['status'], $r['body'] );
		$this->assertSame( 'acme_importer_invalid_file', $r['json']['code'] ?? null );

		$r = $this->upload( '', 'empty.csv' );
		$this->assertSame( 400, $r['status'], $r['body'] );
		$this->assertSame( 'acme_importer_invalid_file', $r['json']['code'] ?? null );

		wp_cache_flush();
		$this->assertCount( $count, $this->rest_data( $this->rest( 'GET', '/acme-importer/v1/imports' ) ), 'nothing queued' );
	}

	public function test_permissions_and_listing(): void {
		$r = $this->http( 'GET', '/wp-json/acme-importer/v1/imports' );
		$this->assertSame( 401, $r['status'] );

		$editor = $this->http_login( $this->user_id( 'eddie' ) );
		$r      = $this->http( 'GET', '/wp-json/acme-importer/v1/imports', array( 'login' => $editor, 'rest_nonce' => true ) );
		$this->assertSame( 403, $r['status'] );
		$r = $this->upload( self::csv( self::product_rows( 'PE', 2 ) ), 'x.csv', 'eddie' );
		$this->assertSame( 403, $r['status'] );
		$this->assertSame( array(), $this->products_with_sku( 'PE-0001' ) );

		// The shop manager can.
		$first  = $this->queue( self::csv( self::product_rows( 'PM', 2 ) ), 'first.csv', 'sam' );
		$second = $this->queue( self::csv( self::product_rows( 'PM', 3, 3 ) ), 'second.csv', 'sam' );
		$this->assertSame( $this->user_id( 'sam' ), $second['user'] );
		$sam = $this->http_login( $this->user_id( 'sam' ) );
		$r   = $this->http( 'GET', '/wp-json/acme-importer/v1/imports', array( 'login' => $sam, 'rest_nonce' => true ) );
		$this->assertSame( 200, $r['status'] );
		$ids = array_column( $r['json'], 'id' );
		$this->assertSame( array( $second['id'], $first['id'] ), array_slice( $ids, 0, 2 ), 'newest first' );
		$this->assertSame( 3, $r['json'][0]['total'] );

		$r = $this->http( 'GET', '/wp-json/acme-importer/v1/imports/' . $first['id'], array( 'login' => $sam, 'rest_nonce' => true ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( 'first.csv', $r['json']['file_name'] );

		$r = $this->http( 'GET', '/wp-json/acme-importer/v1/imports/999999', array( 'login' => $sam, 'rest_nonce' => true ) );
		$this->assertSame( 404, $r['status'] );
		$this->assertSame( 'acme_importer_not_found', $r['json']['code'] ?? null );

		$r = $this->http( 'POST', '/wp-json/acme-importer/v1/imports/' . $first['id'] . '/cancel', array( 'login' => $editor, 'rest_nonce' => true ) );
		$this->assertSame( 403, $r['status'] );
		$this->assertSame( 'queued', $this->job( $first['id'] )['status'] );

		$this->drain( $first['id'] );
		$this->drain( $second['id'] );
	}

	public function test_admin_upload_form_and_progress_on_screen(): void {
		$sam    = $this->user_id( 'sam' );
		$login  = $this->http_login( $sam );
		$before = $this->count_products();
		$r      = $this->multipart(
			'/wp-admin/admin-post.php',
			$login,
			array(
				'action'   => 'acme_importer_upload',
				'_wpnonce' => $this->nonce_for( $sam, 'acme_importer_upload', $login['logged_in'] ),
			),
			array( 'import_file' => array( 'from-the-form.csv', self::csv( self::product_rows( 'AF', 60 ) ) ) ),
			false
		);
		$this->assertContains( $r['status'], array( 302, 303 ), 'the form redirects back: ' . substr( $r['body'], 0, 500 ) );
		$this->assertSame( $before, $this->count_products(), 'nothing imported during the upload request' );

		wp_set_current_user( $this->admin_id() );
		$list = $this->rest_data( $this->rest( 'GET', '/acme-importer/v1/imports' ) );
		$job  = $list[0];
		$this->jobs_created[] = $job['id'];
		$this->assertSame( 'from-the-form.csv', $job['file_name'] );
		$this->assertSame( $sam, $job['user'] );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( 60, $job['total'] );

		$this->assertScreenRow( $login, $job['id'], 'Queued', 0, 60 );

		$this->set_batch_size( 25 );
		$this->cron_round();
		$processed = $this->job( $job['id'] )['processed'];
		$this->assertScreenRow( $login, $job['id'], 'Running', $processed, 60 );

		$this->drain( $job['id'] );
		$this->assertScreenRow( $login, $job['id'], 'Completed', 60, 60 );
		$this->assertEachSkuOnce( 'AF-', 60 );
	}

	private function assertScreenRow( array $login, int $id, string $status, int $value, int $max ): void {
		$r = $this->http( 'GET', '/wp-admin/edit.php?post_type=acme_product&page=acme-importer', array( 'login' => $login ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertMatchesRegularExpression( '#<tr[^>]*\bid=["\']acme-import-' . $id . '["\'][^>]*>(.*?)</tr>#s', $r['body'], "row for import $id" );
		preg_match( '#<tr[^>]*\bid=["\']acme-import-' . $id . '["\'][^>]*>(.*?)</tr>#s', $r['body'], $m );
		$row = $m[1];
		$this->assertStringContainsString( $status, wp_strip_all_tags( $row ), "status label in the row of import $id" );
		$this->assertMatchesRegularExpression( '#<progress[^>]*>#', $row );
		preg_match( '#<progress([^>]*)>#', $row, $p );
		$this->assertMatchesRegularExpression( '#\bvalue=["\']' . $value . '["\']#', $p[1], 'progress value' );
		$this->assertMatchesRegularExpression( '#\bmax=["\']' . $max . '["\']#', $p[1], 'progress max' );
	}
}
