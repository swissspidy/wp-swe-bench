<?php
/**
 * Saving products in the block editor: sidebar (REST API) + the meta box request that follows.
 */

use WPSB\ProductFields\Base;
use function WPSB\ProductFields\product_id;
use function WPSB\ProductFields\staff_fields;

class BlockEditorSaveTest extends Base {

	public function test_sidebar_changes_survive_the_meta_box_request(): void {
		$id     = $this->fresh( 'trail-runner-pro' );
		$staff  = staff_fields( $id );
		$editor = $this->open_block_editor( $id );
		$this->block_editor_save(
			$editor,
			array(
				'_acme_price'    => '139',
				'_acme_featured' => false,
				'_acme_badge'    => 'Now 15% off "Pro" \\ Sale',
				'_acme_sku'      => 'TRP-02',
			)
		);

		$meta = $this->rest_meta( $id );
		$this->assertSame( '139.00', $meta['_acme_price'] );
		$this->assertFalse( $meta['_acme_featured'], 'Unchecking "Featured product" in the sidebar was reverted' );
		$this->assertSame( 'Now 15% off "Pro" \\ Sale', $meta['_acme_badge'] );
		$this->assertSame( 'TRP-02', $meta['_acme_sku'] );
		$this->assertTrue( $meta['_acme_in_stock'] );
		$this->assertSame( $staff, staff_fields( $id ), 'Staff fields must not change' );
	}

	public function test_meta_box_fields_are_saved_exactly_from_the_block_editor(): void {
		$id     = $this->fresh( 'trail-runner-pro' );
		$before = $this->rest_meta( $id );
		$editor = $this->edit_metabox(
			$this->open_block_editor( $id ),
			array(
				'Internal notes' => "Call \"Maya\" first.\nNew path: C:\\orders\\2025\\trp.xlsx",
				'Supplier'       => "Alpine Goods \"AG\"",
			)
		);
		$this->block_editor_save( $editor, array(), array( 'title' => 'Trail Runner Pro' ) );

		$this->assertSame(
			array(
				'notes'    => "Call \"Maya\" first.\nNew path: C:\\orders\\2025\\trp.xlsx",
				'supplier' => 'Alpine Goods "AG"',
			),
			staff_fields( $id )
		);
		$this->assertSame( $before, $this->rest_meta( $id ), 'Sidebar fields must not change when only the meta box was edited' );
		$this->assertSame( 'New "Pro" model', $this->rest_meta( $id )['_acme_badge'] );
	}

	public function test_repeated_saves_in_one_editor_session_keep_the_latest_values(): void {
		$id     = $this->fresh( 'city-backpack' );
		$editor = $this->open_block_editor( $id );

		$this->block_editor_save( $editor, array( '_acme_in_stock' => false, '_acme_price' => '85' ) );
		$this->block_editor_save( $editor, array( '_acme_featured' => true ) );
		$this->block_editor_save( $editor, array( '_acme_price' => '82.5' ) );

		$meta = $this->rest_meta( $id );
		$this->assertSame( '82.50', $meta['_acme_price'] );
		$this->assertFalse( $meta['_acme_in_stock'] );
		$this->assertTrue( $meta['_acme_featured'] );
		$this->assertSame( 'CBP-2', $meta['_acme_sku'] );
		$this->assertSame( array( 'notes' => 'Reorder in May.', 'supplier' => 'Urban Carry' ), staff_fields( $id ) );
		$this->assertFalse( acme_pf_is_in_stock( $id ) );
	}

	public function test_products_imported_from_1x_show_their_real_state(): void {
		$beanie = $this->rest_meta( product_id( 'wool-beanie' ) );
		$this->assertTrue( $beanie['_acme_in_stock'] );
		$this->assertTrue( $beanie['_acme_featured'] );

		$stove = $this->rest_meta( product_id( 'camp-stove' ) );
		$this->assertFalse( $stove['_acme_in_stock'] );
		$this->assertFalse( $stove['_acme_featured'] );
	}

	public function test_saving_an_imported_product_from_the_sidebar_keeps_its_state(): void {
		$id     = $this->fresh( 'camp-stove' );
		$editor = $this->open_block_editor( $id );
		// The sidebar sends the whole meta object it loaded, with the one change.
		$meta                = $this->rest_meta( $id );
		$meta['_acme_price'] = '49.90';
		$this->block_editor_save( $editor, $meta );

		$after = $this->rest_meta( $id );
		$this->assertSame( '49.90', $after['_acme_price'] );
		$this->assertFalse( $after['_acme_in_stock'] );
		$this->assertFalse( $after['_acme_featured'] );
		$this->assertFalse( acme_pf_is_in_stock( $id ) );
		$this->assertFalse( acme_pf_is_featured( $id ) );

		$id     = $this->fresh( 'wool-beanie' );
		$editor = $this->open_block_editor( $id );
		$meta   = $this->rest_meta( $id );
		$meta['_acme_badge'] = 'Bestseller "2025"';
		$this->block_editor_save( $editor, $meta );
		$this->assertTrue( acme_pf_is_in_stock( $id ) );
		$this->assertTrue( acme_pf_is_featured( $id ) );
		$this->assertSame( 'Bestseller "2025"', acme_pf_get_badge( $id ) );
	}

	public function test_rest_permissions_and_private_fields(): void {
		$intern = get_user_by( 'login', 'intern' )->ID;
		$login  = $this->login( $intern );
		$other  = product_id( 'trail-runner-pro' );
		$res    = $this->http( 'POST', "/wp-json/wp/v2/acme_product/$other", array( 'login' => $login, 'rest_nonce' => true, 'json' => true, 'body' => array( 'meta' => array( '_acme_price' => '1' ) ) ) );
		$this->assertContains( $res['status'], array( 401, 403 ) );
		$this->assertSame( '129.00', $this->rest_meta( $other )['_acme_price'] );

		$own = $this->fresh( 'intern-socks' );
		$res = $this->http( 'POST', "/wp-json/wp/v2/acme_product/$own", array( 'login' => $login, 'rest_nonce' => true, 'json' => true, 'body' => array( 'meta' => array( '_acme_price' => '21', '_acme_badge' => 'Pack of "3"' ) ) ) );
		$this->assertSame( 200, $res['status'], $res['body'] );
		$meta = $this->rest_meta( $own );
		$this->assertSame( '21.00', $meta['_acme_price'] );
		$this->assertSame( 'Pack of "3"', $meta['_acme_badge'] );

		$this->assertArrayNotHasKey( '_acme_internal_notes', $this->rest_meta( $other ) );
		$this->assertArrayNotHasKey( '_acme_supplier', $this->rest_meta( $other ) );
		$public = $this->http( 'GET', "/wp-json/wp/v2/acme_product/$other" );
		$this->assertStringNotContainsString( 'Maya', $public['body'] );
	}
}
