<?php
/**
 * `wp acme-redirects import|export`.
 */

class ImportExportTest extends AcmeRedirectsCase {

	const AGENCY = __DIR__ . '/fixtures/agency.csv';

	/** Expected report of agency.csv without --update: row => [source, result]. */
	const AGENCY_REPORT = array(
		2  => array( '/agency-a', 'created' ),
		3  => array( '/old-about', 'skipped' ),
		4  => array( 'old-contact-us', 'invalid' ),
		6  => array( '/agency-gone', 'created' ),
		7  => array( '/agency-bad', 'invalid' ),
		8  => array( '/agency-a', 'skipped' ),
		9  => array( '^/(bad', 'invalid' ),
		10 => array( '/agency-c', 'created' ),
		11 => array( '/old-contact', 'skipped' ),
		12 => array( '/agency-d', 'invalid' ),
		13 => array( '^/agency/(\d+)$', 'created' ),
		14 => array( '/agency-e', 'created' ),
	);

	private function report( string $file ): array {
		$this->assertFileExists( $file, 'report file' );
		$rows = self::csv_rows( file_get_contents( $file ) );
		$this->assertSame( array( 'row', 'source', 'result', 'message' ), array_shift( $rows ), 'report header' );
		$out = array();
		foreach ( $rows as $r ) {
			$this->assertCount( 4, $r );
			$out[ (int) $r[0] ] = $r;
		}
		ksort( $out );
		return $out;
	}

	private function assertAgencyReport( array $report, array $expected ): void {
		$this->assertSame( array_keys( $expected ), array_keys( $report ), 'one report line per data row, numbered like the spreadsheet' );
		foreach ( $expected as $row => list( $source, $result ) ) {
			$this->assertSame( $source, $report[ $row ][1], "row $row source" );
			$this->assertSame( $result, $report[ $row ][2], "row $row result" );
			if ( 'invalid' === $result ) {
				$this->assertNotSame( '', trim( $report[ $row ][3] ), "row $row needs a reason" );
			}
		}
	}

	private function assertInvalidRowWarnings( array $run ): void {
		foreach ( array( 4, 7, 9, 12 ) as $row ) {
			$this->assertMatchesRegularExpression( "/^Warning: Row $row: \S.*$/m", $run['stderr'], "warning for row $row" );
		}
		$this->assertSame( 4, preg_match_all( '/^Warning: Row \d+:/m', $run['stderr'] ), 'only invalid rows produce warnings' );
	}

	public function test_dry_run_changes_nothing_and_reports_everything(): void {
		$this->warm_front_end();
		$before = $this->rows();
		$log    = $this->purge_log();
		$report = $this->tmp_path();

		$run = $this->cmd( 'import ' . self::AGENCY . ' --dry-run --report=' . $report );
		$this->assertSame( 1, $run['exit'], 'invalid rows => exit 1' . $run['stdout'] . $run['stderr'] );
		$this->assertSame( 'Error: Dry run: 5 would be created, 0 would be updated, 3 skipped, 4 invalid.', $this->last_line( $run, 'stderr' ) );
		$this->assertInvalidRowWarnings( $run );

		$this->assertSame( $before, $this->rows(), 'a dry run must not change any rule' );
		$this->assertSame( $log, $this->purge_log(), 'a dry run must not purge the CDN' );
		$this->assertNoFrontRedirect( '/agency-a' );
		$this->assertAgencyReport( $this->report( $report ), self::AGENCY_REPORT );

		$run = $this->cmd( 'import ' . self::AGENCY . ' --dry-run --update' );
		$this->assertSame( 'Error: Dry run: 5 would be created, 3 would be updated, 0 skipped, 4 invalid.', $this->last_line( $run, 'stderr' ) );
		$this->assertSame( $before, $this->rows() );
	}

