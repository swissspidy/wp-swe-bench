<?php
/**
 * Specs in the REST API: shape, schema, migrated legacy data, validation, permissions.
 */

use function WPSB\Specs\certs;
use function WPSB\Specs\product_id;
use function WPSB\Specs\stored;
use function WPSB\Specs\user_id;
use const WPSB\Specs\CERTS;
use const WPSB\Specs\DIMS;
use const WPSB\Specs\LEGACY_KEYS;
use const WPSB\Specs\MATS;

class SpecsRestTest extends WPSB\TestCase {

	private function get( int $id, array $query = array() ): WP_REST_Response {
		return $this->rest( 'GET', '/wp/v2/products/' . $id, $query );
	}

	private function update( int $id, array $meta, array $extra = array() ): WP_REST_Response {
		return $this->rest( 'POST', '/wp/v2/products/' . $id, array(), array_merge( $extra, array( 'meta' => $meta ) ) );
	}

	private function meta_of( WP_REST_Response $res ): array {
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data = $res->get_data();
		$this->assertArrayHasKey( 'meta', $data, 'Products must expose meta' );
		foreach ( array( DIMS, MATS, CERTS ) as $key ) {
			$this->assertArrayHasKey( $key, (array) $data['meta'], "meta.$key missing" );
		}
		return (array) $data['meta'];
	}

	private function oak_certs(): array {
		return array(
			array( 'CE', '2019-03-01', '2029-03-01' ),
			array( 'FSC', '2021-06-15', '' ),
		);
	}

	public function test_structured_specs_are_public(): void {
		$meta = $this->meta_of( $this->get( product_id( 'oak-desk' ) ) );
		$this->assertEquals( array( 'width' => 120, 'height' => 75, 'depth' => 60, 'unit' => 'cm' ), (array) $meta[ DIMS ] );
		$this->assertSame( array( 'Oak', 'Steel' ), $meta[ MATS ] );
		$this->assertSame( $this->oak_certs(), certs( $meta[ CERTS ] ) );

		// Also in collections, for anonymous visitors.
		$list = $this->rest( 'GET', '/wp/v2/products', array( 'per_page' => 50 ) );
		$this->assertSame( 200, $list->get_status() );
		$found = false;
		foreach ( $list->get_data() as $item ) {
			if ( product_id( 'oak-desk' ) === $item['id'] ) {
				$found = true;
				$this->assertSame( array( 'Oak', 'Steel' ), $item['meta'][ MATS ] ?? null );
			}
		}
		$this->assertTrue( $found );
	}

	public function test_draft_specs_stay_private(): void {
		$res = $this->get( product_id( 'draft-chair' ) );
		$this->assertContains( $res->get_status(), array( 401, 403 ) );
		$this->assertStringNotContainsString( 'Secret alloy', wp_json_encode( $this->rest_data( $this->rest( 'GET', '/wp/v2/products', array( 'per_page' => 50 ) ) ) ) );
	}

