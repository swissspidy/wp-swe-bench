<?php
/**
 * Step 2: row validation and the error report.
 */

class ValidationTest extends ImporterCase {

	/** Row => [sku as in the file, reason fragment]. */
	private function rows(): array {
		return array(
			array( 'VA-0001', 'Valid one', '1.234,50', '5', 'publish', 'Valid' ),              // row 2
			array( '=HYPERLINK("http://evil.example","x")', 'Formula', '1', '1', '', '' ),     // row 3
			array( '-VA-LEAD', 'Leading dash', '1', '1', '', '' ),                             // row 4
			array( 'VA', 'Too short', '1', '1', '', '' ),                                      // row 5
			array( 'VA-0002', '', '1', '1', '', '' ),                                          // row 6 (new product without name)
			array( 'VA-0003', 'Bad price', 'abc', '1', '', '' ),                               // row 7
			array( 'VA-0004', 'Negative price', '-5,00', '1', '', '' ),                        // row 8
			null,                                                                              // row 9 (blank)
			array( 'VA-0005', 'Fractional stock', '1', '3.5', '', '' ),                        // row 10
			array( 'VA-0006', 'Negative stock', '1', '-2', '', '' ),                           // row 11
			array( 'VA-0007', 'Unknown status', '1', '1', 'archived', '' ),                    // row 12
			array( 'VA-0008', str_repeat( 'x', 201 ), '1', '1', '', '' ),                      // row 13
			array( '', 'No SKU', '1', '1', '', '' ),                                           // row 14 (skipped)
			array( 'acme-1005', '', '26,90', '-', '', '' ),                                    // row 15 (existing: no name needed)
			array( 'VA-0009', 'Forbidden category', '1', '1', '', 'Forbidden|Tools' ),         // row 16 (plugin rule)
			array( 'VA-0010', 'Valid two', '1,234.50', '0', 'pending', '' ),                   // row 17
			array( 'VA-0011', 'Two problems', '-1', 'many', '', '' ),                          // row 18
			array( '+VA-0012', 'Plus sign', '1', '1', '', '' ),                                // row 19
			array( '@VA-0013', 'At sign', '1', '1', '', '' ),                                  // row 20
		);
	}

	const INVALID_ROWS = array( 3, 4, 5, 6, 7, 8, 10, 11, 12, 13, 16, 18, 19, 20 );

	public function test_invalid_rows_are_rejected_and_reported(): void {
		update_option( 'wpsb_test_log_saves', 1 );
		$seen = array();
		$rule = function ( $errors, $row = null, $import_id = null ) use ( &$seen ) {
			$seen[] = $import_id;
			if ( is_array( $row ) && false !== stripos( (string) ( $row['categories'] ?? '' ), 'Forbidden' ) ) {
				$errors[] = 'Category "Forbidden" is not allowed.';
			}
			return $errors;
		};
		add_filter( 'acme_importer_validate_row', $rule, 10, 3 );
		try {
			$job  = $this->queue( self::csv( $this->rows() ) );
			$done = $this->drain( $job['id'] );
		} finally {
			remove_filter( 'acme_importer_validate_row', $rule, 10 );
		}

		$this->assertJobShape( $done );
		$this->assertSame( 'completed', $done['status'] );
		$this->assertSame( 18, $done['total'] );
		$this->assertSame( 18, $done['processed'] );
		$this->assertSame( 2, $done['created'] );
		$this->assertSame( 1, $done['updated'] );
		$this->assertSame( 1, $done['skipped'] );
		$this->assertSame( 14, $done['failed'] );
		$this->assertContains( $done['id'], $seen, 'the import ID is passed to acme_importer_validate_row' );

		// Nothing of an invalid row is saved, not even partially.
		$saved = array_map( static fn( $l ) => explode( ' ', trim( $l ), 2 )[1] ?? '', file( '/tmp/wpsb-saves.log' ) );
		sort( $saved );
		$this->assertSame( array( 'ACME-1005', 'VA-0001', 'VA-0010' ), $saved, 'only valid rows reach saving' );
		foreach ( array( 'VA-0002', 'VA-0003', 'VA-0004', 'VA-0005', 'VA-0006', 'VA-0007', 'VA-0008', 'VA-0009', 'VA-0011' ) as $sku ) {
			$this->assertSame( array(), $this->products_with_sku( $sku ), $sku );
		}
		$this->assertSame( 123450, (int) $this->meta( $this->product( 'VA-0001' )->ID, '_acme_price' ) );
		$this->assertSame( 'pending', $this->product( 'VA-0010' )->post_status );
		$hatchet = $this->product( 'ACME-1005' );
		$this->assertSame( 'Hatchet', $hatchet->post_title );
		$this->assertSame( 2690, (int) $this->meta( $hatchet->ID, '_acme_price' ) );

		// Error report.
		$r = $this->error_report( $done['id'] );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertStringStartsWith( 'text/csv', $r['headers']['content-type'] ?? '' );
		$this->assertMatchesRegularExpression( '/attachment;\s*filename="?import-' . $done['id'] . '-errors\.csv"?/', $r['headers']['content-disposition'] ?? '' );

		$rows = self::parse_csv( $r['body'] );
		$this->assertSame( array( 'row', 'sku', 'attempts', 'error' ), array_shift( $rows ) );
		$this->assertSame( array_map( 'strval', self::INVALID_ROWS ), array_column( $rows, 0 ), 'one line per invalid row, by spreadsheet row number' );
		$by_row = array_column( $rows, null, 0 );
		foreach ( $rows as $line ) {
			$this->assertCount( 4, $line );
			$this->assertSame( '0', $line[2], 'invalid rows have no save attempts' );
			$this->assertNotSame( '', trim( $line[3] ), 'every invalid row has a reason' );
			foreach ( $line as $cell ) {
				$this->assertDoesNotMatchRegularExpression( '/^[=+\-@\t\r]/', $cell, 'no cell may start like a formula' );
			}
		}
		$this->assertSame( '\'=HYPERLINK("http://evil.example","x")', $by_row['3'][1] );
		$this->assertSame( "'-VA-LEAD", $by_row['4'][1] );
		$this->assertSame( 'VA', $by_row['5'][1] );
		$this->assertSame( "'+VA-0012", $by_row['19'][1] );
		$this->assertSame( "'@VA-0013", $by_row['20'][1] );
		$this->assertSame( 'VA-0009', $by_row['16'][1] );
		$this->assertStringContainsString( 'Forbidden', $by_row['16'][3] );
	}