	public function test_import_matches_the_dry_run(): void {
		$this->warm_front_end();
		$dry_report = $this->tmp_path();
		$report     = $this->tmp_path();
		$this->cmd( 'import ' . self::AGENCY . ' --dry-run --report=' . $dry_report );

		$run = $this->cmd( 'import ' . self::AGENCY . ' --report=' . $report );
		$this->assertSame( 1, $run['exit'] );
		$this->assertSame( 'Error: 5 created, 0 updated, 3 skipped, 4 invalid.', $this->last_line( $run, 'stderr' ) );
		$this->assertInvalidRowWarnings( $run );

		$real = $this->report( $report );
		$this->assertAgencyReport( $real, self::AGENCY_REPORT );
		$dry = $this->report( $dry_report );
		$this->assertSame( array_column( $dry, 2, 0 ), array_column( $real, 2, 0 ), 'the dry run predicts the real import' );

		$rows = $this->rows();
		$this->assertCount( 23, $rows );
		$this->assertCount( 1, $this->rows_by_source( '/agency-a' ), 'a source repeated in the file is created once' );
		$a = $this->rows_by_source( '/agency-a' )[0];
		$this->assertSame( '/new-a/', $a['target'] );
		$this->assertSame( 'Agency A', $a['note'] );

		$c = $this->rows_by_source( '/agency-c' )[0];
		$this->assertSame( 'prefix', $c['match_type'] );
		$this->assertSame( 307, (int) $c['status_code'] );
		$this->assertSame( 5, (int) $c['priority'] );
		$this->assertSame( 0, (int) $c['enabled'] );
		$this->assertSame( 'note, with "quotes"', $c['note'] );

		$e = $this->rows_by_source( '/agency-e' )[0];
		$this->assertSame( 'exact', $e['match_type'] );
		$this->assertSame( 301, (int) $e['status_code'] );
		$this->assertSame( 10, (int) $e['priority'] );
		$this->assertSame( 1, (int) $e['enabled'] );

		$gone = $this->rows_by_source( '/agency-gone' )[0];
		$this->assertSame( 410, (int) $gone['status_code'] );

		$this->assertSame( '/about/', $rows[1]['target'], 'existing rules are skipped without --update' );
		$this->assertSame( 12, (int) $rows[1]['hits'] );
		$this->assertSame( array(), $this->rows_by_source( '/old-contact' ), 'the 1.0 rule for old-contact already exists' );
		$this->assertSame( array(), $this->rows_by_source( '/agency-d' ) );

		$inserts = array_filter( $this->purge_log(), static fn( $e ) => str_starts_with( $e, 'insert:' ) );
		$this->assertCount( 5, $inserts, 'CDN purge for every created rule' );

		$this->assertFrontRedirect( '/agency-a', 301, self::h( '/new-a/' ) );
		$this->assertFrontRedirect( '/agency/77', 301, self::h( '/a/77' ) );
		$this->assertSame( 410, $this->front( '/agency-gone' )['status'] );
		$this->assertNoFrontRedirect( '/agency-c/x' );
	}

	public function test_import_update(): void {
		$this->warm_front_end();
		$run = $this->cmd( 'import ' . self::AGENCY . ' --update' );
		$this->assertSame( 1, $run['exit'] );
		$this->assertSame( 'Error: 5 created, 3 updated, 0 skipped, 4 invalid.', $this->last_line( $run, 'stderr' ) );

		$rows = $this->rows();
		$this->assertCount( 23, $rows, 'updates must not create rules' );
		$this->assertSame( '/about-new/', $rows[1]['target'] );
		$this->assertSame( 302, (int) $rows[1]['status_code'] );
		$this->assertSame( 12, (int) $rows[1]['hits'], 'hits are kept' );
		$this->assertSame( '/contact-us/', $rows[2]['target'], 'the 1.0 rule is updated, not duplicated' );
		$this->assertSame( 3, (int) $rows[2]['hits'] );
		$this->assertSame( '/new-a-2/', $this->rows_by_source( '/agency-a' )[0]['target'], 'the later row wins' );
		$this->assertContains( 'update:1', $this->purge_log() );

		$this->assertFrontRedirect( '/old-about', 302, self::h( '/about-new/' ) );
		$this->assertFrontRedirect( '/old-contact', 301, self::h( '/contact-us/' ) );
	}

	public function test_valid_file_succeeds_with_defaults(): void {
		$file = $this->tmp_file( "source,target\n/v-one,/one/\n/v-two,https://example.org/two\n/team,/elsewhere/\n" );
		$run  = $this->cmd( 'import ' . $file );
		$this->assertOk( $run );
		$this->assertSame( 'Success: 2 created, 0 updated, 1 skipped, 0 invalid.', $this->last_line( $run ) );
		$one = $this->rows_by_source( '/v-one' );
		$this->assertCount( 1, $one );
		$this->assertSame( 301, (int) $one[0]['status_code'] );
		$this->assertSame( 'exact', $one[0]['match_type'] );
		$this->assertSame( '/about/team/', $this->row( 15 )['target'] );
	}

	public function test_unreadable_or_malformed_files(): void {
		$before = $this->rows();
		$this->assertFails( $this->cmd( 'import /tmp/does-not-exist-' . wp_rand() . '.csv' ), 'missing file' );
		$this->assertFails( $this->cmd( 'import ' . $this->tmp_file( "from,to\n/a-from,/a-to/\n" ) ), 'no source column' );
		$this->assertFails( $this->cmd( 'import ' . $this->tmp_file( "/b-from,/b-to/\n/c-from,/c-to/\n" ) ), 'no header row' );
		$this->assertSame( $before, $this->rows(), 'nothing may be imported' );
	}

	public function test_export_is_identical_to_the_admin_export(): void {
		$admin = $this->admin_export();
		$run   = $this->cmd( 'export' );
		$this->assertOk( $run );
		$this->assertSame( $admin, $run['stdout'], 'byte-for-byte the admin export' );

		$rows = self::csv_rows( $run['stdout'] );
		$this->assertSame( array( 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'note' ), $rows[0] );
		$this->assertCount( 19, $rows );

		$file = $this->tmp_path();
		$run  = $this->cmd( 'export ' . $file );
		$this->assertOk( $run );
		$this->assertSame( "Success: Exported 18 redirect(s) to $file.", $this->last_line( $run ) );
		$this->assertSame( $admin, file_get_contents( $file ) );

		$run = $this->cmd( 'export --match_type=regex --enabled=yes' );
		$this->assertOk( $run );
		$rows = self::csv_rows( $run['stdout'] );
		$this->assertSame( array( '^/blog/(\d{4})/(\d{2})/(.+?)/?$', '^/events/(.*)$', '^/Products/(.*)$', '^/docs/v1/(.*)', '^/(\d+)$' ), array_column( array_slice( $rows, 1 ), 0 ) );
	}

