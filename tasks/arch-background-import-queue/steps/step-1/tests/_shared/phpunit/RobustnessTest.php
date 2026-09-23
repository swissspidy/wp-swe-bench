<?php
/**
 * Interrupted runners, overlapping runners, cancelling.
 */

class RobustnessTest extends ImporterCase {

	public function test_killed_runner_loses_nothing_and_duplicates_nothing(): void {
		update_option( 'wpsb_test_lock_timeout', 2 );
		add_filter( 'acme_importer_lock_timeout', $short = static fn() => 2, 999 );
		update_option( 'wpsb_test_crash_sku', 'KR-0150' );
		touch( '/tmp/wpsb-crash-armed' );

		try {
			$job = $this->queue( self::csv( self::product_rows( 'KR', 250 ) ) );

			// Cron requests in separate processes, until one of them gets killed at row 150.
			$crashed = false;
			for ( $i = 0; $i < 6 && ! $crashed; $i++ ) {
				$run     = $this->cron_round_subprocess( 0 );
				$crashed = ! file_exists( '/tmp/wpsb-crash-armed' );
				if ( ! $crashed ) {
					$this->assertSame( 0, $run['exit'], $run['stdout'] . $run['stderr'] );
				}
			}
			$this->assertTrue( $crashed, 'the runner reached row 150 within a few cron runs' );
			$this->assertNotSame( 0, $run['exit'], 'the runner was killed' );

			$mid = $this->job( $job['id'] );
			$this->assertJobShape( $mid );
			$this->assertSame( 'running', $mid['status'] );
			$this->assertGreaterThanOrEqual( 100, $mid['processed'], 'the first batch was persisted' );
			$this->assertLessThan( 150, $mid['processed'], 'row 150 was not finished' );
			$this->assertCount( 1, $this->products_with_sku( 'KR-0150' ), 'the product of row 150 was saved before the crash' );

			// The import continues on its own once the dead runner's claim expired.
			sleep( 3 );
			$done = $this->drain( $job['id'] );
		} finally {
			remove_filter( 'acme_importer_lock_timeout', $short, 999 );
		}

		$this->assertJobShape( $done );
		$this->assertSame( 'completed', $done['status'] );
		$this->assertSame( 250, $done['processed'] );
		$this->assertSame( 0, $done['failed'] );
		$this->assertSame( 0, $done['skipped'] );
		$this->assertGreaterThanOrEqual( 249, $done['created'] );
		$this->assertEachSkuOnce( 'KR-', 250 );
		$this->assertSame( 'Product KR 150', $this->product( 'KR-0150' )->post_title );
		$this->assertSame( 'Product KR 250', $this->product( 'KR-0250' )->post_title );
	}

	public function test_overlapping_runner_leaves_the_import_alone(): void {
		update_option( 'wpsb_test_log_saves', 1 );
		$job    = $this->queue( self::csv( self::product_rows( 'OV', 150 ) ) );
		$nested = null;
		$probe  = function () use ( &$nested ) {
			if ( null !== $nested || null === self::$running_event ) {
				return;
			}
			// A second cron request runs the same event while this one is busy.
			list( $hook, $args ) = self::$running_event;
			$nested              = array( 'before' => $this->count_products() );
			$php                 = sprintf( 'do_action_ref_array( %s, %s );', var_export( $hook, true ), var_export( $args, true ) );
			$nested['run']       = $this->wp_cli( 'eval ' . escapeshellarg( $php ) );
			$nested['after']     = $this->count_products();
		};
		add_action( 'acme_importer_product_saved', $probe, 5 );
		try {
			$this->cron_round();
		} finally {
			remove_action( 'acme_importer_product_saved', $probe, 5 );
		}

		$this->assertNotNull( $nested, 'the batch started' );
		$this->assertSame( 0, $nested['run']['exit'], 'the second runner must exit cleanly: ' . $nested['run']['stderr'] );
		$this->assertSame( $nested['before'], $nested['after'], 'the second runner must not import anything while the first one is busy' );
		$first = $this->job( $job['id'] );
		$this->assertSame( 'running', $first['status'] );
		$this->assertGreaterThanOrEqual( 10, $first['processed'], 'the first runner keeps going' );
		$this->assertLessThanOrEqual( 100, $first['processed'] );

		$done = $this->drain( $job['id'] );
		$this->assertSame( 'completed', $done['status'] );
		$this->assertSame( 150, $done['processed'] );
		$this->assertSame( 150, $done['created'] );
		$this->assertEachSkuOnce( 'OV-', 150 );

		$saves = array_count_values( array_map( static fn( $l ) => explode( ' ', trim( $l ) )[1] ?? '', file( '/tmp/wpsb-saves.log' ) ) );
		$this->assertCount( 150, $saves );
		$this->assertSame( array(), array_filter( $saves, static fn( $n ) => $n > 1 ), 'every row is processed exactly once' );
	}

