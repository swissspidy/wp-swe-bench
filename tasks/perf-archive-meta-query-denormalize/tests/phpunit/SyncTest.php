<?php
/**
 * The search follows every change to listings and their meta, however it is written
 * (importers call update_post_meta() & co. directly). Rolled back after each test.
 */

use function WPSB\RealEstate\brute_force_search;
use function WPSB\RealEstate\combos;
use function WPSB\RealEstate\expected;
use function WPSB\RealEstate\id;
use function WPSB\RealEstate\slugs;

class SyncTest extends WPSB\TestCase {

	private function found( array $args ): array {
		return slugs( acme_re_search( $args + array( 'per_page' => -1 ) )['ids'] );
	}

	private function listing( array $meta, array $post = array() ): int {
		return $this->create_post(
			array_merge(
				array(
					'post_type'  => 'acme_listing',
					'post_title' => 'Test listing ' . wp_rand(),
					'meta_input' => $meta,
				),
				$post
			)
		);
	}

	private function first_where( callable $fn, array $args = array( 'status' => 'all' ) ): string {
		foreach ( $this->found( $args ) as $slug ) {
			if ( $fn( id( $slug ) ) ) {
				return $slug;
			}
		}
		$this->fail( 'No seeded listing matches' );
	}

	public function test_price_updates(): void {
		$slug = expected()['city']['slugs'][3];
		$id   = id( $slug );
		$this->assertNotContains( $slug, $this->found( array( 'min_price' => 7654000, 'max_price' => 7654321 ) ) );

		update_post_meta( $id, '_acme_price', 7654321 );
		$this->assertSame( array( $slug ), $this->found( array( 'min_price' => 7654000, 'max_price' => 7654321 ) ) );
		$this->assertSame( $slug, $this->found( array( 'sort' => 'price_desc', 'city' => 'Springfield' ) )[0] );

		update_post_meta( $id, '_acme_price', '0' );
		$this->assertNotContains( $slug, $this->found( array( 'max_price' => 5000000 ) ), 'Price on request is left out of price filters' );
		$this->assertContains( $slug, $this->found( array( 'city' => 'Springfield' ) ) );

		delete_post_meta( $id, '_acme_price' );
		$this->assertNotContains( $slug, $this->found( array( 'sort' => 'price_asc', 'city' => 'Springfield' ) ) );
		$this->assertContains( $slug, $this->found( array( 'city' => 'Springfield' ) ) );

		add_post_meta( $id, '_acme_price', '123456.00' );
		$this->assertSame( array( $slug ), $this->found( array( 'min_price' => 123456, 'max_price' => 123456, 'status' => 'all' ) ) );
	}

	public function test_bedrooms_updates(): void {
		$slug = $this->first_where( static fn( $id ) => '' === get_post_meta( $id, '_acme_bedrooms', true ) );
		$id   = id( $slug );
		$this->assertNotContains( $slug, $this->found( array( 'status' => 'all', 'sort' => 'beds_desc' ) ) );

		add_post_meta( $id, '_acme_bedrooms', 9 );
		$this->assertSame( $slug, $this->found( array( 'status' => 'all', 'sort' => 'beds_desc' ) )[0] );
		$this->assertContains( $slug, $this->found( array( 'status' => 'all', 'beds' => 9 ) ) );

		update_post_meta( $id, '_acme_bedrooms', 1 );
		$this->assertNotContains( $slug, $this->found( array( 'status' => 'all', 'beds' => 2 ) ) );

		delete_post_meta( $id, '_acme_bedrooms' );
		$this->assertNotContains( $slug, $this->found( array( 'status' => 'all', 'beds' => 1 ) ) );
		$this->assertNotContains( $slug, $this->found( array( 'status' => 'all', 'sort' => 'beds_desc' ) ) );
	}

