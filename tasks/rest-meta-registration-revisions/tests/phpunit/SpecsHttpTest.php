<?php
/**
 * Real requests against the site: front end, previews, the specifications box, WP-CLI.
 */

use function WPSB\Specs\certs;
use function WPSB\Specs\product_id;
use function WPSB\Specs\stored;
use function WPSB\Specs\tables;
use function WPSB\Specs\user_id;
use const WPSB\Specs\CERTS;
use const WPSB\Specs\DIMS;
use const WPSB\Specs\LEGACY_KEYS;
use const WPSB\Specs\MATS;

class SpecsHttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var array<int, array<string, array>> */
	private array $snapshots = array();

	/** @var int[] */
	private array $cleanup = array();

	private function snapshot( int $id ): void {
		$meta = array();
		foreach ( array_merge( array( DIMS, MATS, CERTS ), LEGACY_KEYS ) as $key ) {
			$meta[ $key ] = get_post_meta( $id, $key, false );
		}
		$this->snapshots[ $id ] = array(
			'meta'    => $meta,
			'content' => get_post( $id )->post_content,
			'status'  => get_post( $id )->post_status,
		);
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->snapshots as $id => $snap ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $snap['content'], 'post_status' => $snap['status'] ), array( 'ID' => $id ) );
			foreach ( $snap['meta'] as $key => $values ) {
				$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $key ) );
				foreach ( $values as $value ) {
					$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $key, 'meta_value' => maybe_serialize( $value ) ) );
				}
			}
			foreach ( wp_get_post_autosave( $id ) ? array( wp_get_post_autosave( $id ) ) : array() as $autosave ) {
				wp_delete_post_revision( $autosave->ID );
			}
			delete_transient( 'acme_specs_html_' . $id );
			clean_post_cache( $id );
		}
		foreach ( $this->cleanup as $id ) {
			wp_delete_post( $id, true );
		}
		wp_cache_flush();
		parent::tearDown();
	}

	private function page( string $url, ?array $login = null ): string {
		$res = $this->http( 'GET', $url, $login ? array( 'login' => $login ) : array() );
		$this->assertSame( 200, $res['status'], "GET $url: " . substr( $res['body'], 0, 500 ) );
		return $res['body'];
	}

	private function path( int $id ): string {
		return wp_make_link_relative( get_permalink( $id ) );
	}

	public function test_front_end_tables(): void {
		$oak = tables( $this->page( $this->path( product_id( 'oak-desk' ) ) ) );
		$this->assertCount( 1, $oak );
		$this->assertSame( '120 × 75 × 60 cm', $oak[0]['dimensions'] );
		$this->assertSame( 'Oak, Steel', $oak[0]['materials'] );
		$this->assertSame( array( 'CE' => 'since 2019-03-01, valid until 2029-03-01', 'FSC' => 'since 2021-06-15' ), $oak[0]['certs'] );

		$shelf = tables( $this->page( $this->path( product_id( 'steel-shelf' ) ) ) );
		$this->assertCount( 1, $shelf );
		$this->assertSame( '90 × 180 × 35.5 cm', $shelf[0]['dimensions'] );
		$this->assertSame( 'Steel, Powder coating', $shelf[0]['materials'] );
		$this->assertSame( array( 'CE' => 'since 2018-01-10', 'GS' => 'since 2020-05-12, valid until 2025-05-12' ), $shelf[0]['certs'] );

		$cabinet = tables( $this->page( $this->path( product_id( 'walnut-cabinet' ) ) ) );
		$this->assertNull( $cabinet[0]['dimensions'] );
		$this->assertSame( 'Walnut veneer, MDF', $cabinet[0]['materials'] );

		$lamp = tables( $this->page( $this->path( product_id( 'lamp-classic' ) ) ) );
		$this->assertSame( '20 × 45 × 20 cm', $lamp[0]['dimensions'] );
		$this->assertSame( 'Brass, Linen', $lamp[0]['materials'] );
		$this->assertSame( array(), $lamp[0]['certs'] );

		$this->assertSame( array(), tables( $this->page( $this->path( product_id( 'mystery-box' ) ) ) ) );

		$guide = tables( $this->page( '/desk-buying-guide/' ) );
		$this->assertCount( 2, $guide );
		$this->assertSame( '120 × 75 × 60 cm', $guide[0]['dimensions'] );
		$this->assertSame( '90 × 180 × 35.5 cm', $guide[1]['dimensions'] );
	}

	public function test_rest_updates_show_up_on_the_front_end(): void {
		$id = product_id( 'oak-desk' );
		$this->snapshot( $id );
		$login = $this->http_login( user_id( 'eddie' ) );
		$res   = $this->http(
			'POST',
			'/wp-json/wp/v2/products/' . $id,
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array( 'meta' => array( DIMS => array( 'width' => 99, 'height' => 88, 'depth' => 77, 'unit' => 'mm' ) ) ),
			)
		);
		$this->assertSame( 200, $res['status'], $res['body'] );
		$this->assertEquals( 99, $res['json']['meta'][ DIMS ]['width'] ?? null );

		$table = tables( $this->page( $this->path( $id ) ) );
		$this->assertSame( '99 × 88 × 77 mm', $table[0]['dimensions'] );
	}

	public function test_preview_shows_autosaved_specs(): void {
		$id    = product_id( 'oak-desk' );
		$eddie = user_id( 'eddie' );
		$this->snapshot( $id );

		// Warm the public page first.
		$this->assertSame( '120 × 75 × 60 cm', tables( $this->page( $this->path( $id ) ) )[0]['dimensions'] );

		$login = $this->http_login( $eddie );
		$res   = $this->autosave( $id, $login, 'Unsaved oak description.', array(
			DIMS  => array( 'width' => 160, 'height' => 72, 'depth' => 80, 'unit' => 'cm' ),
			MATS  => array( 'Smoked oak' ),
			CERTS => array(
				array( 'code' => 'CE', 'issued' => '2019-03-01', 'expires' => '2029-03-01' ),
				array( 'code' => 'FSC', 'issued' => '2021-06-15' ),
			),
		) );
		$this->assertContains( $res['status'], array( 200, 201 ), $res['body'] );

		$nonce   = $this->nonce_for( $eddie, 'post_preview_' . $id, $login['logged_in'] );
		$preview = $this->page( add_query_arg( array( 'preview' => 'true', 'preview_id' => $id, 'preview_nonce' => $nonce ), $this->path( $id ) ), $login );
		$this->assertStringContainsString( 'Unsaved oak description.', $preview, 'Preview must show the autosave' );
		$table = tables( $preview );
		$this->assertCount( 1, $table );
		$this->assertSame( '160 × 72 × 80 cm', $table[0]['dimensions'], 'Preview must show the autosaved specs' );
		$this->assertSame( 'Smoked oak', $table[0]['materials'] );

		// Visitors still see the published specs.
		foreach ( array( $this->path( $id ), '/desk-buying-guide/' ) as $url ) {
			$public = tables( $this->page( $url ) );
			$this->assertSame( '120 × 75 × 60 cm', $public[0]['dimensions'], "Unsaved specs leaked to $url" );
			$this->assertSame( 'Oak, Steel', $public[0]['materials'] );
		}
		$this->assertStringNotContainsString( 'Unsaved oak description.', $this->page( $this->path( $id ) ) );
		$this->assertSame( array( 'Oak', 'Steel' ), stored( $id, MATS ) );
	}

	private function autosave( int $id, array $login, string $content, array $meta ): array {
		return $this->http(
			'POST',
			"/wp-json/wp/v2/products/$id/autosaves",
			array(
				'login'      => $login,
				'rest_nonce' => true,
				'json'       => true,
				'body'       => array(
					'title'   => get_post( $id )->post_title,
					'content' => $content,
					'meta'    => $meta,
				),
			)
		);
	}

	public function test_autosave_keeps_unsaved_specs_separate(): void {
		$id = product_id( 'oak-desk' );
		$this->snapshot( $id );
		$res = $this->autosave(
			$id,
			$this->http_login( user_id( 'eddie' ) ),
			'Unsaved description.',
			array(
				DIMS  => array( 'width' => 100, 'height' => 70, 'depth' => 50, 'unit' => 'cm' ),
				MATS  => array( 'Birch' ),
				CERTS => array( array( 'code' => 'CE', 'issued' => '2020-01-01' ) ),
			)
		);
		$this->assertContains( $res['status'], array( 200, 201 ), $res['body'] );
		$autosave_id = (int) ( $res['json']['id'] ?? 0 );
		$this->assertGreaterThan( 0, $autosave_id );
		$this->assertNotSame( $id, $autosave_id, 'Published products get a separate autosave' );
		$this->assertSame( array( 'Birch' ), $res['json']['meta'][ MATS ] ?? null, 'The autosave response carries the specs' );

		wp_cache_flush();
		// The published product is untouched.
		$this->assertSame( array( 'Oak', 'Steel' ), stored( $id, MATS ) );
		$this->assertEquals( 120, stored( $id, DIMS )['width'] ?? null );
		$this->assertStringNotContainsString( 'Unsaved description.', get_post( $id )->post_content );

		// The autosave has them.
		$this->assertSame( array( 'Birch' ), stored( $autosave_id, MATS ) );
		$this->assertEquals( 100, stored( $autosave_id, DIMS )['width'] ?? null );
		wp_set_current_user( user_id( 'eddie' ) );
		$list = $this->rest( 'GET', "/wp/v2/products/$id/autosaves" );
		$this->assertSame( 200, $list->get_status() );
		$this->assertSame( array( 'Birch' ), $list->get_data()[0]['meta'][ MATS ] ?? null );
	}

	private function metabox_post( int $id, array $login, array $specs ): array {
		$user = $login['user_id'];
		$post = get_post( $id );
		return $this->http(
			'POST',
			'/wp-admin/post.php',
			array(
				'login' => $login,
				'body'  => array(
					'action'               => 'editpost',
					'post_ID'              => $id,
					'post_type'            => 'acme_product',
					'user_ID'              => $user,
					'_wpnonce'             => $this->nonce_for( $user, 'update-post_' . $id, $login['logged_in'] ),
					'_wp_http_referer'     => '/wp-admin/post.php?post=' . $id . '&action=edit',
					'post_title'           => $post->post_title,
					'content'              => $post->post_content,
					'post_status'          => 'publish',
					'original_post_status' => 'publish',
					'visibility'           => 'public',
					'acme_specs_nonce'     => $this->nonce_for( $user, 'acme_specs_save', $login['logged_in'] ),
					'acme_specs'           => $specs,
				),
			)
		);
	}

	public function test_specifications_box_still_saves(): void {
		$id = product_id( 'bar-stool' );
		$this->snapshot( $id );
		$res = $this->metabox_post(
			$id,
			$this->http_login( 1 ),
			array(
				'width'     => '400',
				'height'    => '750,5',
				'depth'     => '410',
				'unit'      => 'mm',
				'materials' => "Beech\nSteel",
				'certs'     => array(
					array( 'code' => 'GS', 'issued' => '2024-03-01', 'expires' => '' ),
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), substr( $res['body'], 0, 800 ) );
		wp_cache_flush();
		$dims = stored( $id, DIMS );
		$this->assertEquals( array( 'width' => 400, 'height' => 750.5, 'depth' => 410, 'unit' => 'mm' ), (array) $dims );
		$this->assertSame( array( 'Beech', 'Steel' ), stored( $id, MATS ) );
		$this->assertSame( array( array( 'GS', '2024-03-01', '' ) ), certs( stored( $id, CERTS ) ) );
		$this->assertSame( '400 × 750.5 × 410 mm', tables( $this->page( $this->path( $id ) ) )[0]['dimensions'] );
	}

	public function test_specifications_box_does_not_let_authors_change_certifications(): void {
		$id = product_id( 'oak-desk' );
		$this->snapshot( $id );
		$res = $this->metabox_post(
			$id,
			$this->http_login( user_id( 'alice' ) ),
			array(
				'width'     => '121',
				'height'    => '75',
				'depth'     => '60',
				'unit'      => 'cm',
				'materials' => "Oak\nSteel\nWax",
				'certs'     => array(
					array( 'code' => 'UL', 'issued' => '2024-03-01', 'expires' => '' ),
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), substr( $res['body'], 0, 800 ) );
		wp_cache_flush();
		$this->assertEquals( 121, stored( $id, DIMS )['width'] ?? null, 'Authors can still edit dimensions in the box' );
		$this->assertSame( array( 'Oak', 'Steel', 'Wax' ), stored( $id, MATS ) );
		$this->assertSame(
			array( array( 'CE', '2019-03-01', '2029-03-01' ), array( 'FSC', '2021-06-15', '' ) ),
			certs( stored( $id, CERTS ) ),
			'Authors must not change certifications through the box'
		);
	}

	public function test_cli_migrates_imported_legacy_products(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => 'acme_product',
				'post_status' => 'publish',
				'post_title'  => 'Imported Table',
				'post_name'   => 'imported-table-' . wp_rand(),
			)
		);
		$this->cleanup[] = $id;
		update_post_meta( $id, '_acme_width', '80' );
		update_post_meta( $id, '_acme_height', '74' );
		update_post_meta( $id, '_acme_depth', '80' );
		update_post_meta( $id, '_acme_unit', 'cm' );
		update_post_meta( $id, '_acme_materials', 'Pine' );
		update_post_meta( $id, '_acme_certs', 'FSC:03.04.2021' );

		$run = $this->wp_cli( 'acme-specs migrate' );
		$this->assertSame( 0, $run['exit'], $run['stderr'] . $run['stdout'] );
		$this->assertMatchesRegularExpression( '/Migrated 1 product\b/', $run['stdout'] . $run['stderr'] );

		wp_cache_flush();
		$this->assertEquals( array( 'width' => 80, 'height' => 74, 'depth' => 80, 'unit' => 'cm' ), (array) stored( $id, DIMS ) );
		$this->assertSame( array( 'Pine' ), stored( $id, MATS ) );
		$this->assertSame( array( array( 'FSC', '2021-04-03', '' ) ), certs( stored( $id, CERTS ) ) );
		foreach ( LEGACY_KEYS as $key ) {
			$this->assertFalse( metadata_exists( 'post', $id, $key ), "$key must be removed" );
		}

		$again = $this->wp_cli( 'acme-specs migrate' );
		$this->assertSame( 0, $again['exit'] );
		$this->assertMatchesRegularExpression( '/Migrated 0 products/', $again['stdout'] . $again['stderr'] );
		$this->assertSame( array( 'Pine' ), stored( $id, MATS ) );
	}
}
