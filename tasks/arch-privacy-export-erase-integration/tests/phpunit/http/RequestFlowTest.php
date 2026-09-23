<?php
/**
 * The real Tools → Export/Erase Personal Data flow over HTTP (admin-ajax, as the screens do it),
 * including core's export file.
 */

use function WPSB\Loyalty\ledger_rows;
use function WPSB\Loyalty\order_id;
use function WPSB\Loyalty\order_state;
use function WPSB\Loyalty\subscriber;
use function WPSB\Loyalty\user_id;

class RequestFlowTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function create_request( string $email, string $action ): int {
		$id = wp_create_user_request( $email, $action, array(), 'confirmed' );
		$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : 'request not created' );
		return $id;
	}

	private function ajax( array $login, array $body ): array {
		$res = $this->http( 'POST', '/wp-admin/admin-ajax.php', array( 'login' => $login, 'body' => $body ) );
		$this->assertSame( 200, $res['status'], 'admin-ajax: ' . substr( $res['body'], 0, 500 ) );
		$this->assertIsArray( $res['json'], 'admin-ajax did not return JSON: ' . substr( $res['body'], 0, 500 ) );
		$this->assertTrue( $res['json']['success'] ?? false, 'admin-ajax error: ' . substr( $res['body'], 0, 1000 ) );
		return $res['json']['data'];
	}

	public function test_download_personal_data_for_a_member(): void {
		$admin   = $this->create_user( 'administrator' );
		$login   = $this->http_login( $admin );
		$request = $this->create_request( 'jane.doe@example.com', 'export_personal_data' );
		$nonce   = $this->nonce_for( $admin, 'wp-privacy-export-personal-data-' . $request, $login['logged_in'] );
		$count   = count( apply_filters( 'wp_privacy_personal_data_exporters', array() ) );

		$groups = array();
		$last   = null;
		for ( $exporter = 1; $exporter <= $count; $exporter++ ) {
			$page = 1;
			do {
				$data = $this->ajax(
					$login,
					array(
						'action'      => 'wp-privacy-export-personal-data',
						'exporter'    => $exporter,
						'id'          => $request,
						'page'        => $page,
						'security'    => $nonce,
						'sendAsEmail' => 'false',
					)
				);
				foreach ( $data['data'] as $item ) {
					$groups[ $item['group_id'] ][] = $item['item_id'];
				}
				$this->assertLessThanOrEqual( 100, count( $data['data'] ) );
				$last = $data;
				++$page;
				$this->assertLessThan( 100, $page );
			} while ( empty( $data['done'] ) );
		}

		$this->assertCount( 1, $groups['acme-loyalty-membership'] ?? array() );
		$this->assertCount( 250, array_unique( $groups['acme-loyalty-points'] ?? array() ) );
		$this->assertCount( 250, $groups['acme-loyalty-points'] ?? array() );
		$this->assertCount( 1, $groups['acme-loyalty-newsletter'] ?? array() );
		$this->assertCount( 4, $groups['acme-loyalty-orders'] ?? array() );

		// Core built the downloadable file from it.
		$this->assertNotEmpty( $last['url'] ?? '', 'No export file URL returned' );
		$file = WP_CONTENT_DIR . '/uploads/wp-personal-data-exports/' . basename( $last['url'] );
		$this->assertFileExists( $file );
		$zip = new ZipArchive();
		$this->assertTrue( true === $zip->open( $file ) );
		$html = (string) $zip->getFromName( 'index.html' );
		$zip->close();
		$this->assertStringContainsString( 'Loyalty points history', $html );
		$this->assertStringContainsString( 'Loyalty membership', $html );
		$this->assertStringContainsString( 'Leave at the back door', $html );
		$this->assertStringContainsString( 'Jane.Doe@Example.com', $html );
	}

	public function test_erase_personal_data_for_a_guest_and_a_member(): void {
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$count = count( apply_filters( 'wp_privacy_personal_data_erasers', array() ) );
		$jane  = user_id( 'jane' );
		$ids   = array_keys( ledger_rows( 'user_id = ' . $jane ) );

		foreach ( array( 'guest.gina@example.net', 'jane.doe@example.com' ) as $email ) {
			$request  = $this->create_request( $email, 'remove_personal_data' );
			$nonce    = $this->nonce_for( $admin, 'wp-privacy-erase-personal-data-' . $request, $login['logged_in'] );
			$messages = array();
			for ( $eraser = 1; $eraser <= $count; $eraser++ ) {
				$page = 1;
				do {
					$data     = $this->ajax(
						$login,
						array(
							'action'   => 'wp-privacy-erase-personal-data',
							'eraser'   => $eraser,
							'id'       => $request,
							'page'     => $page,
							'security' => $nonce,
						)
					);
					$messages = array_merge( $messages, $data['messages'] );
					++$page;
					$this->assertLessThan( 100, $page );
				} while ( empty( $data['done'] ) );
			}
			wp_cache_flush();
			$this->assertSame( 'request-completed', get_post_status( $request ) );
			if ( 'guest.gina@example.net' === $email ) {
				$this->assertContains( 'Order AC-1021 is still being processed; it was not changed.', $messages );
			} else {
				$this->assertContains( 'Order AC-1003 is still being processed; it was not changed.', $messages );
				$this->assertContains( 'Points history entries were anonymized and kept for accounting.', $messages );
			}
		}

		wp_cache_flush();
		$this->assertNull( subscriber( 'Guest.Gina@Example.NET' ) );
		$this->assertNull( subscriber( 'Jane.Doe@Example.com' ) );
		$this->assertSame( 'deleted@site.invalid', order_state( order_id( 'AC-1020' ) )['email'] );
		$this->assertSame( 'deleted@site.invalid', order_state( order_id( 'AC-0950' ) )['email'] );
		$rows = ledger_rows( 'id IN (' . implode( ',', $ids ) . ')' );
		$this->assertCount( 250, $rows );
		$this->assertSame( array( '0' ), array_values( array_unique( array_map( static fn( $r ) => (string) $r['user_id'], $rows ) ) ) );
		$this->assertNotEmpty( subscriber( 'mary-jane.doe@example.com' ) );
	}

	public function test_editors_cannot_run_the_tools(): void {
		$editor  = user_id( 'barista' );
		$login   = $this->http_login( $editor );
		$request = $this->create_request( 'loyal.fan@example.com', 'remove_personal_data' );
		$nonce   = $this->nonce_for( $editor, 'wp-privacy-erase-personal-data-' . $request, $login['logged_in'] );
		$res     = $this->http(
			'POST',
			'/wp-admin/admin-ajax.php',
			array(
				'login' => $login,
				'body'  => array(
					'action'   => 'wp-privacy-erase-personal-data',
					'eraser'   => 1,
					'id'       => $request,
					'page'     => 1,
					'security' => $nonce,
				),
			)
		);
		$this->assertFalse( $res['json']['success'] ?? true );
		wp_cache_flush();
		$this->assertNotNull( subscriber( 'loyal.fan@example.com' ) );
	}
}
