<?php
/**
 * Step 2: failing rows are retried with backoff, then failed for good.
 */

class RetryTest extends ImporterCase {

	/** @var array<int,array> Calls of acme_importer_retry_delay seen in this process. */
	private array $delays = array();

	private function record_delays(): void {
		add_filter(
			'acme_importer_retry_delay',
			function ( $seconds, $attempt = null, $row = null, $import_id = null ) {
				$this->delays[] = array(
					'seconds' => $seconds,
					'attempt' => $attempt,
					'sku'     => is_array( $row ) ? ( $row['sku'] ?? null ) : null,
					'import'  => $import_id,
				);
				return $seconds;
			},
			1,
			4
		);
	}

	protected function tearDown(): void {
		remove_all_filters( 'acme_importer_retry_delay', 1 );
		parent::tearDown();
	}

	public function test_failing_rows_do_not_block_and_wait_for_the_backoff(): void {
		update_option(
			'wpsb_test_throw_skus',
			array(
				'RT-0005' => 1,
				'RT-0012' => 1,
			)
		);
		$job = $this->queue( self::csv( self::product_rows( 'RT', 30 ) ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->cron_round();
		}
		$job = $this->job( $job['id'] );
		$this->assertJobShape( $job );
		$this->assertSame( 'running', $job['status'], 'rows are waiting for another attempt' );
		$this->assertSame( 28, $job['processed'], 'the other rows were imported' );
		$this->assertSame( 28, $job['created'] );
		$this->assertSame( 0, $job['failed'] );
		$this->assertSame( 2, $job['retrying'] ?? null );
		$this->assertSame(
			array(
				'RT-0005' => 0,
				'RT-0012' => 0,
			),
			$this->db_option( 'wpsb_test_throw_skus' ),
			'both rows were attempted once'
		);
		$this->assertSame( array(), $this->products_with_sku( 'RT-0005' ) );
		$this->assertCount( 1, $this->products_with_sku( 'RT-0013' ), 'rows after the failing one were imported' );

		// Not retried before the delay (60 s) has passed.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->cron_round( 30 );
			sleep( 1 );
		}
		$this->assertSame( array(), $this->products_with_sku( 'RT-0005' ), 'no retry before 60 seconds' );
		$this->assertSame( 2, $this->job( $job['id'] )['retrying'] );
		$this->assertSame( 'running', $this->job( $job['id'] )['status'] );
	}

	public function test_retries_with_backoff_then_fail_for_good(): void {
		$this->record_delays();
		update_option( 'wpsb_test_retry_delay', 1 );
		update_option(
			'wpsb_test_throw_skus',
			array(
				'RB-0004' => 1,
				'RB-0010' => 9,
			)
		);
		update_option( 'wpsb_test_reject_marker', 'REJECTME' );
		$rows    = self::product_rows( 'RB', 12 );
		$rows[5] = array( 'RB-0006', 'Broken REJECTME product', '12,00', '1', '', '' );
		$job     = $this->queue( self::csv( $rows ) );
		$done    = $this->drain( $job['id'] );

		$this->assertJobShape( $done );
		$this->assertSame( 'completed', $done['status'] );
		$this->assertSame( 12, $done['processed'] );
		$this->assertSame( 10, $done['created'] );
		$this->assertSame( 2, $done['failed'] );
		$this->assertSame( 0, $done['retrying'] ?? null );
		$this->assertCount( 1, $this->products_with_sku( 'RB-0004' ), 'imported on the second attempt' );
		$this->assertSame( array(), $this->products_with_sku( 'RB-0010' ) );
		$this->assertSame( array(), $this->products_with_sku( 'RB-0006' ) );
		$this->assertSame( 6, $this->db_option( 'wpsb_test_throw_skus' )['RB-0010'] ?? null, 'exactly 3 attempts' );

		// Backoff: 60 s after the first failed attempt, 300 s after the second.
		$calls = array();
		foreach ( $this->delays as $d ) {
			$calls[ $d['sku'] ][] = array( $d['seconds'], $d['attempt'] );
			$this->assertSame( $done['id'], $d['import'], 'import ID passed to the filter' );
		}
		$this->assertSame( array( array( 60, 1 ) ), $calls['RB-0004'] ?? null );
		$this->assertSame( array( array( 60, 1 ), array( 300, 2 ) ), $calls['RB-0010'] ?? null );
		$this->assertSame( array( array( 60, 1 ), array( 300, 2 ) ), $calls['RB-0006'] ?? null, 'errors returned while saving are retried too' );

		$r = $this->error_report( $done['id'] );
		$this->assertSame( 200, $r['status'] );
		$rows = self::parse_csv( $r['body'] );
		$this->assertSame( array( 'row', 'sku', 'attempts', 'error' ), $rows[0] );
		$this->assertCount( 3, $rows );
		$this->assertSame( array( '7', 'RB-0006', '3' ), array_slice( $rows[1], 0, 3 ) );
		$this->assertNotSame( '', $rows[1][3] );
		$this->assertSame( array( '11', 'RB-0010', '3' ), array_slice( $rows[2], 0, 3 ) );
		$this->assertStringContainsString( 'ERP connection reset', $rows[2][3] );
	}

	public function test_cancel_drops_pending_retries(): void {
		update_option( 'wpsb_test_throw_skus', array( 'RC-0002' => 1 ) );
		$job = $this->queue( self::csv( self::product_rows( 'RC', 5 ) ) );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->cron_round();
		}
		$this->assertSame( 1, $this->job( $job['id'] )['retrying'] ?? null );

		wp_set_current_user( $this->admin_id() );
		$response = $this->rest( 'POST', '/acme-importer/v1/imports/' . $job['id'] . '/cancel' );
		$this->assertSame( 200, $response->get_status() );
		$data = $this->rest_data( $response );
		$this->assertSame( 'cancelled', $data['status'] );
		$this->assertSame( 0, $data['retrying'] );

		update_option( 'wpsb_test_retry_delay', 0 );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->cron_round( HOUR_IN_SECONDS );
		}
		$this->assertSame( array(), $this->products_with_sku( 'RC-0002' ), 'no retry after cancelling' );
		$this->assertSame( 'cancelled', $this->job( $job['id'] )['status'] );
	}

	public function test_crash_recovery_still_works_with_retries(): void {
		update_option( 'wpsb_test_lock_timeout', 2 );
		update_option( 'wpsb_test_retry_delay', 1 );
		update_option( 'wpsb_test_crash_sku', 'RK-0030' );
		update_option( 'wpsb_test_throw_skus', array( 'RK-0010' => 1 ) );
		add_filter( 'acme_importer_lock_timeout', $short = static fn() => 2, 999 );
		touch( '/tmp/wpsb-crash-armed' );
		try {
			$job = $this->queue( self::csv( self::product_rows( 'RK', 40 ) ) );
			$run = $this->cron_round_subprocess( 0 );
			$this->assertFileDoesNotExist( '/tmp/wpsb-crash-armed', 'crashed at row 30: ' . $run['stdout'] . $run['stderr'] );
			sleep( 3 );
			$done = $this->drain( $job['id'] );
		} finally {
			remove_filter( 'acme_importer_lock_timeout', $short, 999 );
		}
		$this->assertSame( 'completed', $done['status'] );
		$this->assertSame( 40, $done['processed'] );
		$this->assertSame( 0, $done['failed'] );
		$this->assertEachSkuOnce( 'RK-', 40 );
	}
}