	public function test_export_import_round_trip(): void {
		$fields = 'source,target,match_type,status,priority,enabled';
		$before = $this->json( $this->cmd( "list --format=json --fields=$fields" ) );
		$file   = $this->tmp_path();
		$this->assertOk( $this->cmd( 'export ' . $file ) );

		$ids = trim( $this->cmd( 'list --format=ids' )['stdout'] );
		$this->assertOk( $this->cmd( 'delete ' . $ids ) );
		$this->assertSame( '0', trim( $this->cmd( 'list --format=count' )['stdout'] ) );

		$run = $this->cmd( 'import ' . $file );
		$this->assertOk( $run );
		$this->assertSame( 'Success: 18 created, 0 updated, 0 skipped, 0 invalid.', $this->last_line( $run ) );
		$this->assertSame( $before, $this->json( $this->cmd( "list --format=json --fields=$fields" ) ) );

		$this->assertFrontRedirect( '/old-contact', 301, self::h( '/contact/' ) );
		$this->assertSame( 410, $this->front( '/retired-product' )['status'] );
		$this->assertNoFrontRedirect( '/disabled-page' );
	}

	/** Runs WP-CLI with a tight memory limit. */
	private function wp_cli_limited( string $args, string $limit = '160M' ): array {
		$mu = WPMU_PLUGIN_DIR . '/wpsb-test-memory-limit.php';
		copy( __DIR__ . '/fixtures/wpsb-test-memory-limit.php', $mu );
		$this->tmp_files[] = $mu;
		$proc = proc_open( 'WPSB_TEST_MEMORY_LIMIT=' . $limit . ' wp ' . $args, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, '/wordpress' );
		$out  = stream_get_contents( $pipes[1] );
		$err  = stream_get_contents( $pipes[2] );
		$code = proc_close( $proc );
		return array( 'exit' => $code, 'stdout' => $out, 'stderr' => $err );
	}

	public function test_import_streams_large_files(): void {
		$file = $this->tmp_path();
		$h    = fopen( $file, 'w' );
		fwrite( $h, "source,target,match_type,status,priority,enabled,note\n" );
		$note = str_repeat( 'Lorem ipsum dolor sit amet ', 1200 );
		for ( $i = 1; $i <= 4000; $i++ ) {
			fputcsv( $h, array( "/bulk/item-$i", "/catalog/item-$i/", 'exact', 301, 10, 'yes', $note ), ',', '"', '' );
		}
		fclose( $h );
		$this->assertGreaterThan( 120 * MB_IN_BYTES, filesize( $file ) );

		$run = $this->wp_cli_limited( 'acme-redirects import ' . $file . ' --dry-run' );
		$this->assertSame( 0, $run['exit'], substr( $run['stdout'] . $run['stderr'], -2000 ) );
		$this->assertSame( 'Success: Dry run: 4000 would be created, 0 would be updated, 0 skipped, 0 invalid.', $this->last_line( $run ) );
		$this->assertCount( 18, $this->rows() );
	}

	public function test_export_streams_large_rule_sets(): void {
		global $wpdb;
		$table  = $wpdb->prefix . 'acme_redirects';
		$target = '/catalog/?' . str_repeat( 'x', 30000 ) . '=';
		$wpdb->query( 'START TRANSACTION' );
		for ( $i = 1; $i <= 4000; $i++ ) {
			$wpdb->insert(
				$table,
				array(
					'source'      => "/bulk/item-$i",
					'target'      => $target . $i,
					'match_type'  => 'exact',
					'status_code' => 301,
					'priority'    => 60,
					'enabled'     => 1,
					'hits'        => 0,
					'note'        => '',
				)
			);
		}
		$wpdb->query( 'COMMIT' );

		$file = $this->tmp_path();
		$run  = $this->wp_cli_limited( 'acme-redirects export ' . $file );
		$this->assertSame( 0, $run['exit'], substr( $run['stdout'] . $run['stderr'], -2000 ) );
		$this->assertSame( "Success: Exported 4018 redirect(s) to $file.", $this->last_line( $run ) );
		$this->assertGreaterThan( 110 * MB_IN_BYTES, filesize( $file ) );

		$h     = fopen( $file, 'r' );
		$count = 0;
		$last  = null;
		while ( false !== ( $r = fgetcsv( $h, 0, ',', '"', '' ) ) ) {
			++$count;
			$last = $r;
		}
		fclose( $h );
		$this->assertSame( 4019, $count );
		$this->assertSame( '^/(\d+)$', $last[0], 'the priority 90 rule comes last' );
	}
}
