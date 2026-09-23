<?php
/**
 * After a change, one request regenerates; concurrent requests get the previous numbers.
 */

use function WPSB\Stats\expected;
use function WPSB\Stats\new_request;
use function WPSB\Stats\numbers;
use function WPSB\Stats\uid;

class StampedeTest extends WPSB\Stats\HttpStatsTestCase {

	/** @var array<int, bool> */
	private array $done = array();

	private function handle( int $user_id, array $query = array() ) {
		$login = $this->http_login( $user_id );
		$ch    = curl_init( rtrim( WP_HOME, '/' ) . '/wp-json/acme-stats/v1/summary' . ( $query ? '?' . http_build_query( $query ) : '' ) );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => array( 'Cookie: ' . $login['cookie'], 'X-WP-Nonce: ' . $login['rest_nonce'] ),
				CURLOPT_TIMEOUT        => 90,
				CURLOPT_PROXY          => '',
			)
		);
		return $ch;
	}

	private function pump( $mh, callable $until, float $timeout ): bool {
		$deadline = microtime( true ) + $timeout;
		do {
			curl_multi_exec( $mh, $running );
			while ( $info = curl_multi_info_read( $mh ) ) {
				$this->done[ spl_object_id( $info['handle'] ) ] = true;
			}
			if ( $until() ) {
				return true;
			}
			curl_multi_select( $mh, 0.1 );
		} while ( microtime( true ) < $deadline );
		return false;
	}

	private function is_done( $ch ): bool {
		return ! empty( $this->done[ spl_object_id( $ch ) ] );
	}

	private function json( $ch ): array {
		$this->assertSame( 200, curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ), (string) curl_multi_getcontent( $ch ) );
		$data = json_decode( (string) curl_multi_getcontent( $ch ), true );
		$this->assertIsArray( $data );
		return $data;
	}

	private function run_stampede( int $viewer, int $scope_author, string $scope_key, callable $change ): void {
		$before = $this->http_summary( $viewer );
		$this->assertArrayHasKey( 'stale', $before, 'The REST response must say whether the numbers are stale' );
		$this->assertFalse( $before['stale'] );
		$this->assertSame( expected( $scope_author ), numbers( $before ) );

		$change();
		new_request();
		$fresh = expected( $scope_author );
		$this->assertNotSame( numbers( $before ), $fresh, 'The change must affect the numbers' );

		$this->reset_computes();
		touch( self::PROBE . '/block' );
		$mh = curl_multi_init();
		$h1 = $this->handle( $viewer );
		curl_multi_add_handle( $mh, $h1 );
		$this->assertTrue( $this->pump( $mh, fn() => is_file( self::PROBE . '/started' ), 30 ), 'The first request after the change must regenerate the numbers' );

		$h2 = $this->handle( $viewer );
		$h3 = $this->handle( $viewer );
		curl_multi_add_handle( $mh, $h2 );
		curl_multi_add_handle( $mh, $h3 );
		$served = $this->pump( $mh, fn() => $this->is_done( $h2 ) && $this->is_done( $h3 ), 25 );
		$this->assertFalse( $this->is_done( $h1 ), 'The regenerating request finished early' );
		touch( self::PROBE . '/release' );
		$this->assertTrue( $this->pump( $mh, fn() => $this->is_done( $h1 ) && $this->is_done( $h2 ) && $this->is_done( $h3 ), 60 ) );
		$this->assertTrue( $served, 'Requests made while the numbers were being regenerated must not wait for the regeneration' );

		foreach ( array( $h2, $h3 ) as $ch ) {
			$data = $this->json( $ch );
			$this->assertTrue( $data['stale'], 'Numbers served during a regeneration must be flagged as stale' );
			$this->assertSame( numbers( $before ), numbers( $data ), 'Requests during a regeneration must get the previous numbers' );
			$this->assertSame( $scope_key, $data['scope'] );
		}
		$first = $this->json( $h1 );
		$this->assertFalse( $first['stale'] );
		$this->assertSame( $fresh, numbers( $first ) );
		foreach ( array( $h1, $h2, $h3 ) as $ch ) {
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}
		curl_multi_close( $mh );
		$this->assertSame( array( $scope_key ), $this->computes(), 'Exactly one regeneration may run' );

		@unlink( self::PROBE . '/block' );
		$after = $this->http_summary( $viewer );
		$this->assertFalse( $after['stale'] );
		$this->assertSame( $fresh, numbers( $after ) );
		$this->assertSame( array( $scope_key ), $this->computes() );
	}

	public function test_site_scope_after_a_new_post(): void {
		$this->run_stampede(
			uid( 'eva' ),
			0,
			'site',
			function () {
				$id              = $this->create_post( array( 'post_author' => uid( 'dmitri' ), 'post_content' => 'Council approves the new budget after a long debate.' ) );
				$this->cleanup[] = static fn() => wp_delete_post( $id, true );
			}
		);
	}

	public function test_author_scope_after_a_comment(): void {
		$bruno = uid( 'bruno' );
		$this->run_stampede(
			$bruno,
			$bruno,
			'author:' . $bruno,
			function () use ( $bruno ) {
				global $wpdb;
				$post            = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_author = %d ORDER BY ID DESC LIMIT 1", $bruno ) );
				$id              = wp_insert_comment( array( 'comment_post_ID' => $post, 'comment_content' => 'Spam spam', 'comment_approved' => 'spam' ) );
				$this->cleanup[] = static fn() => wp_delete_comment( $id, true );
			}
		);
	}

	public function test_no_previous_numbers_are_computed_right_away(): void {
		$out = $this->wp_cli( 'acme-stats flush' );
		$this->assertSame( 0, $out['exit'], $out['stderr'] . $out['stdout'] );
		$this->reset_computes();
		$data = $this->http_summary( uid( 'chiara' ) );
		$this->assertFalse( $data['stale'] );
		new_request();
		$this->assertSame( expected( uid( 'chiara' ) ), numbers( $data ) );
		$this->assertSame( array( 'author:' . uid( 'chiara' ) ), $this->computes() );
	}
}
