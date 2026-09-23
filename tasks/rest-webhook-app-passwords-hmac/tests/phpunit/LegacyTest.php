<?php
/**
 * Existing behaviour that must keep working (mostly pass-to-pass).
 */

class LegacyTest extends AcmeOrdersCase {

	protected bool $use_transactions = false;

	public function test_ping_stays_public(): void {
		$res = $this->http( 'GET', '/wp-json/acme-orders/v1/ping' );
		$this->assertSame( 200, $res['status'] );
		$this->assertTrue( $res['json']['ok'] );
		$this->assertSame( 'acme-orders-sync', $res['json']['plugin'] );
		$this->assertIsString( $res['json']['version'] );
	}

	public function test_seeded_orders_are_intact(): void {
		$d = $this->order_post( 'default', 'D-4410' );
		$this->assertGreaterThan( 0, $d );
		$this->assertSame( 5490, (int) get_post_meta( $d, '_acme_order_total', true ) );
		$this->assertSame( 'paid', get_post_meta( $d, '_acme_order_status', true ) );

		$eu = $this->order_post( 'shop-eu', 'EU-10001' );
		$this->assertSame( 'shipped', get_post_meta( $eu, '_acme_order_status', true ) );
		$this->assertSame( 12950, (int) get_post_meta( $eu, '_acme_order_total', true ) );

		$us = $this->order_post( 'shop-us', 'US-2001' );
		$this->assertSame( 'refunded', get_post_meta( $us, '_acme_order_status', true ) );
		$this->assertSame( 2000, (int) get_post_meta( $us, '_acme_order_refunded', true ) );
		$this->assertSame( 'USD', get_post_meta( $us, '_acme_order_currency', true ) );
	}

	public function test_cli_replay_applies_right_away(): void {
		$this->clear_mails();
		$event = $this->event( 'order.created', array( 'number' => 'US-90001', 'currency' => 'USD', 'total' => '19.99' ) );
		$file  = sys_get_temp_dir() . '/wpsb-replay-' . wp_rand() . '.json';
		file_put_contents( $file, wp_json_encode( $event ) );

		$res = $this->wp_cli( 'acme-orders replay ' . escapeshellarg( $file ) . ' --source=shop-us' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] . $res['stdout'] );
		$this->assertStringContainsString( 'Success', $res['stdout'] );
		wp_cache_flush();
		$post_id = $this->order_post( 'shop-us', 'US-90001' );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( 1999, (int) get_post_meta( $post_id, '_acme_order_total', true ) );
		$this->assertCount( 1, $this->pick_mails( 'US-90001' ) );

		$refund = $this->event( 'order.refunded', array( 'number' => 'US-90001', 'currency' => 'USD', 'total' => '19.99', 'refund' => array( 'amount' => '4,99' ) ) );
		file_put_contents( $file, wp_json_encode( $refund ) );
		$res = $this->wp_cli( 'acme-orders replay ' . escapeshellarg( $file ) . ' --source=shop-us' );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		wp_cache_flush();
		$this->assertSame( 'refunded', get_post_meta( $post_id, '_acme_order_status', true ) );
		$this->assertSame( 499, (int) get_post_meta( $post_id, '_acme_order_refunded', true ) );

		$res = $this->wp_cli( 'acme-orders replay ' . escapeshellarg( $file ) . ' --source=nope' );
		$this->assertNotSame( 0, $res['exit'] );
		unlink( $file );
	}

	public function test_admin_screens_render(): void {
		$login = $this->http_login( 1 );
		$page  = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-orders-sync', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'shop-eu', $page['body'] );
		$this->assertStringContainsString( 'shop-us', $page['body'] );
		$this->assertStringContainsString( 'acme_orders_sync_settings[log_level]', $page['body'] );

		$log = $this->http( 'GET', '/wp-admin/tools.php?page=acme-orders-sync-log', array( 'login' => $login ) );
		$this->assertSame( 200, $log['status'] );
		$this->assertStringContainsString( 'acme-orders-log', $log['body'] );

		$editor = $this->create_user( 'editor' );
		$denied = $this->http( 'GET', '/wp-admin/tools.php?page=acme-orders-sync-log', array( 'login' => $this->http_login( $editor ) ) );
		$this->assertNotSame( 200, $denied['status'] );
		wp_delete_user( $editor );
	}

	public function test_listeners_keep_their_signatures(): void {
		$seen = array();
		add_action(
			'acme_orders_order_synced',
			static function ( ...$args ) use ( &$seen ) {
				$seen = $args;
			},
			10,
			4
		);
		$logged = 0;
		$cb     = static function ( $entry ) use ( &$logged ) {
			if ( is_array( $entry ) && isset( $entry['message'], $entry['level'], $entry['time'] ) ) {
				++$logged;
			}
		};
		add_action( 'acme_orders_logged', $cb );

		$event = $this->event( 'order.created', array( 'number' => 'EU-90002' ) );
		$this->deliver_signed( $event );
		$this->run_cron();
		remove_action( 'acme_orders_logged', $cb );
		$result = $this->order_post( 'shop-eu', 'EU-90002' );

		$this->assertGreaterThan( 0, $result );
		$this->assertCount( 4, $seen );
		$this->assertSame( $result, $seen[0] );
		$this->assertSame( 'EU-90002', $seen[1]['number'] );
		$this->assertSame( $event['id'], $seen[2]['id'] );
		$this->assertSame( 'shop-eu', $seen[3] );
		$this->assertGreaterThan( 0, $logged );
	}
}