	public function test_error_report_permissions_and_empty_report(): void {
		$done = $this->drain( $this->queue( self::csv( self::product_rows( 'VE', 3 ) ) )['id'] );
		$r    = $this->error_report( $done['id'], 'sam' );
		$this->assertSame( 200, $r['status'] );
		$this->assertSame( array( array( 'row', 'sku', 'attempts', 'error' ) ), self::parse_csv( $r['body'] ) );

		$this->assertSame( 403, $this->error_report( $done['id'], 'eddie' )['status'] );
		$this->assertSame( 401, $this->http( 'GET', '/wp-json/acme-importer/v1/imports/' . $done['id'] . '/errors' )['status'] );
		$this->assertSame( 404, $this->error_report( 999999 )['status'] );
	}

	public function test_import_screen_links_the_error_report(): void {
		$bad  = $this->drain( $this->queue( self::csv( array( array( 'VS-0001', 'Good', '1', '1', '', '' ), array( 'VS-0002', 'Bad', 'xyz', '1', '', '' ) ) ) )['id'] );
		$good = $this->drain( $this->queue( self::csv( self::product_rows( 'VT', 2 ) ) )['id'] );
		$this->assertSame( 1, $bad['failed'] );

		$sam = $this->http_login( $this->user_id( 'sam' ) );
		$r   = $this->http( 'GET', '/wp-admin/edit.php?post_type=acme_product&page=acme-importer', array( 'login' => $sam ) );
		$this->assertSame( 200, $r['status'] );
		preg_match( '#<tr[^>]*\bid=["\']acme-import-' . $bad['id'] . '["\'][^>]*>(.*?)</tr>#s', $r['body'], $m );
		$this->assertNotEmpty( $m, 'row of the import' );
		$this->assertMatchesRegularExpression( '#<a[^>]*class=["\'][^"\']*acme-import-error-report[^"\']*["\'][^>]*>#', $m[1] );
		preg_match( '#<a[^>]*class=["\'][^"\']*acme-import-error-report[^"\']*["\'][^>]*>#', $m[1], $a );
		preg_match( '#href=["\']([^"\']+)["\']#', $a[0], $href );
		$url = html_entity_decode( $href[1] );

		$download = $this->http( 'GET', $url, array( 'login' => $sam ) );
		$this->assertSame( 200, $download['status'], $download['body'] );
		$rows = self::parse_csv( $download['body'] );
		$this->assertSame( array( 'row', 'sku', 'attempts', 'error' ), $rows[0] );
		$this->assertCount( 2, $rows );
		$this->assertSame( array( '3', 'VS-0002', '0' ), array_slice( $rows[1], 0, 3 ) );

		preg_match( '#<tr[^>]*\bid=["\']acme-import-' . $good['id'] . '["\'][^>]*>(.*?)</tr>#s', $r['body'], $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( 'acme-import-error-report', $m[1], 'no link without errors' );
	}
}
