<?php
/**
 * Changes made in one request show up in the next ones (real requests through the server).
 */

use function WPSB\Related\section_ids;
use function WPSB\Related\sections;
use function WPSB\Related\seed_id;

class HttpInvalidationTest extends WPSB\Related\HttpTestCase {

	private array $cleanup = array();

	protected function tearDown(): void {
		foreach ( $this->cleanup as $fn ) {
			$fn();
		}
		parent::tearDown();
	}

	private function lists_on( string $path ): array {
		$r = $this->http( 'GET', $path );
		$this->assertSame( 200, $r['status'] );
		return array_map( 'WPSB\Related\section_ids', sections( $r['body'] ) );
	}

	public function test_changes_through_the_rest_api_show_on_the_site(): void {
		$source    = seed_id( 129 );
		$path      = '/' . get_post_field( 'post_name', $source ) . '/';
		$old_picks = get_post_meta( $source, '_acme_related_manual', true );
		$this->cleanup[] = static fn() => update_post_meta( $source, '_acme_related_manual', $old_picks );
		$first = $this->lists_on( $path );
		$this->assertCount( 1, $first );
		$this->lists_on( '/page/2/' );

		$admin = $this->http_login( 1 );
		$pick  = seed_id( 7 );
		$r     = $this->http( 'POST', '/wp-json/wp/v2/posts/' . $source, array( 'login' => $admin, 'rest_nonce' => true, 'json' => true, 'body' => array( 'meta' => array( '_acme_related_manual' => array( $pick ) ) ) ) );
		$this->assertSame( 200, $r['status'], $r['body'] );
		$this->assertSame( $pick, $this->lists_on( $path )[0][0] );

		// A new post with the same terms, published through the REST API.
		$r = $this->http(
			'POST',
			'/wp-json/wp/v2/posts',
			array(
				'login'      => $admin,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'title'      => 'Fresh twin',
					'status'     => 'publish',
					'date_gmt'   => '2026-03-01T10:00:00',
					'categories' => wp_get_post_categories( $source ),
					'tags'       => wp_get_post_tags( $source, array( 'fields' => 'ids' ) ),
				),
			)
		);
		$this->assertSame( 201, $r['status'], $r['body'] );
		$twin            = (int) $r['json']['id'];
		$this->cleanup[] = static fn() => wp_delete_post( $twin, true );
		$this->assertSame( array( $pick, $twin ), array_slice( $this->lists_on( $path )[0], 0, 2 ) );
		$rest = $this->http( 'GET', '/wp-json/wp/v2/posts/' . $source );
		$this->assertSame( array( $pick, $twin ), array_slice( array_column( $rest['json']['acme_related'], 'id' ), 0, 2 ) );

		// Retitle it, then trash it.
		$r = $this->http( 'POST', '/wp-json/wp/v2/posts/' . $twin, array( 'login' => $admin, 'rest_nonce' => true, 'json' => true, 'body' => array( 'title' => 'Twin, retitled' ) ) );
		$this->assertSame( 200, $r['status'] );
		$page = $this->http( 'GET', $path )['body'];
		$this->assertStringContainsString( '>Twin, retitled</a>', $page );

		$r = $this->http( 'DELETE', '/wp-json/wp/v2/posts/' . $twin, array( 'login' => $admin, 'rest_nonce' => true ) );
		$this->assertSame( 200, $r['status'] );
		$this->assertNotContains( $twin, $this->lists_on( $path )[0] );
		$rest = $this->http( 'GET', '/wp-json/wp/v2/posts?per_page=5&include[]=' . $source );
		$this->assertNotContains( $twin, array_column( $rest['json'][0]['acme_related'], 'id' ) );
	}

	public function test_term_changes_through_the_rest_api_show_on_archives(): void {
		$source = seed_id( 136 );
		$other  = seed_id( 2 );
		$tags   = wp_get_post_tags( $other, array( 'fields' => 'ids' ) );
		$cats   = wp_get_post_categories( $other );
		$this->cleanup[] = static function () use ( $other, $tags, $cats ) {
			wp_set_post_terms( $other, $tags, 'post_tag' );
			wp_set_post_terms( $other, $cats, 'category' );
		};
		$path = '/' . get_post_field( 'post_name', $source ) . '/';
		$this->assertNotContains( $other, $this->lists_on( $path )[0] );

		$admin = $this->http_login( 1 );
		$r     = $this->http(
			'POST',
			'/wp-json/wp/v2/posts/' . $other,
			array(
				'login'      => $admin,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'tags'       => wp_get_post_tags( $source, array( 'fields' => 'ids' ) ),
					'categories' => wp_get_post_categories( $source ),
				),
			)
		);
		$this->assertSame( 200, $r['status'], $r['body'] );
		wp_cache_flush(); // The change was made by another process.
		$this->assertContains( $other, acme_related_get_ids( $source, 12 ) );
		$this->assertContains( $other, $this->lists_on( $path )[0] );

		// Unhook it again: the next request must not show it any more.
		wp_set_post_terms( $other, array(), 'post_tag' );
		wp_set_post_terms( $other, array( get_cat_ID( 'Sponsored' ) ), 'category' );
		$this->assertNotContains( $other, $this->lists_on( $path )[0] );
	}
}
