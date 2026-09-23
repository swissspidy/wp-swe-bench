<?php
/**
 * Quick Edit and Bulk Edit on the products list.
 */

use WPSB\ProductFields\Base;
use function WPSB\ProductFields\product_id;
use function WPSB\ProductFields\staff_fields;

class ListTableTest extends Base {

	public function test_quick_edit_changes_price_and_stock_only(): void {
		$id     = $this->fresh( 'trail-runner-pro' );
		$before = $this->rest_meta( $id );
		$staff  = staff_fields( $id );

		$res = $this->quick_edit( $id, array( 'Price' => '119', 'In stock' => false ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'Trail Runner Pro', $res['body'] );

		$meta = $this->rest_meta( $id );
		$this->assertSame( '119.00', $meta['_acme_price'] );
		$this->assertFalse( $meta['_acme_in_stock'], 'Unchecking "In stock" in Quick Edit must be saved' );
		foreach ( array( '_acme_sku', '_acme_badge', '_acme_featured' ) as $key ) {
			$this->assertSame( $before[ $key ], $meta[ $key ], "$key must not change" );
		}
		$this->assertSame( $staff, staff_fields( $id ) );
	}

	public function test_quick_edit_of_the_title_keeps_all_product_fields(): void {
		$id     = $this->fresh( 'city-backpack' );
		$before = $this->rest_meta( $id );
		$staff  = staff_fields( $id );

		$res = $this->quick_edit( $id, array() );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( $before, $this->rest_meta( $id ) );
		$this->assertSame( $staff, staff_fields( $id ) );
	}

	public function test_quick_edit_can_put_a_product_back_in_stock(): void {
		$id  = $this->fresh( 'rain-jacket' );
		$res = $this->quick_edit( $id, array( 'In stock' => true ) );
		$this->assertSame( 200, $res['status'] );
		$meta = $this->rest_meta( $id );
		$this->assertTrue( $meta['_acme_in_stock'] );
		$this->assertSame( '189.00', $meta['_acme_price'] );
		$this->assertSame( '15% off', $meta['_acme_badge'] );
		$this->assertSame( array( 'notes' => 'Back in stock in week 42.', 'supplier' => 'Nordic Wear' ), staff_fields( $id ) );
	}

	public function test_bulk_edit_no_change_leaves_products_as_they_are(): void {
		$ids    = array( $this->fresh( 'trail-runner-pro' ), $this->fresh( 'rain-jacket' ), $this->fresh( 'city-backpack' ) );
		$before = array_map( fn( $id ) => $this->rest_meta( $id ), $ids );
		$staff  = array_map( fn( $id ) => staff_fields( $id ), $ids );

		$this->bulk_edit( $ids, array( 'Featured product' => 'Yes', 'In stock' => '— No Change —' ) );

		foreach ( $ids as $i => $id ) {
			$meta     = $this->rest_meta( $id );
			$expected = $before[ $i ];
			$expected['_acme_featured'] = true;
			$this->assertSame( $expected, $meta, "Product $id after Bulk Edit" );
			$this->assertSame( $staff[ $i ], staff_fields( $id ) );
		}
	}

	public function test_bulk_edit_marks_products_out_of_stock(): void {
		$ids    = array( $this->fresh( 'trail-runner-pro' ), $this->fresh( 'city-backpack' ) );
		$before = array_map( fn( $id ) => $this->rest_meta( $id ), $ids );

		$this->bulk_edit( $ids, array( 'In stock' => 'No' ) );

		foreach ( $ids as $i => $id ) {
			$expected                   = $before[ $i ];
			$expected['_acme_in_stock'] = false;
			$this->assertSame( $expected, $this->rest_meta( $id ) );
		}
	}

	public function test_list_columns(): void {
		$page = $this->get_page( '/wp-admin/edit.php?post_type=acme_product' );
		$xp   = WPSB\ProductFields\dom( $page['body'] );
		$row  = static fn( $slug ) => WPSB\ProductFields\text( WPSB\ProductFields\first( $xp, "//tr[@id='post-" . product_id( $slug ) . "']" ) );
		$this->assertStringContainsString( '$129.00', $row( 'trail-runner-pro' ) );
		$this->assertStringContainsString( 'TRP-01', $row( 'trail-runner-pro' ) );
		$this->assertStringContainsString( 'Out of stock', $row( 'camp-stove' ) );
		$this->assertStringContainsString( 'In stock', $row( 'wool-beanie' ) );
	}
}