	public function test_features_updates_in_every_format(): void {
		$slug = expected()['default']['slugs'][0];
		$id   = id( $slug );

		update_post_meta( $id, '_acme_features', array( 'elevator', 'sea-view', 'pets-allowed', 'pool-heated' ) );
		$this->assertContains( $slug, $this->found( array( 'features' => 'sea-view,elevator,pets-allowed' ) ) );
		$this->assertNotContains( $slug, $this->found( array( 'features' => 'pool' ) ), '"pool-heated" is not "pool"' );

		// A third-party importer writing the old map format with CSV spelling.
		update_post_meta( $id, '_acme_features', array( 'Pool' => 'yes', 'Garage' => 'yes' ) );
		$this->assertContains( $slug, $this->found( array( 'features' => array( 'pool', 'garage' ) ) ) );
		$this->assertNotContains( $slug, $this->found( array( 'features' => 'sea-view' ) ) );

		update_post_meta( $id, '_acme_features', '' );
		$this->assertNotContains( $slug, $this->found( array( 'features' => 'pool' ) ) );
		$this->assertContains( $slug, $this->found( array() ) );

		add_post_meta( $id, '_acme_features', array( 'garden' ), true );
		delete_post_meta( $id, '_acme_features' );
		add_post_meta( $id, '_acme_features', array( 'garden', 'fireplace' ) );
		$this->assertContains( $slug, $this->found( array( 'features' => 'garden,fireplace' ) ) );
	}

	public function test_city_and_status_updates(): void {
		$slug = expected()['city']['slugs'][0];
		$id   = id( $slug );

		update_post_meta( $id, '_acme_city', 'Ogdenville' );
		$this->assertNotContains( $slug, $this->found( array( 'city' => 'Springfield' ) ) );
		$this->assertContains( $slug, $this->found( array( 'city' => 'Ogdenville' ) ) );

		update_post_meta( $id, '_acme_status', 'sold' );
		$this->assertNotContains( $slug, $this->found( array( 'city' => 'Ogdenville' ) ) );
		$this->assertContains( $slug, $this->found( array( 'city' => 'Ogdenville', 'status' => 'sold' ) ) );

		delete_post_meta( $id, '_acme_status' );
		$this->assertNotContains( $slug, $this->found( array( 'city' => 'Ogdenville', 'status' => 'all' ) ) );
	}

	private function found_ids( array $args ): array {
		return acme_re_search( $args + array( 'per_page' => -1 ) )['ids'];
	}

	public function test_new_listings_and_post_status_changes(): void {
		$id   = $this->listing(
			array(
				'_acme_price'    => 333333,
				'_acme_bedrooms' => 4,
				'_acme_city'     => 'Capital City',
				'_acme_features' => array( 'pool', 'garden' ),
				'_acme_status'   => 'for-sale',
			),
			array( 'post_date' => '2030-01-01 10:00:00', 'post_status' => 'draft' )
		);
		$args = array( 'city' => 'Capital City', 'features' => 'pool,garden' );
		$this->assertNotContains( $id, $this->found_ids( $args ), 'Drafts are never listed' );

		wp_publish_post( $id );
		$this->assertSame( $id, $this->found_ids( $args )[0] ?? null, 'Newest first' );
		$this->assertContains( $id, $this->found_ids( array( 'min_price' => 333333, 'max_price' => 333333, 'beds' => 4 ) ) );

		wp_update_post( array( 'ID' => $id, 'post_date' => '2001-01-01 10:00:00', 'post_date_gmt' => '2001-01-01 10:00:00' ) );
		$found = $this->found_ids( $args );
		$this->assertSame( $id, end( $found ), 'Sorted by the (changed) listing date' );

		wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		$this->assertNotContains( $id, $this->found_ids( $args ) );
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
		$this->assertContains( $id, $this->found_ids( $args ) );

		wp_trash_post( $id );
		$this->assertNotContains( $id, $this->found_ids( $args ) );
		wp_untrash_post( $id );
		wp_publish_post( $id );
		$this->assertContains( $id, $this->found_ids( $args ) );

		wp_delete_post( $id, true );
		$this->assertNotContains( $id, $this->found_ids( $args ) );
		$this->assertSame( expected()['default']['total'], acme_re_search()['total'] );
	}

