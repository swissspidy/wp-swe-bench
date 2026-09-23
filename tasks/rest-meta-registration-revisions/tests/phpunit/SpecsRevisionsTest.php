<?php
/**
 * Specs in revisions and autosaves.
 */

use function WPSB\Specs\certs;
use function WPSB\Specs\product_id;
use function WPSB\Specs\stored;
use function WPSB\Specs\user_id;
use const WPSB\Specs\CERTS;
use const WPSB\Specs\DIMS;
use const WPSB\Specs\MATS;

class SpecsRevisionsTest extends WPSB\TestCase {

	private function update( int $id, array $body ): WP_REST_Response {
		$res = $this->rest( 'POST', '/wp/v2/products/' . $id, array(), $body );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		return $res;
	}

	private function revisions( int $id ): array {
		$res = $this->rest( 'GET', "/wp/v2/products/$id/revisions", array( 'per_page' => 100 ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		return $res->get_data();
	}

	private function specs_a(): array {
		return array(
			DIMS  => array( 'width' => 100, 'height' => 70, 'depth' => 50, 'unit' => 'cm' ),
			MATS  => array( 'Birch' ),
			CERTS => array( array( 'code' => 'CE', 'issued' => '2020-01-01' ) ),
		);
	}

	private function specs_b(): array {
		return array(
			DIMS  => array( 'width' => 200, 'height' => 80, 'depth' => 90, 'unit' => 'cm' ),
			MATS  => array( 'Walnut', 'Brass' ),
			CERTS => array( array( 'code' => 'UL', 'issued' => '2023-05-05', 'expires' => '2026-05-05' ) ),
		);
	}

	public function test_spec_changes_create_revisions_that_carry_the_specs(): void {
		$this->login_as( user_id( 'eddie' ) );
		$id     = product_id( 'oak-desk' );
		$before = count( wp_get_post_revisions( $id ) );

		// Only the specs change, the content stays the same.
		$this->update( $id, array( 'meta' => $this->specs_a() ) );
		$this->assertCount( $before + 1, wp_get_post_revisions( $id ), 'Changing only the specs must create a revision' );

		$this->update( $id, array( 'meta' => $this->specs_b() ) );
		$this->assertCount( $before + 2, wp_get_post_revisions( $id ) );

		$latest = $this->revisions( $id )[0];
		$this->assertArrayHasKey( 'meta', $latest, 'Revisions must expose the specs' );
		$this->assertEquals( $this->specs_b()[ DIMS ], (array) $latest['meta'][ DIMS ] );
		$this->assertSame( array( 'Walnut', 'Brass' ), $latest['meta'][ MATS ] );
		$this->assertSame( array( array( 'UL', '2023-05-05', '2026-05-05' ) ), certs( $latest['meta'][ CERTS ] ) );

		$single = $this->rest( 'GET', "/wp/v2/products/$id/revisions/" . $latest['id'] );
		$this->assertSame( 200, $single->get_status() );
		$this->assertSame( array( 'Walnut', 'Brass' ), $single->get_data()['meta'][ MATS ] ?? null );

		// Saving again without changes does not create another revision.
		$this->update( $id, array( 'meta' => $this->specs_b() ) );
		$this->assertCount( $before + 2, wp_get_post_revisions( $id ) );
	}

	public function test_restoring_a_revision_restores_its_specs(): void {
		$this->login_as( user_id( 'eddie' ) );
		$id = product_id( 'oak-desk' );
		$this->update( $id, array( 'meta' => $this->specs_a(), 'content' => 'Birch edition.' ) );
		$this->update( $id, array( 'meta' => $this->specs_b(), 'content' => 'Walnut edition.' ) );

		$rev_a = null;
		foreach ( $this->revisions( $id ) as $revision ) {
			if ( false !== strpos( $revision['content']['raw'] ?? $revision['content']['rendered'], 'Birch edition.' ) ) {
				$rev_a = $revision;
			}
		}
		$this->assertNotNull( $rev_a, 'Revision with the Birch edition not found' );
		$this->assertSame( array( 'Birch' ), $rev_a['meta'][ MATS ] ?? null );

		$this->assertNotEmpty( wp_restore_post_revision( $rev_a['id'] ) );
		wp_cache_flush();

		$this->assertStringContainsString( 'Birch edition.', get_post( $id )->post_content );
		$this->assertEquals( $this->specs_a()[ DIMS ], (array) stored( $id, DIMS ) );
		$this->assertSame( array( 'Birch' ), stored( $id, MATS ) );
		$this->assertSame( array( array( 'CE', '2020-01-01', '' ) ), certs( stored( $id, CERTS ) ) );

		$res = $this->rest( 'GET', '/wp/v2/products/' . $id );
		$this->assertSame( array( 'Birch' ), $res->get_data()['meta'][ MATS ] );

		// The front end shows the restored specs too.
		$table = \WPSB\Specs\tables( do_shortcode( '[acme_specs id="' . $id . '"]' ) );
		$this->assertCount( 1, $table );
		$this->assertSame( '100 × 70 × 50 cm', $table[0]['dimensions'] );
		$this->assertSame( 'Birch', $table[0]['materials'] );
	}

	public function test_restoring_a_revision_that_cleared_materials(): void {
		$this->login_as( user_id( 'eddie' ) );
		$id = product_id( 'oak-desk' );
		$this->update( $id, array( 'meta' => array( MATS => null ), 'content' => 'No materials listed.' ) );
		$this->update( $id, array( 'meta' => $this->specs_b(), 'content' => 'Walnut edition.' ) );

		$target = null;
		foreach ( wp_get_post_revisions( $id ) as $revision ) {
			if ( false !== strpos( $revision->post_content, 'No materials listed.' ) ) {
				$target = $revision;
			}
		}
		$this->assertNotNull( $target );
		wp_restore_post_revision( $target->ID );
		wp_cache_flush();
		$this->assertEmpty( stored( $id, MATS ), 'Restoring a revision without materials clears them' );
		$this->assertEquals( array( 'width' => 120, 'height' => 75, 'depth' => 60, 'unit' => 'cm' ), (array) stored( $id, DIMS ) );
	}

	public function test_restoring_a_revision_from_before_the_update_keeps_specs(): void {
		$this->login_as( user_id( 'eddie' ) );
		$id  = product_id( 'oak-desk' );
		$old = null;
		foreach ( wp_get_post_revisions( $id ) as $revision ) {
			if ( false !== strpos( $revision->post_content, '2024 catalogue' ) ) {
				$old = $revision;
			}
		}
		$this->assertNotNull( $old, 'Seeded revision not found' );

		wp_restore_post_revision( $old->ID );
		wp_cache_flush();
		$this->assertStringContainsString( '2024 catalogue', get_post( $id )->post_content );
		$this->assertEquals( array( 'width' => 120, 'height' => 75, 'depth' => 60, 'unit' => 'cm' ), (array) stored( $id, DIMS ) );
		$this->assertSame( array( 'Oak', 'Steel' ), stored( $id, MATS ) );
		$this->assertCount( 2, certs( stored( $id, CERTS ) ) );

		// Same for a migrated 1.x product.
		$shelf = product_id( 'steel-shelf' );
		$revs  = wp_get_post_revisions( $shelf );
		$this->assertNotEmpty( $revs );
		wp_restore_post_revision( end( $revs )->ID );
		wp_cache_flush();
		$this->assertEquals( 35.5, stored( $shelf, DIMS )['depth'] ?? null );
		$this->assertSame( array( 'Steel', 'Powder coating' ), stored( $shelf, MATS ) );
	}
}
