<?php
/**
 * Query budgets (cold object cache, no persistent object cache).
 */

use function WPSB\Related\query_summary;
use function WPSB\Related\seed_id;
use function WPSB\Related\set_settings;

class QueryCountTest extends WPSB\Related\HttpTestCase {

	private function slug( int $n ): string {
		return get_post_field( 'post_name', seed_id( $n ) );
	}

	public function test_single_post_pages(): void {
		foreach ( array( 3, 42, 88, 200 ) as $n ) {
			$run = $this->related_queries( '/' . $this->slug( $n ) . '/' );
			$this->assertCount( 1, WPSB\Related\sections( $run['on']['body'] ), 'The list must be shown under the post' );
			$this->assertLessThanOrEqual( 20, $run['delta'], "Single post ($n): the related list costs {$run['delta']} queries\n" . $run['summary'] );
		}
	}

	public function test_archive_pages(): void {
		foreach ( array( '/page/2/', '/category/guides/', '/tag/coffee/', '/page/5/' ) as $path ) {
			$run = $this->related_queries( $path );
			$this->assertGreaterThanOrEqual( 5, count( WPSB\Related\sections( $run['on']['body'] ) ), "$path must show lists under its posts" );
			$this->assertLessThanOrEqual( 25, $run['delta'], "$path: related lists cost {$run['delta']} queries\n" . $run['summary'] );
		}
	}

	public function test_archive_cost_does_not_grow_with_the_number_of_posts(): void {
		update_option( 'posts_per_page', 5 );
		$five = $this->related_queries( '/page/3/' );
		update_option( 'posts_per_page', 20 );
		$twenty = $this->related_queries( '/page/3/' );
		$this->assertGreaterThanOrEqual( 18, count( WPSB\Related\sections( $twenty['on']['body'] ) ) );
		$this->assertLessThanOrEqual( 25, $twenty['delta'], $twenty['summary'] );
		$this->assertLessThanOrEqual( $five['delta'] + 3, $twenty['delta'], "5 posts: {$five['delta']} queries, 20 posts: {$twenty['delta']}\n" . $twenty['summary'] );
	}

	public function test_cost_does_not_grow_with_the_number_of_items(): void {
		set_settings( array( 'count' => 2 ) );
		$two = $this->related_queries( '/page/4/' );
		set_settings( array( 'count' => 12 ) );
		$twelve = $this->related_queries( '/page/4/' );
		$this->assertGreaterThanOrEqual( 100, substr_count( implode( '', WPSB\Related\sections( $twelve['on']['body'] ) ), 'class="acme-related__item"' ) );
		$this->assertLessThanOrEqual( 25, $twelve['delta'], $twelve['summary'] );
		$this->assertLessThanOrEqual( $two['delta'] + 3, $twelve['delta'], "2 items: {$two['delta']} queries, 12 items: {$twelve['delta']}\n" . $twelve['summary'] );
	}

	public function test_rest_collection_with_the_field(): void {
		$cost = function ( int $per_page, int $page ) {
			wp_cache_flush();
			$without = $this->count_queries( fn() => $this->rest( 'GET', '/wp/v2/posts', array( 'per_page' => $per_page, 'page' => $page, '_fields' => 'id,title' ) ) );
			wp_cache_flush();
			$with = $this->count_queries( fn() => $this->rest( 'GET', '/wp/v2/posts', array( 'per_page' => $per_page, 'page' => $page, '_fields' => 'id,title,acme_related' ) ) );
			$data = $this->rest_data( $with['result'] );
			$this->assertCount( $per_page, $data );
			$this->assertNotEmpty( $data[0]['acme_related'] );
			return array( $with['count'] - $without['count'], query_summary( $with['queries'] ) );
		};
		list( $ten, $summary ) = $cost( 10, 2 );
		$this->assertLessThanOrEqual( 25, $ten, "REST: acme_related for 10 posts costs $ten queries\n$summary" );
		list( $fifty, $summary ) = $cost( 50, 1 );
		$this->assertLessThanOrEqual( $ten + 3, $fifty, "REST: 10 posts cost $ten queries, 50 posts $fifty\n$summary" );
	}

	public function test_related_route(): void {
		$id = seed_id( 117 );
		wp_cache_flush();
		$run = $this->count_queries( fn() => $this->rest( 'GET', '/acme-related/v1/related/' . $id, array( 'count' => 12, 'html' => true ) ) );
		$this->assertSame( 200, $run['result']->get_status() );
		$this->assertCount( 12, $run['result']->get_data()['items'] );
		$this->assertLessThanOrEqual( 20, $run['count'], query_summary( $run['queries'] ) );
	}

	public function test_blocks(): void {
		$page = get_page_by_path( 'reading-list' );
		$GLOBALS['post'] = $page;
		wp_cache_flush();
		$run = $this->count_queries( fn() => do_blocks( $page->post_content ) );
		$this->assertCount( 3, WPSB\Related\sections( $run['result'] ) );
		$this->assertLessThanOrEqual( 60, $run['count'], "Three blocks cost {$run['count']} queries\n" . query_summary( $run['queries'] ) );

		$markup = sprintf( '<!-- wp:acme/related-posts {"postId":%d,"count":12} /-->', seed_id( 64 ) );
		wp_cache_flush();
		$run = $this->count_queries( fn() => do_blocks( $markup ) );
		$this->assertCount( 1, WPSB\Related\sections( $run['result'] ) );
		$this->assertLessThanOrEqual( 20, $run['count'], "One block with 12 items costs {$run['count']} queries\n" . query_summary( $run['queries'] ) );
		unset( $GLOBALS['post'] );
	}

	public function test_template_tag_in_a_custom_loop(): void {
		// Themes call the template tag inside their own loops (e.g. a "more from the magazine" strip).
		wp_cache_flush();
		$run = $this->count_queries(
			function () {
				$q    = new WP_Query( array( 'posts_per_page' => 8, 'paged' => 7 ) );
				$html = '';
				while ( $q->have_posts() ) {
					$q->the_post();
					ob_start();
					acme_related_the_list( get_the_ID(), array( 'count' => 3 ) );
					$html .= ob_get_clean();
				}
				wp_reset_postdata();
				return $html;
			}
		);
		$this->assertCount( 8, WPSB\Related\sections( $run['result'] ) );
		$this->assertLessThanOrEqual( 30, $run['count'], "Custom loop of 8 posts: {$run['count']} queries\n" . query_summary( $run['queries'] ) );
	}
}