	public function test_legacy_product_was_migrated(): void {
		$id   = product_id( 'steel-shelf' );
		$meta = $this->meta_of( $this->get( $id ) );
		$this->assertEquals( array( 'width' => 90, 'height' => 180, 'depth' => 35.5, 'unit' => 'cm' ), (array) $meta[ DIMS ] );
		$this->assertSame( array( 'Steel', 'Powder coating' ), $meta[ MATS ] );
		$this->assertSame( array( array( 'CE', '2018-01-10', '' ), array( 'GS', '2020-05-12', '2025-05-12' ) ), certs( $meta[ CERTS ] ) );

		$dims = stored( $id, DIMS );
		$this->assertIsArray( $dims, 'Migrated dimensions are stored in the structured format' );
		$this->assertEquals( 35.5, $dims['depth'] );
		$this->assertSame( 'cm', $dims['unit'] );
		foreach ( LEGACY_KEYS as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), "1.x key $key must be removed after the upgrade" );
		}
	}

	public function test_messy_legacy_products_were_migrated(): void {
		$cabinet = $this->meta_of( $this->get( product_id( 'walnut-cabinet' ) ) );
		$this->assertEmpty( $cabinet[ DIMS ], 'Incomplete 1.x dimensions are dropped' );
		$this->assertSame( array( 'Walnut veneer', 'MDF' ), $cabinet[ MATS ] );
		$this->assertSame( array( array( 'UL', '2022-07-01', '' ) ), certs( $cabinet[ CERTS ] ) );

		$stool = $this->meta_of( $this->get( product_id( 'bar-stool' ) ) );
		$this->assertEquals( array( 'width' => 420, 'height' => 760, 'depth' => 420, 'unit' => 'mm' ), (array) $stool[ DIMS ] );
		$this->assertSame( array( 'Beech' ), $stool[ MATS ] );
		$this->assertEmpty( $stool[ CERTS ] );
		foreach ( array( 'walnut-cabinet', 'bar-stool' ) as $slug ) {
			foreach ( LEGACY_KEYS as $key ) {
				$this->assertFalse( metadata_exists( 'post', product_id( $slug ), $key ), "$slug: $key must be removed" );
			}
		}
	}

	public function test_structured_data_wins_over_leftover_legacy_data(): void {
		$id   = product_id( 'lamp-classic' );
		$meta = $this->meta_of( $this->get( $id ) );
		$this->assertEquals( array( 'width' => 20, 'height' => 45, 'depth' => 20, 'unit' => 'cm' ), (array) $meta[ DIMS ] );
		$this->assertSame( array( 'Brass', 'Linen' ), $meta[ MATS ] );
		$this->assertEmpty( $meta[ CERTS ] );
		foreach ( LEGACY_KEYS as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), "1.x key $key must be removed" );
		}
	}

	public function test_product_without_specs(): void {
		$meta = $this->meta_of( $this->get( product_id( 'mystery-box' ) ) );
		$this->assertEmpty( $meta[ DIMS ] );
		$this->assertSame( array(), $meta[ MATS ] );
		$this->assertSame( array(), $meta[ CERTS ] );
	}

	public function test_schema_describes_the_specs(): void {
		$res = $this->rest( 'OPTIONS', '/wp/v2/products' );
		$this->assertSame( 200, $res->get_status() );
		$props = $res->get_data()['schema']['properties']['meta']['properties'] ?? array();

		$dims = $props[ DIMS ] ?? null;
		$this->assertNotNull( $dims, 'dimensions missing from the schema' );
		$this->assertContains( 'object', (array) $dims['type'] );
		foreach ( array( 'width', 'height', 'depth' ) as $axis ) {
			$this->assertContains( 'number', (array) ( $dims['properties'][ $axis ]['type'] ?? array() ), "$axis must be a number" );
		}
		$this->assertEqualsCanonicalizing( array( 'mm', 'cm', 'in' ), $dims['properties']['unit']['enum'] ?? array() );

		$mats = $props[ MATS ] ?? null;
		$this->assertNotNull( $mats );
		$this->assertContains( 'array', (array) $mats['type'] );
		$this->assertContains( 'string', (array) ( $mats['items']['type'] ?? array() ) );

		$certs = $props[ CERTS ] ?? null;
		$this->assertNotNull( $certs );
		$this->assertContains( 'array', (array) $certs['type'] );
		$this->assertContains( 'object', (array) ( $certs['items']['type'] ?? array() ) );
		$codes = $certs['items']['properties']['code']['enum'] ?? array();
		foreach ( array( 'CE', 'UL', 'RoHS', 'FCC', 'FSC', 'GS' ) as $code ) {
			$this->assertContains( $code, $codes, "Certification code $code (incl. codes added through acme_specs_certification_codes) must be in the schema" );
		}
		$this->assertArrayHasKey( 'issued', $certs['items']['properties'] );
		$this->assertArrayHasKey( 'expires', $certs['items']['properties'] );
	}

	public function test_editor_writes_all_specs(): void {
		$this->login_as( user_id( 'eddie' ) );
		$id   = product_id( 'oak-desk' );
		$meta = $this->meta_of(
			$this->update(
				$id,
				array(
					DIMS  => array( 'width' => 140.5, 'height' => 76, 'depth' => 70, 'unit' => 'in' ),
					MATS  => array( 'Oak', 'Steel', 'Linseed oil' ),
					CERTS => array(
						array( 'code' => 'GS', 'issued' => '2024-02-29', 'expires' => '2027-02-28' ),
						array( 'code' => 'CE', 'issued' => '2019-03-01' ),
					),
				)
			)
		);
		$this->assertEquals( array( 'width' => 140.5, 'height' => 76, 'depth' => 70, 'unit' => 'in' ), (array) $meta[ DIMS ] );
		$this->assertSame( array( 'Oak', 'Steel', 'Linseed oil' ), $meta[ MATS ] );
		$this->assertSame( array( array( 'GS', '2024-02-29', '2027-02-28' ), array( 'CE', '2019-03-01', '' ) ), certs( $meta[ CERTS ] ) );

		// Stored in the documented structured format, readable by the rest of the plugin.
		$dims = stored( $id, DIMS );
		$this->assertIsArray( $dims );
		$this->assertEquals( 140.5, $dims['width'] );
		$this->assertSame( 'in', $dims['unit'] );
		$this->assertSame( array( 'Oak', 'Steel', 'Linseed oil' ), stored( $id, MATS ) );
		$this->assertSame( array( array( 'GS', '2024-02-29', '2027-02-28' ), array( 'CE', '2019-03-01', '' ) ), certs( stored( $id, CERTS ) ) );
		$this->assertStringContainsString( '140.5 × 76 × 70 in', acme_specs_format_dimensions( acme_specs_get( $id )['dimensions'] ) );

		// Clearing with null.
		$meta = $this->meta_of( $this->update( $id, array( MATS => null ) ) );
		$this->assertEmpty( $meta[ MATS ] );
		$this->assertSame( array(), acme_specs_get( $id )['materials'] );
	}

	public static function invalid_payloads(): array {
		$cert = static function ( array $overrides ) {
			return array( CERTS => array( array_merge( array( 'code' => 'CE', 'issued' => '2020-01-01' ), $overrides ) ) );
		};
		$dims = static function ( array $overrides ) {
			return array( DIMS => array_merge( array( 'width' => 10, 'height' => 10, 'depth' => 10, 'unit' => 'cm' ), $overrides ) );
		};
		$no_depth = $dims( array() );
		unset( $no_depth[ DIMS ]['depth'] );
		return array(
			'unknown unit'             => array( $dims( array( 'unit' => 'furlong' ) ) ),
			'negative width'           => array( $dims( array( 'width' => -5 ) ) ),
			'zero height'              => array( $dims( array( 'height' => 0 ) ) ),
			'non-numeric depth'        => array( $dims( array( 'depth' => 'deep' ) ) ),
			'missing depth'            => array( $no_depth ),
			'unknown dimension prop'   => array( $dims( array( 'color' => 'red' ) ) ),
			'dimensions not an object' => array( array( DIMS => '10x10x10' ) ),
			'materials as object'      => array( array( MATS => array( 'name' => 'Oak' ) ) ),
			'empty material'           => array( array( MATS => array( 'Oak', '' ) ) ),
			'too many materials'       => array( array( MATS => array_map( static fn( $i ) => "Material $i", range( 1, 13 ) ) ) ),
			'unknown certification'    => array( $cert( array( 'code' => 'XYZ' ) ) ),
			'month 13'                 => array( $cert( array( 'issued' => '2020-13-01' ) ) ),
			'february 30'              => array( $cert( array( 'issued' => '2023-02-30' ) ) ),
			'1.x date format'          => array( $cert( array( 'issued' => '01.03.2019' ) ) ),
			'expiry not a date'        => array( $cert( array( 'expires' => 'never' ) ) ),
			'expires before issued'    => array( $cert( array( 'issued' => '2021-05-01', 'expires' => '2021-04-30' ) ) ),
			'missing issue date'       => array( array( CERTS => array( array( 'code' => 'CE' ) ) ) ),
			'unknown cert prop'        => array( $cert( array( 'notes' => 'x' ) ) ),
			'cert not an object'       => array( array( CERTS => array( 'CE' ) ) ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'invalid_payloads' )]
	public function test_invalid_specs_are_rejected( array $meta ): void {
		$this->login_as( user_id( 'eddie' ) );
		$id  = product_id( 'oak-desk' );
		$res = $this->update( $id, $meta );
		$this->assertSame( 400, $res->get_status(), 'Expected 400, got: ' . wp_json_encode( $res->get_data() ) );

		$this->assertEquals( array( 'width' => 120, 'height' => 75, 'depth' => 60, 'unit' => 'cm' ), (array) stored( $id, DIMS ) );
		$this->assertSame( array( 'Oak', 'Steel' ), stored( $id, MATS ) );
		$this->assertSame( $this->oak_certs(), certs( stored( $id, CERTS ) ) );
	}

	public function test_valid_edge_values_are_accepted(): void {
		$this->login_as( user_id( 'eddie' ) );
		$id   = product_id( 'mystery-box' );
		$meta = $this->meta_of(
			$this->update(
				$id,
				array(
					DIMS  => array( 'width' => 0.5, 'height' => 1, 'depth' => 2.25, 'unit' => 'mm' ),
					MATS  => array_map( static fn( $i ) => "Material $i", range( 1, 12 ) ),
					CERTS => array( array( 'code' => 'RoHS', 'issued' => '2022-12-31', 'expires' => '2022-12-31' ) ),
				)
			)
		);
		$this->assertEquals( array( 'width' => 0.5, 'height' => 1, 'depth' => 2.25, 'unit' => 'mm' ), (array) $meta[ DIMS ] );
		$this->assertCount( 12, $meta[ MATS ] );
		$this->assertSame( array( array( 'RoHS', '2022-12-31', '2022-12-31' ) ), certs( $meta[ CERTS ] ) );
	}

	public function test_authors_edit_dimensions_and_materials_of_their_products(): void {
		$this->login_as( user_id( 'alice' ) );
		$id   = product_id( 'oak-desk' );
		$meta = $this->meta_of(
			$this->update(
				$id,
				array(
					DIMS => array( 'width' => 118, 'height' => 74, 'depth' => 60, 'unit' => 'cm' ),
					MATS => array( 'Oak', 'Steel', 'Felt pads' ),
				)
			)
		);
		$this->assertEquals( 118, $meta[ DIMS ]['width'] );
		$this->assertSame( array( 'Oak', 'Steel', 'Felt pads' ), stored( $id, MATS ) );
		$this->assertSame( $this->oak_certs(), certs( $meta[ CERTS ] ) );

		// The editor UI re-sends the unchanged certifications on save: that is fine.
		$current = array(
			array( 'code' => 'CE', 'issued' => '2019-03-01', 'expires' => '2029-03-01' ),
			array( 'code' => 'FSC', 'issued' => '2021-06-15' ),
		);
		$meta    = $this->meta_of( $this->update( $id, array( MATS => array( 'Oak' ), CERTS => $current ) ) );
		$this->assertSame( array( 'Oak' ), stored( $id, MATS ) );
		$this->assertSame( $this->oak_certs(), certs( stored( $id, CERTS ) ) );
	}

	public function test_only_editors_change_certifications(): void {
		$id = product_id( 'oak-desk' );

		$this->login_as( user_id( 'alice' ) );
		$attempts = array(
			array( array( 'code' => 'UL', 'issued' => '2024-01-01' ) ),
			array( array( 'code' => 'CE', 'issued' => '2019-03-01', 'expires' => '2039-03-01' ) ),
			array(),
			null,
		);
		foreach ( $attempts as $certs ) {
			$res = $this->update( $id, array( CERTS => $certs ) );
			$this->assertSame( 403, $res->get_status(), 'Author changed certifications: ' . wp_json_encode( $certs ) );
			$this->assertSame( $this->oak_certs(), certs( stored( $id, CERTS ) ) );
		}

		// Authors can't create products with certifications either.
		$res = $this->rest(
			'POST',
			'/wp/v2/products',
			array(),
			array(
				'title'  => 'Alice lamp',
				'status' => 'draft',
				'meta'   => array( CERTS => array( array( 'code' => 'CE', 'issued' => '2024-01-01' ) ) ),
			)
		);
		$this->assertSame( 403, $res->get_status() );
		$created = get_posts( array( 'post_type' => 'acme_product', 'title' => 'Alice lamp', 'post_status' => 'any', 'fields' => 'ids' ) );
		foreach ( $created as $created_id ) {
			$this->assertEmpty( stored( $created_id, CERTS ) );
		}

		// Other people's products: not at all.
		$this->login_as( user_id( 'bob' ) );
		$this->assertSame( 403, $this->update( $id, array( MATS => array( 'Plastic' ) ) )->get_status() );
		$this->login_as( user_id( 'carl' ) );
		$this->assertSame( 403, $this->update( $id, array( MATS => array( 'Plastic' ) ) )->get_status() );
		$this->assertSame( array( 'Oak', 'Steel' ), stored( $id, MATS ) );

		// Editors and administrators can.
		$this->login_as( user_id( 'eddie' ) );
		$meta = $this->meta_of( $this->update( $id, array( CERTS => array( array( 'code' => 'UL', 'issued' => '2024-01-01' ) ) ) ) );
		$this->assertSame( array( array( 'UL', '2024-01-01', '' ) ), certs( $meta[ CERTS ] ) );
		$this->login_as( 1 );
		$meta = $this->meta_of( $this->update( $id, array( CERTS => null ) ) );
		$this->assertEmpty( $meta[ CERTS ] );
	}

	public function test_edit_context_for_authors(): void {
		$this->login_as( user_id( 'alice' ) );
		$meta = $this->meta_of( $this->get( product_id( 'draft-chair' ), array( 'context' => 'edit' ) ) );
		$this->assertSame( array( 'Secret alloy' ), $meta[ MATS ] );
	}
}
