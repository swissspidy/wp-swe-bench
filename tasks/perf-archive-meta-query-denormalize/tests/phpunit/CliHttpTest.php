<?php
/**
 * WP-CLI export/import/reindex, `wp post meta`, the search page and the edit screen
 * (committed data, real processes/HTTP; everything is restored afterwards).
 */

use function WPSB\RealEstate\combos;
use function WPSB\RealEstate\expected;
use function WPSB\RealEstate\id;
use function WPSB\RealEstate\slugs;

class CliHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var array<int, array<string, mixed>> post ID => meta to restore */
	private array $restore = array();

	/** @var int[] posts to delete */
	private array $delete = array();

	protected function tearDown(): void {
		wp_cache_flush();
		foreach ( $this->restore as $id => $meta ) {
			foreach ( $meta as $key => $value ) {
				if ( null === $value ) {
					delete_post_meta( $id, $key );
				} else {
					update_post_meta( $id, $key, $value );
				}
			}
		}
		foreach ( $this->delete as $id ) {
			wp_delete_post( $id, true );
		}
		$this->restore = array();
		$this->delete  = array();
		parent::tearDown();
	}

	private function remember( int $id, array $keys ): void {
		foreach ( $keys as $key ) {
			$this->restore[ $id ][ $key ] = metadata_exists( 'post', $id, $key ) ? get_post_meta( $id, $key, true ) : null;
		}
	}

	private function found( array $args ): array {
		wp_cache_flush();
		return slugs( acme_re_search( $args + array( 'per_page' => -1 ) )['ids'] );
	}

	private function export( string $flags ): array {
		$res = $this->wp_cli( 'acme-listings export ' . $flags );
		$this->assertSame( 0, $res['exit'], $res['stderr'] );
		return $res;
	}

	public function test_cli_export(): void {
		$cases = array(
			'combo springfield'  => '--city=Springfield --beds=2 --features=garage --sort=price_asc --max-price=900000',
			'features as text'   => '--features=garden,fireplace,balcony --status=all',
			'bogus sort'         => '--sort=cheapest --city=Shelbyville',
			'city alias sorted'  => '--city=SF --sort=price_desc',
			'status sold'        => '--status=sold',
			'price range as text' => "--min-price='\$250,000' --max-price='\$499,000'",
		);
		foreach ( $cases as $name => $flags ) {
			$res  = $this->export( $flags );
			$rows = array_map( 'str_getcsv', array_values( array_filter( explode( "\n", trim( $res['stdout'] ) ) ) ) );
			$this->assertSame( array( 'id', 'slug', 'title', 'price', 'bedrooms', 'city', 'features', 'status' ), $rows[0], $name );
			$this->assertSame( expected()[ $name ]['slugs'], array_column( array_slice( $rows, 1 ), 1 ), $name );
		}

		$res  = $this->export( '--features=air-conditioning --format=json' );
		$json = json_decode( $res['stdout'], true );
		$this->assertSame( expected()['legacy capitalized']['slugs'], array_column( $json, 'slug' ) );
		foreach ( $json as $row ) {
			$this->assertContains( 'air-conditioning', $row['features'] );
		}
	}

	public function test_cli_import_updates_the_search(): void {
		$existing = id( 'listing-0050' );
		$this->remember( $existing, array( '_acme_price', '_acme_bedrooms', '_acme_bathrooms', '_acme_sqft', '_acme_city', '_acme_features', '_acme_status' ) );
		$title = get_the_title( $existing );

		$csv = tempnam( sys_get_temp_dir(), 'wpsb' ) . '.csv';
		file_put_contents(
			$csv,
			"ref,title,price,bedrooms,bathrooms,sqft,city,features,status\n" .
			"WPSB-1,Imported villa,\"1,234,000\",5,3,2600,North Haverbrook,pool|sea-view|garage,for-sale\n" .
			"WPSB-2,Imported lot,95000,,0,0,North Haverbrook,,for-sale\n" .
			"MLS-100050,Renovated listing,777000,2,1,900,Ogdenville,fireplace|elevator,pending\n"
		);
		$res = $this->wp_cli( 'acme-listings import ' . escapeshellarg( $csv ) );
		unlink( $csv );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );

		global $wpdb;
		$ref          = static fn( $r ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_acme_ref' AND meta_value = %s", $r ) );
		$this->delete = array_filter( array( $ref( 'WPSB-1' ), $ref( 'WPSB-2' ) ) );
		$this->assertCount( 2, $this->delete );

		$villa = slugs( array( $ref( 'WPSB-1' ) ) )[0];
		$lot   = slugs( array( $ref( 'WPSB-2' ) ) )[0];
		wp_update_post( array( 'ID' => $existing, 'post_title' => $title ) );

		$this->assertSame( array( $villa ), $this->found( array( 'city' => 'North Haverbrook', 'features' => 'pool,sea-view,garage', 'min_price' => 1234000, 'max_price' => 1234000, 'beds' => 5 ) ) );
		$this->assertContains( $lot, $this->found( array( 'city' => 'North Haverbrook', 'max_price' => 100000 ) ) );
		$this->assertNotContains( $lot, $this->found( array( 'city' => 'North Haverbrook', 'sort' => 'beds_desc' ) ) );
		$this->assertContains( 'listing-0050', $this->found( array( 'city' => 'Ogdenville', 'status' => 'pending', 'features' => 'elevator,fireplace', 'min_price' => 777000, 'max_price' => 777000 ) ) );
	}

	public function test_wp_cli_meta_commands_update_the_search(): void {
		$slug = expected()['city']['slugs'][5];
		$id   = id( $slug );
		$this->remember( $id, array( '_acme_city', '_acme_features' ) );

		$this->assertSame( 0, $this->wp_cli( "post meta update $id _acme_city 'North Haverbrook'" )['exit'] );
		$this->assertNotContains( $slug, $this->found( array( 'city' => 'Springfield' ) ) );
		$this->assertContains( $slug, $this->found( array( 'city' => 'North Haverbrook' ) ) );

		$this->assertSame( 0, $this->wp_cli( "post meta update $id _acme_features '[\"solar-panels\",\"pets-allowed\"]' --format=json" )['exit'] );
		$this->assertContains( $slug, $this->found( array( 'features' => 'pets-allowed,solar-panels' ) ) );

		$this->assertSame( 0, $this->wp_cli( "post meta delete $id _acme_features" )['exit'] );
		$this->assertNotContains( $slug, $this->found( array( 'features' => 'pets-allowed' ) ) );
	}

	public function test_reindex_rebuilds_from_meta(): void {
		global $wpdb;
		$slug = expected()['combo sold ny']['slugs'][0];
		$id   = id( $slug );
		$this->remember( $id, array( '_acme_status' ) );

		// Changed behind WordPress' back (a DBA fixing data with SQL).
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => 'for-sale' ), array( 'post_id' => $id, 'meta_key' => '_acme_status' ) );
		$res = $this->wp_cli( 'acme-listings reindex' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		$this->assertNotContains( $slug, $this->found( array( 'status' => 'sold', 'city' => 'New York' ) ) );
		$this->assertContains( $slug, $this->found( array( 'status' => 'for-sale', 'city' => 'New York' ) ) );

		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => 'sold' ), array( 'post_id' => $id, 'meta_key' => '_acme_status' ) );
		$this->assertSame( 0, $this->wp_cli( 'acme-listings reindex' )['exit'] );
		unset( $this->restore[ $id ] );
		foreach ( array( 'combo sold ny', 'default', 'combo everything', 'overlapping features' ) as $name ) {
			$this->assertSame( expected()[ $name ]['slugs'], $this->found( combos()[ $name ]['args'] ), $name );
		}
	}

	private function page( string $query ): array {
		$res = $this->http( 'GET', '/find-a-home/?' . $query );
		$this->assertSame( 200, $res['status'] );
		preg_match_all( '/<li class="acme-re-listing[^"]*" data-listing-id="(\d+)"/', $res['body'], $m );
		preg_match( '/<p class="acme-re-count">([\d,]+) listings? found<\/p>/', $res['body'], $c );
		return array(
			'slugs' => slugs( array_map( 'intval', $m[1] ) ),
			'count' => isset( $c[1] ) ? (int) str_replace( ',', '', $c[1] ) : null,
			'body'  => $res['body'],
		);
	}

	public function test_search_page(): void {
		$cases = array(
			'combo springfield'    => array( 'city=Springfield&beds=2&features%5B%5D=garage&sort=price_asc&max_price=900000', 1 ),
			'feature'              => array( 'features%5B%5D=pool&listing_page=3', 3 ),
			'city alias'           => array( 'city=NYC&listing_page=2', 2 ),
			'city with apostrophe' => array( 'city=' . rawurlencode( "Coeur d'Alene" ) . '&status=all', 1 ),
			'overlapping features' => array( 'features%5B%5D=pool&features%5B%5D=pool-heated&status=all&listing_page=2', 2 ),
			'beds desc'            => array( 'sort=beds_desc&listing_page=4', 4 ),
			'no results'           => array( 'city=Ogdenville&beds=6&features%5B%5D=pool&features%5B%5D=sea-view&features%5B%5D=elevator', 1 ),
		);
		foreach ( $cases as $name => list( $query, $page ) ) {
			$res = $this->page( $query );
			$this->assertSame( array_slice( expected()[ $name ]['slugs'], ( $page - 1 ) * 10, 10 ), $res['slugs'], $name );
			$this->assertSame( expected()[ $name ]['total'], $res['count'], $name );
		}
	}

	public function test_edit_screen_save_updates_the_search(): void {
		$slug = expected()['combo capital pending']['slugs'][0];
		$id   = id( $slug );
		$this->remember( $id, array( '_acme_price', '_acme_bedrooms', '_acme_bathrooms', '_acme_sqft', '_acme_city', '_acme_features', '_acme_status' ) );
		$post  = get_post( $id );
		$login = $this->http_login( 1 );

		$res = $this->http(
			'POST',
			'/wp-admin/post.php',
			array(
				'login' => $login,
				'body'  => array(
					'_wpnonce'                      => $this->nonce_for( 1, 'update-post_' . $id, $login['logged_in'] ),
					'acme_re_listing_details_nonce' => $this->nonce_for( 1, 'acme_re_listing_details', $login['logged_in'] ),
					'action'                        => 'editpost',
					'originalaction'                => 'editpost',
					'post_ID'                       => $id,
					'post_type'                     => 'acme_listing',
					'original_post_status'          => 'publish',
					'post_status'                   => 'publish',
					'post_title'                    => $post->post_title,
					'content'                       => $post->post_content,
					'save'                          => 'Update',
					'acme_re'                       => array(
						'price'     => '2750000',
						'bedrooms'  => '',
						'bathrooms' => '1',
						'sqft'      => '5000',
						'city'      => 'San Francisco',
						'status'    => 'for-sale',
						'features'  => array( 'sea-view', 'elevator' ),
					),
				),
			)
		);
		$this->assertSame( 302, $res['status'], substr( $res['body'], 0, 1500 ) );

		$this->assertNotContains( $slug, $this->found( array( 'city' => 'Capital City', 'status' => 'pending' ) ) );
		$this->assertSame( $slug, $this->found( array( 'city' => 'San Francisco', 'features' => 'elevator,sea-view', 'sort' => 'price_desc', 'min_price' => 2750000 ) )[0] ?? null );
		$this->assertNotContains( $slug, $this->found( array( 'city' => 'San Francisco', 'sort' => 'beds_desc' ) ), 'Bedrooms were cleared' );

		$rest = $this->http( 'GET', '/wp-json/acme-re/v1/listings?city=SF&min_price=2750000&max_price=2750000&features=sea-view' );
		$this->assertSame( 200, $rest['status'] );
		$this->assertContains( $slug, array_column( $rest['json']['items'], 'slug' ) );
	}
}
