<?php
/**
 * Same results and ordering as 1.6.2 for many filter combinations (PHP API and REST).
 */

use function WPSB\RealEstate\combos;
use function WPSB\RealEstate\expected;
use function WPSB\RealEstate\id;
use function WPSB\RealEstate\slugs;

class ParityTest extends WPSB\TestCase {

	public static function combo_names(): array {
		$out = array();
		foreach ( array_keys( combos() ) as $name ) {
			$out[ $name ] = array( $name );
		}
		return $out;
	}

	#[PHPUnit\Framework\Attributes\DataProvider( 'combo_names' )]
	public function test_php_api( string $name ): void {
		$combo    = combos()[ $name ];
		$expected = expected()[ $name ];

		$all = acme_re_search( $combo['args'] + array( 'per_page' => -1 ) );
		$this->assertSame( $expected['slugs'], slugs( $all['ids'] ) );
		$this->assertSame( $expected['total'], $all['total'] );

		$page = acme_re_search( $combo['args'] + array( 'per_page' => 5, 'page' => 2 ) );
		$this->assertSame( array_slice( $expected['slugs'], 5, 5 ), slugs( $page['ids'] ) );
		if ( $expected['total'] > 5 ) {
			$this->assertSame( $expected['total'], $page['total'] );
			$this->assertSame( (int) ceil( $expected['total'] / 5 ), $page['pages'] );
		}
	}

	#[PHPUnit\Framework\Attributes\DataProvider( 'combo_names' )]
	public function test_rest( string $name ): void {
		$combo = combos()[ $name ];
		if ( isset( $combo['rest'] ) && false === $combo['rest'] ) {
			$res = $this->rest( 'GET', '/acme-re/v1/listings', $combo['args'] );
			$this->assertSame( 400, $res->get_status(), 'Invalid enum values are rejected by the REST schema' );
			return;
		}
		$expected = expected()[ $name ];
		$args     = $combo['args'];
		if ( isset( $args['features'] ) && is_array( $args['features'] ) && count( $args['features'] ) > 1 ) {
			$args['features'] = implode( ',', $args['features'] ); // The apps send a comma separated list.
		}

		$per_page = 7;
		$pages    = max( 1, (int) ceil( $expected['total'] / $per_page ) );
		foreach ( array_unique( array( 1, min( 2, $pages ), $pages ) ) as $page ) {
			$res = $this->rest( 'GET', '/acme-re/v1/listings', $args + array( 'per_page' => $per_page, 'page' => $page ) );
			$this->assertSame( 200, $res->get_status() );
			$data = $res->get_data();
			$this->assertSame( array_slice( $expected['slugs'], ( $page - 1 ) * $per_page, $per_page ), array_column( $data['items'], 'slug' ), "page $page" );
			$this->assertSame( $expected['total'], $data['total'] );
			$this->assertSame( (int) ceil( $expected['total'] / $per_page ), $data['pages'] );
			$this->assertSame( (string) $expected['total'], (string) $res->get_headers()['X-WP-Total'] );
		}
	}

	public function test_rest_item_shape(): void {
		$res  = $this->rest( 'GET', '/acme-re/v1/listings', array( 'features' => 'air-conditioning', 'sort' => 'price_desc', 'per_page' => 48 ) );
		$data = $res->get_data();
		$this->assertNotEmpty( $data['items'] );
		foreach ( $data['items'] as $item ) {
			$id = id( $item['slug'] );
			$this->assertSame( $id, $item['id'] );
			$this->assertSame( (int) get_post_meta( $id, '_acme_price', true ), $item['price'] );
			$this->assertSame( get_post_meta( $id, '_acme_city', true ), $item['city'] );
			$this->assertContains( 'air-conditioning', $item['features'] );
			$this->assertSame( $item['features'], array_values( array_unique( $item['features'] ) ) );
			$this->assertArrayHasKey( 'sqm', $item, 'acme_re_listing_data filter still applies' );
			$this->assertSame( get_permalink( $id ), $item['link'] );
		}
		$this->assertSame( 'Price on request', acme_re_get_listing( id( expected()['price asc']['slugs'][0] ) )['price_label'] );
	}
}
