<?php
/**
 * The DBA's requirements: no post meta (and no pattern matching) to find listings, only the
 * requested page's data loaded, a constant number of queries per search.
 */

use function WPSB\RealEstate\combos;
use function WPSB\RealEstate\expected;
use function WPSB\RealEstate\postmeta_queries;

class QueryShapeTest extends WPSB\TestCase {

	private const CASES = array( 'default', 'two features', 'combo everything', 'combo springfield', 'legacy decimal prices', 'beds desc all', 'three features', 'city alias sorted' );

	public static function cases(): array {
		$out = array();
		foreach ( self::CASES as $name ) {
			$out[ $name ] = array( $name );
		}
		return $out;
	}

	private function search_rest( array $args, int $per_page ): array {
		$this->rest( 'GET', '/acme-re/v1/listings', array( 'per_page' => 1 ) ); // warm-up (route registration etc.).
		wp_cache_flush();
		return $this->count_queries(
			fn() => $this->rest( 'GET', '/acme-re/v1/listings', $args + array( 'per_page' => $per_page ) )
		);
	}

	private function assert_query_shape( array $q, int $per_page, string $label ): void {
		$all = implode( "\n", $q['queries'] );
		foreach ( $q['queries'] as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(LIKE|REGEXP|RLIKE|GLOB)\b/i', $sql, "$label: no pattern matching:\n$sql" );
		}
		foreach ( postmeta_queries( $q['queries'] ) as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\bJOIN\b/i', $sql, "$label: post meta must not be joined to find listings:\n$sql" );
			$this->assertMatchesRegularExpression( '/WHERE\s+post_id\s+IN\s*\(([\d,\s]+)\)/i', $sql, "$label: post meta may only be loaded for the listings being shown:\n$sql" );
			preg_match( '/WHERE\s+post_id\s+IN\s*\(([\d,\s]+)\)/i', $sql, $m );
			$ids = array_filter( array_map( 'trim', explode( ',', $m[1] ) ) );
			$this->assertLessThanOrEqual( $per_page, count( $ids ), "$label: loaded meta of more listings than shown:\n$sql" );
		}
		$this->assertLessThanOrEqual( 12, $q['count'], "$label: too many queries:\n$all" );
	}

	#[PHPUnit\Framework\Attributes\DataProvider( 'cases' )]
	public function test_rest_search_query_shape( string $name ): void {
		$args = combos()[ $name ]['args'];
		if ( isset( $args['features'] ) && is_array( $args['features'] ) ) {
			$args['features'] = implode( ',', $args['features'] );
		}
		$small = $this->search_rest( $args, 3 );
		$large = $this->search_rest( $args, 24 );

		$this->assertSame( array_slice( expected()[ $name ]['slugs'], 0, 24 ), array_column( $large['result']->get_data()['items'], 'slug' ) );
		$this->assert_query_shape( $small, 3, "$name per_page=3" );
		$this->assert_query_shape( $large, 24, "$name per_page=24" );
		$this->assertLessThanOrEqual( $small['count'] + 1, $large['count'], "$name: the number of queries must not grow with the page size:\n" . implode( "\n", $large['queries'] ) );
	}

	public function test_php_api_query_shape(): void {
		wp_cache_flush();
		$q = $this->count_queries( static fn() => acme_re_search( array( 'features' => array( 'garden', 'garage' ), 'city' => 'Springfield', 'beds' => 2, 'sort' => 'price_asc', 'per_page' => 10, 'page' => 2 ) ) );
		foreach ( $q['queries'] as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(LIKE|REGEXP)\b/i', $sql );
		}
		foreach ( postmeta_queries( $q['queries'] ) as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\bJOIN\b|meta_value\s*(=|<|>|IN|LIKE|BETWEEN)|meta_key\s*=/i', $sql, "Finding listings must not query post meta:\n$sql" );
		}
		$this->assertLessThanOrEqual( 6, $q['count'], implode( "\n", $q['queries'] ) );
	}

	public function test_export_query_shape(): void {
		wp_cache_flush();
		$q = $this->count_queries( static fn() => acme_re_search( array( 'status' => 'all', 'features' => 'pool', 'sort' => 'beds_desc', 'per_page' => -1 ) ) );
		foreach ( $q['queries'] as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\b(LIKE|REGEXP)\b/i', $sql );
		}
		foreach ( postmeta_queries( $q['queries'] ) as $sql ) {
			$this->assertDoesNotMatchRegularExpression( '/\bJOIN\b|meta_value\s*(=|<|>|IN|LIKE|BETWEEN)|meta_key\s*=/i', $sql, "Finding listings must not query post meta:\n$sql" );
		}
		$this->assertCount( expected()['combo pool beds']['total'], $q['result']['ids'] );
	}
}
