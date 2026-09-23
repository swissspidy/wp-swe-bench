<?php
/**
 * The output must stay exactly what Acme Related 2.3.1 produced (fixtures recorded from it).
 */

use function WPSB\Related\fixture;
use function WPSB\Related\list_html;
use function WPSB\Related\seed_id;

class ParityTest extends WPSB\TestCase {

	public static function sources(): array {
		$out = array();
		foreach ( array_keys( fixture( 'parity' )['sources'] ) as $n ) {
			$out[ "post ($n)" ] = array( (int) $n );
		}
		return $out;
	}

	private function source( int $n ): array {
		$s = fixture( 'parity' )['sources'][ (string) $n ];
		$this->assertSame( $s['id'], seed_id( $n ), 'Seeded data changed?' );
		return $s;
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'sources' )]
	public function test_related_ids_are_unchanged( int $n ): void {
		$s = $this->source( $n );
		$this->assertSame( $s['ids'], acme_related_get_ids( $s['id'] ), 'default count' );
		$this->assertSame( $s['ids_12'], acme_related_get_ids( $s['id'], 12 ), 'count 12' );
		$this->assertSame( $s['ids_1'], acme_related_get_ids( $s['id'], 1 ), 'count 1' );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'sources' )]
	public function test_list_html_is_unchanged( int $n ): void {
		$s = $this->source( $n );
		$this->assertSame( $s['html'], list_html( $s['id'] ) );
		$this->assertSame( $s['html_6'], list_html( $s['id'], array( 'count' => 6, 'heading' => 'Six more' ) ) );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'sources' )]
	public function test_rest_field_is_unchanged( int $n ): void {
		$s    = $this->source( $n );
		$data = $this->rest_data( $this->rest( 'GET', '/wp/v2/posts/' . $s['id'] ) );
		$this->assertArrayHasKey( 'acme_related', $data );
		$this->assertSame( $s['rest_field'], $data['acme_related'] );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'sources' )]
	public function test_block_output_is_unchanged( int $n ): void {
		$s = $this->source( $n );
		$this->assertSame(
			$s['block'],
			do_blocks( sprintf( '<!-- wp:acme/related-posts {"postId":%d,"count":5,"heading":"Block heading","className":"is-style-compact"} /-->', $s['id'] ) )
		);
	}

	public function test_collection_field_equals_single_post_field(): void {
		foreach ( array( 1, 2, 5 ) as $page ) {
			wp_cache_flush();
			$collection = $this->rest_data( $this->rest( 'GET', '/wp/v2/posts', array( 'per_page' => 10, 'page' => $page ) ) );
			$this->assertCount( 10, $collection );
			foreach ( $collection as $post ) {
				wp_cache_flush();
				$single = $this->rest_data( $this->rest( 'GET', '/wp/v2/posts/' . $post['id'] ) );
				$this->assertSame( $single['acme_related'], $post['acme_related'], "Post {$post['id']} (page $page): collection and single responses differ" );
			}
		}
	}

	public function test_mixed_counts_in_one_request(): void {
		// Lists of different sizes for the same posts in one request must be consistent.
		$fx = fixture( 'parity' )['sources'];
		foreach ( array( 3, 10, 42, 88 ) as $n ) {
			$this->assertSame( $fx[ (string) $n ]['ids'], acme_related_get_ids( $fx[ (string) $n ]['id'] ) );
		}
		foreach ( array( 3, 10, 42, 88 ) as $n ) {
			$this->assertSame( $fx[ (string) $n ]['ids_12'], acme_related_get_ids( $fx[ (string) $n ]['id'], 12 ) );
			$this->assertSame( $fx[ (string) $n ]['html'], list_html( $fx[ (string) $n ]['id'] ) );
			$this->assertSame( $fx[ (string) $n ]['ids_1'], acme_related_get_ids( $fx[ (string) $n ]['id'], 1 ) );
		}
	}

	public function test_related_route_is_unchanged(): void {
		$fx = fixture( 'parity' )['sources'];
		foreach ( array( 3, 23, 117 ) as $n ) {
			$s    = $fx[ (string) $n ];
			$data = $this->rest_data( $this->rest( 'GET', '/acme-related/v1/related/' . $s['id'], array( 'count' => 12 ) ) );
			$this->assertSame( $s['ids_12'], array_column( $data['items'], 'id' ) );
			$data = $this->rest_data( $this->rest( 'GET', '/acme-related/v1/related/' . $s['id'], array( 'html' => true ) ) );
			$this->assertSame( $s['rest_field'], $data['items'] );
			$this->assertSame( $s['html'], $data['html'] );
		}
		$draft = (int) $GLOBALS['wpdb']->get_var( "SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_status = 'draft' AND post_type = 'post' LIMIT 1" );
		$this->assertSame( 404, $this->rest( 'GET', '/acme-related/v1/related/' . $draft )->get_status() );
	}
}