	public function test_cancel_from_another_request_stops_the_running_batch(): void {
		$job      = $this->queue( self::csv( self::product_rows( 'CX', 250 ) ) );
		$sam      = $this->http_login( $this->user_id( 'sam' ) );
		$response = null;
		$this->cron_round();
		$this->assertLessThan( 120, $this->job( $job['id'] )['processed'] );

		$cancel = function ( $id, $data ) use ( $job, $sam, &$response ) {
			if ( 'CX-0120' === $data['sku'] && null === $response ) {
				$response = $this->http( 'POST', '/wp-json/acme-importer/v1/imports/' . $job['id'] . '/cancel', array( 'login' => $sam, 'rest_nonce' => true ) );
			}
		};
		add_action( 'acme_importer_product_saved', $cancel, 10, 2 );
		try {
			for ( $i = 0; $i < 5 && null === $response; $i++ ) {
				$this->cron_round();
			}
		} finally {
			remove_action( 'acme_importer_product_saved', $cancel, 10 );
		}

		$this->assertNotNull( $response, 'row 120 was reached' );
		$this->assertSame( 200, $response['status'], $response['body'] );
		$this->assertSame( 'cancelled', $response['json']['status'] );
		$this->assertIsoDate( $response['json']['finished_at'] );

		$after = $this->job( $job['id'] );
		$this->assertJobShape( $after );
		$this->assertSame( 'cancelled', $after['status'] );
		$this->assertContains( $after['processed'], array( 119, 120 ), 'the running batch stopped before its next row' );
		$this->assertCount( 1, $this->products_with_sku( 'CX-0120' ) );
		$this->assertSame( array(), $this->products_with_sku( 'CX-0121' ) );

		// Nothing else happens for this import.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->cron_round( HOUR_IN_SECONDS );
		}
		$this->assertSame( $after, $this->job( $job['id'] ) );
		$this->assertSame( array(), $this->products_with_sku( 'CX-0121' ) );

		// Can't cancel twice.
		$r = $this->http( 'POST', '/wp-json/acme-importer/v1/imports/' . $job['id'] . '/cancel', array( 'login' => $sam, 'rest_nonce' => true ) );
		$this->assertSame( 409, $r['status'] );
		$this->assertSame( 'acme_importer_not_cancellable', $r['json']['code'] ?? null );
	}

	public function test_cancel_queued_and_completed_imports(): void {
		$queued = $this->queue( self::csv( self::product_rows( 'CQ', 30 ) ) );
		wp_set_current_user( $this->admin_id() );
		$response = $this->rest( 'POST', '/acme-importer/v1/imports/' . $queued['id'] . '/cancel' );
		$this->assertSame( 200, $response->get_status() );
		$data = $this->rest_data( $response );
		$this->assertSame( 'cancelled', $data['status'] );
		$this->assertSame( 0, $data['processed'] );
		$this->assertIsoDate( $data['finished_at'] );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->cron_round( HOUR_IN_SECONDS );
		}
		$this->assertSame( 0, $this->job( $queued['id'] )['processed'] );
		$this->assertSame( array(), $this->products_with_sku( 'CQ-0001' ) );

		$done = $this->drain( $this->queue( self::csv( self::product_rows( 'CD', 5 ) ) )['id'] );
		wp_set_current_user( $this->admin_id() );
		$response = $this->rest( 'POST', '/acme-importer/v1/imports/' . $done['id'] . '/cancel' );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'acme_importer_not_cancellable', $response->get_data()['code'] ?? null );
		$this->assertSame( 'completed', $this->job( $done['id'] )['status'] );

		$response = $this->rest( 'POST', '/acme-importer/v1/imports/999999/cancel' );
		$this->assertSame( 404, $response->get_status() );
	}
}