	public function test_listing_created_first_and_meta_written_afterwards(): void {
		// How importers work: the post is inserted, then the meta is written one key at a time.
		$id = wp_insert_post(
			array(
				'post_type'   => 'acme_listing',
				'post_status' => 'publish',
				'post_title'  => 'Imported listing',
			)
		);
		$this->assertNotContains( $id, $this->found_ids( array( 'status' => 'all' ) ), 'No status yet: not listed' );

		update_post_meta( $id, '_acme_status', 'pending' );
		update_post_meta( $id, '_acme_price', 612000 );
		update_post_meta( $id, '_acme_city', "Coeur d'Alene" );
		update_post_meta( $id, '_acme_bedrooms', 3 );
		update_post_meta( $id, '_acme_features', array( 'fireplace', 'solar-panels' ) );

		$this->assertSame( $id, $this->found_ids( array( 'city' => "Coeur d'Alene", 'features' => 'solar-panels,fireplace', 'status' => 'pending' ) )[0] ?? null );
		$this->assertContains( $id, $this->found_ids( array( 'min_price' => 600000, 'max_price' => 620000, 'beds' => 3 ) ) );
		$item = acme_re_get_listing( $id );
		$this->assertSame( array( 'fireplace', 'solar-panels' ), $item['features'] );
	}

	public function test_random_changes_stay_consistent(): void {
		mt_srand( 99 );
		$ids      = array_map( 'WPSB\RealEstate\id', array_slice( expected()['status all']['slugs'], 0, 120 ) );
		$cities   = array( 'Springfield', 'Shelbyville', 'New York', "Coeur d'Alene" );
		$features = array( 'pool', 'pool-heated', 'garage', 'garden', 'sea-view' );
		for ( $i = 0; $i < 80; $i++ ) {
			$id = $ids[ mt_rand( 0, count( $ids ) - 1 ) ];
			switch ( mt_rand( 0, 7 ) ) {
				case 0:
					update_post_meta( $id, '_acme_price', mt_rand( 0, 30 ) * 50000 );
					break;
				case 1:
					update_post_meta( $id, '_acme_bedrooms', mt_rand( 0, 6 ) );
					break;
				case 2:
					delete_post_meta( $id, '_acme_bedrooms' );
					break;
				case 3:
					update_post_meta( $id, '_acme_city', $cities[ mt_rand( 0, 3 ) ] );
					break;
				case 4:
					$pick = array_values( array_filter( $features, static fn() => mt_rand( 0, 1 ) ) );
					update_post_meta( $id, '_acme_features', mt_rand( 0, 1 ) ? $pick : array_fill_keys( array_map( 'ucfirst', $pick ), 'yes' ) );
					break;
				case 5:
					update_post_meta( $id, '_acme_status', array( 'for-sale', 'pending', 'sold' )[ mt_rand( 0, 2 ) ] );
					break;
				case 6:
					wp_update_post( array( 'ID' => $id, 'post_status' => mt_rand( 0, 1 ) ? 'draft' : 'publish' ) );
					break;
				case 7:
					update_metadata( 'post', $id, '_acme_price', (string) ( mt_rand( 1, 40 ) * 25000 ) . '.00' );
					break;
			}
		}
		foreach ( array( 'default', 'two features', 'combo springfield', 'legacy decimal prices', 'beds desc all', 'combo everything', 'city alias', 'overlapping features', 'price asc', 'status sold' ) as $name ) {
			$args = combos()[ $name ]['args'];
			$this->assertSame( brute_force_search( $args ), $this->found( $args ), "After random changes: $name" );
		}
	}
}
