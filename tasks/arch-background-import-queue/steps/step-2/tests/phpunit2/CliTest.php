<?php
/**
 * Step 2: `wp acme-importer queue|run|status|cancel`.
 */

class CliTest extends ImporterCase {

	/** @var string[] */
	private array $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $f ) {
			@unlink( $f );
		}
		parent::tearDown();
	}

	private function file( string $csv, string $ext = '.csv' ): string {
		$f = tempnam( sys_get_temp_dir(), 'wpsb-cli-' );
		@unlink( $f );
		$f .= $ext;
		file_put_contents( $f, $csv );
		$this->files[] = $f;
		return $f;
	}

	private function cli_queue( string $csv, string $extra = '' ): int {
		$run = $this->wp_cli( 'acme-importer queue ' . $this->file( $csv ) . ' --porcelain ' . $extra );
		$this->assertSame( 0, $run['exit'], $run['stdout'] . $run['stderr'] );
		$this->assertMatchesRegularExpression( '/^\d+$/', trim( $run['stdout'] ), 'porcelain prints the ID' );
		$id                   = (int) trim( $run['stdout'] );
		$this->jobs_created[] = $id;
		return $id;
	}

	private static function lines( string $out ): array {
		return array_values( array_filter( array_map( 'trim', explode( "\n", $out ) ), 'strlen' ) );
	}

	public function test_queue_and_run_with_progress(): void {
		$run = $this->wp_cli( 'acme-importer queue ' . $this->file( self::csv( self::product_rows( 'CL', 50 ) ) ) . ' --user=sam' );
		$this->assertSame( 0, $run['exit'], $run['stderr'] );
		$this->assertMatchesRegularExpression( '/^Success: Queued import (\d+) \(50 rows\)\.$/m', $run['stdout'] );
		preg_match( '/Queued import (\d+)/', $run['stdout'], $m );
		$id                   = (int) $m[1];
		$this->jobs_created[] = $id;

		$job = $this->job( $id );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( $this->user_id( 'sam' ), $job['user'] );
		$this->assertSame( 50, $job['total'] );
		$this->assertSame( array(), $this->products_with_sku( 'CL-0001' ), 'queue does not import' );

		$run = $this->wp_cli( "acme-importer run $id --batch-size=20" );
		$this->assertSame( 0, $run['exit'], $run['stdout'] . $run['stderr'] );
		$lines    = self::lines( $run['stdout'] );
		$progress = array_values( preg_grep( '#^Processed \d+/\d+ rows \(\d+%\)\.$#', $lines ) );
		$this->assertGreaterThanOrEqual( 3, count( $progress ), 'a progress line per batch: ' . $run['stdout'] );
		$this->assertSame( 'Processed 20/50 rows (40%).', $progress[0] );
		$this->assertSame( 'Processed 50/50 rows (100%).', end( $progress ) );
		$this->assertSame( "Success: Import $id completed: 50 created, 0 updated, 0 skipped, 0 failed.", end( $lines ) );

		$job = $this->job( $id );
		$this->assertSame( 'completed', $job['status'] );
		$this->assertEachSkuOnce( 'CL-', 50 );

		$status = $this->wp_cli( "acme-importer status $id --format=json" );
		$this->assertSame( 0, $status['exit'] );
		$this->assertSame( $job, json_decode( trim( $status['stdout'] ), true ), 'status --format=json is the REST object' );

		$again = $this->wp_cli( "acme-importer run $id" );
		$this->assertSame( 1, $again['exit'] );
		$this->assertStringContainsString( "Error: Import $id is completed.", $again['stderr'] );
	}

	public function test_run_waits_for_retries(): void {
		update_option( 'wpsb_test_retry_delay', 2 );
		update_option( 'wpsb_test_throw_skus', array( 'CR-0003' => 1, 'CR-0007' => 5 ) );
		$id    = $this->cli_queue( self::csv( self::product_rows( 'CR', 10 ) ) );
		$start = time();
		$run   = $this->wp_cli( "acme-importer run $id" );
		$this->assertSame( 0, $run['exit'], $run['stdout'] . $run['stderr'] );
		$this->assertGreaterThanOrEqual( 4, time() - $start, 'the command waited for the retries' );
		$lines = self::lines( $run['stdout'] );
		$this->assertSame( "Success: Import $id completed: 9 created, 0 updated, 0 skipped, 1 failed.", end( $lines ) );
		$this->assertCount( 1, $this->products_with_sku( 'CR-0003' ) );
		$this->assertSame( 2, $this->db_option( 'wpsb_test_throw_skus' )['CR-0007'] ?? null, '3 attempts' );
		$job = $this->job( $id );
		$this->assertSame( 'completed', $job['status'] );
		$this->assertSame( 0, $job['retrying'] ?? null );
	}

	public function test_run_all_and_nothing_to_do(): void {
		$a   = $this->cli_queue( self::csv( self::product_rows( 'CA', 3 ) ) );
		$b   = $this->cli_queue( self::csv( self::product_rows( 'CB', 4 ) ) );
		$run = $this->wp_cli( 'acme-importer run' );
		$this->assertSame( 0, $run['exit'], $run['stdout'] . $run['stderr'] );
		$successes = array_values( preg_grep( '/^Success: Import \d+ completed/', self::lines( $run['stdout'] ) ) );
		$this->assertContains( "Success: Import $a completed: 3 created, 0 updated, 0 skipped, 0 failed.", $successes );
		$this->assertContains( "Success: Import $b completed: 4 created, 0 updated, 0 skipped, 0 failed.", $successes );
		$this->assertLessThan( array_search( "Success: Import $b completed: 4 created, 0 updated, 0 skipped, 0 failed.", $successes, true ), array_search( "Success: Import $a completed: 3 created, 0 updated, 0 skipped, 0 failed.", $successes, true ), 'oldest first' );

		$run = $this->wp_cli( 'acme-importer run' );
		$this->assertSame( 0, $run['exit'] );
		$this->assertSame( 'Success: No imports to run.', trim( $run['stdout'] ) );
	}

	public function test_errors_and_cancel(): void {
		$run = $this->wp_cli( 'acme-importer run 999999' );
		$this->assertSame( 1, $run['exit'] );
		$this->assertStringContainsString( 'Error: Import 999999 not found.', $run['stderr'] );

		$run = $this->wp_cli( 'acme-importer queue /tmp/does-not-exist-' . wp_rand() . '.csv' );
		$this->assertSame( 1, $run['exit'] );
		$this->assertStringContainsString( 'Error:', $run['stderr'] );

		wp_set_current_user( $this->admin_id() );
		$count = count( $this->rest_data( $this->rest( 'GET', '/acme-importer/v1/imports' ) ) );
		$run   = $this->wp_cli( 'acme-importer queue ' . $this->file( "name,price\nHammer,1\n" ) );
		$this->assertSame( 1, $run['exit'], 'no SKU column' );
		$this->assertStringContainsString( 'Error:', $run['stderr'] );
		$run = $this->wp_cli( 'acme-importer queue ' . $this->file( "sku,name\nX-1,Hammer\n", '.txt' ) );
		$this->assertSame( 1, $run['exit'], 'not a CSV file' );
		wp_cache_flush();
		$this->assertCount( $count, $this->rest_data( $this->rest( 'GET', '/acme-importer/v1/imports' ) ), 'nothing queued' );

		$id  = $this->cli_queue( self::csv( self::product_rows( 'CC', 3 ) ) );
		$run = $this->wp_cli( "acme-importer cancel $id" );
		$this->assertSame( 0, $run['exit'], $run['stderr'] );
		$this->assertSame( "Success: Cancelled import $id.", trim( $run['stdout'] ) );
		$this->assertSame( 'cancelled', $this->job( $id )['status'] );

		$run = $this->wp_cli( "acme-importer cancel $id" );
		$this->assertSame( 1, $run['exit'] );
		$this->assertStringContainsString( 'Error:', $run['stderr'] );
		$run = $this->wp_cli( "acme-importer run $id" );
		$this->assertSame( 1, $run['exit'] );
		$this->assertStringContainsString( "Error: Import $id is cancelled.", $run['stderr'] );
		$run = $this->wp_cli( 'acme-importer cancel 999999' );
		$this->assertSame( 1, $run['exit'] );
		$this->assertSame( array(), $this->products_with_sku( 'CC-0001' ) );
	}

	public function test_run_respects_a_busy_runner(): void {
		$job    = $this->queue( self::csv( self::product_rows( 'CZ', 40 ) ) );
		$nested = null;
		$probe  = function () use ( &$nested, $job ) {
			if ( null !== $nested ) {
				return;
			}
			$nested = array( 'before' => $this->count_products() );
			$nested['run']   = $this->wp_cli( 'acme-importer run ' . $job['id'] );
			$nested['after'] = $this->count_products();
		};
		add_action( 'acme_importer_product_saved', $probe, 5 );
		try {
			$this->cron_round();
		} finally {
			remove_action( 'acme_importer_product_saved', $probe, 5 );
		}
		$this->assertNotNull( $nested );
		$this->assertSame( 1, $nested['run']['exit'], $nested['run']['stdout'] . $nested['run']['stderr'] );
		$this->assertStringContainsString( 'Error: Import ' . $job['id'] . ' is being processed by another process.', $nested['run']['stderr'] );
		$this->assertSame( $nested['before'], $nested['after'] );

		$done = $this->drain( $job['id'] );
		$this->assertSame( 40, $done['created'] );
		$this->assertEachSkuOnce( 'CZ-', 40 );
	}
}
