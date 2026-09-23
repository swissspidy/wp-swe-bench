<?php
/**
 * Query budget for a catalogue page (in-process, cold object cache, read-only).
 */

class PerformanceTest extends AcmeLibraryCase {

	const BUDGET = 30;

	private function measure( string $route, array $query, int $user ): array {
		// Warm up the REST server itself (route registration etc.), then start cold.
		$this->rest( 'GET', '/wp/v2/types' );
		wp_set_current_user( $user );
		wp_cache_flush();
		$result = $this->count_queries(
			function () use ( $route, $query ) {
				$response = $this->rest( 'GET', $route, $query );
				return array( $response, $this->rest_data( $response, true ) );
			}
		);
		wp_set_current_user( 0 );
		return $result;
	}

	public function test_books_page_with_embeds(): void {
		foreach ( array( 'anonymous' => 0, 'editor' => $this->user( 'eddie' ) ) as $who => $user ) {
			$result = $this->measure( '/wp/v2/books', array( 'per_page' => 100 ), $user );
			list( $response, $data ) = $result['result'];
			$this->assertSame( 200, $response->get_status() );
			$this->assertCount( 100, $data );
			$embedded = 0;
			foreach ( $data as $item ) {
				$this->assertSame( count( $item['authors'] ), count( self::embedded( $item, self::REL_AUTHOR ) ) );
				$embedded += count( $item['authors'] );
			}
			$this->assertGreaterThan( 120, $embedded );
			$this->assertLessThanOrEqual( self::BUDGET, $result['count'], "$who: {$result['count']} queries:\n" . implode( "\n", array_slice( $result['queries'], 0, 80 ) ) );
		}
	}

	public function test_authors_page_with_embeds(): void {
		$result = $this->measure( '/wp/v2/authors', array( 'per_page' => 100 ), 0 );
		list( $response, $data ) = $result['result'];
		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThanOrEqual( 35, count( $data ) );
		$embedded = 0;
		foreach ( $data as $item ) {
			$this->assertSame( count( $item['books'] ), count( self::embedded( $item, self::REL_BOOK ) ) );
			$embedded += count( $item['books'] );
		}
		$this->assertGreaterThan( 100, $embedded );
		$this->assertLessThanOrEqual( self::BUDGET, $result['count'], "{$result['count']} queries:\n" . implode( "\n", array_slice( $result['queries'], 0, 80 ) ) );
	}
}
