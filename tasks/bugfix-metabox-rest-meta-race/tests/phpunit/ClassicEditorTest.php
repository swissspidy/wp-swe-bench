<?php
/**
 * Saving products in the classic editor (Settings → Product fields → Classic editor).
 */

use WPSB\ProductFields\Base;
use function WPSB\ProductFields\control_by_label;
use function WPSB\ProductFields\first;
use function WPSB\ProductFields\product_id;
use function WPSB\ProductFields\set_by_label;
use function WPSB\ProductFields\staff_fields;

class ClassicEditorTest extends Base {

	protected function setUp(): void {
		parent::setUp();
		$this->use_editor( 'classic' );
	}

	public function test_the_classic_editor_saves_every_field_exactly(): void {
		$id = $this->fresh( 'trail-runner-pro' );
		$this->classic_save(
			$id,
			static function ( $xp, $form, $pairs ) {
				foreach (
					array(
						'Price'            => '99.9',
						'SKU'              => 'trp-03',
						'Badge text'       => '12" "Rain" \\ Snow',
						'Featured product' => false,
						'In stock'         => false,
						'Internal notes'   => 'Say "hi" to C:\\temp\\new',
						'Supplier'         => "O'Neill & Sons",
					) as $label => $value
				) {
					$pairs = set_by_label( $xp, $form, $pairs, $label, $value );
				}
				return $pairs;
			}
		);

		$meta = $this->rest_meta( $id );
		$this->assertSame( '99.90', $meta['_acme_price'] );
		$this->assertSame( 'TRP-03', $meta['_acme_sku'] );
		$this->assertSame( '12" "Rain" \\ Snow', $meta['_acme_badge'] );
		$this->assertFalse( $meta['_acme_featured'], 'Unchecking "Featured product" must be saved' );
		$this->assertFalse( $meta['_acme_in_stock'], 'Unchecking "In stock" must be saved' );
		$this->assertSame( array( 'notes' => 'Say "hi" to C:\\temp\\new', 'supplier' => "O'Neill & Sons" ), staff_fields( $id ) );
		$this->assertFalse( acme_pf_is_in_stock( $id ) );
	}

	public function test_saving_without_changes_keeps_every_value(): void {
		$id     = $this->fresh( 'trail-runner-pro' );
		$before = $this->rest_meta( $id );
		$staff  = staff_fields( $id );
		$this->classic_save( $id, static fn( $xp, $form, $pairs ) => $pairs );
		$this->classic_save( $id, static fn( $xp, $form, $pairs ) => $pairs );
		$this->assertSame( $before, $this->rest_meta( $id ) );
		$this->assertSame( $staff, staff_fields( $id ) );
		$this->assertSame( 'New "Pro" model', $this->rest_meta( $id )['_acme_badge'] );
	}

	public function test_checking_boxes_is_saved(): void {
		$id = $this->fresh( 'rain-jacket' );
		$this->classic_save( $id, static fn( $xp, $form, $pairs ) => set_by_label( $xp, $form, $pairs, 'In stock', true ) );
		$meta = $this->rest_meta( $id );
		$this->assertTrue( $meta['_acme_in_stock'] );
		$this->assertTrue( $meta['_acme_featured'] );
		$this->assertTrue( acme_pf_is_in_stock( $id ) );
	}

	public function test_imported_products_show_and_keep_their_state(): void {
		$id   = $this->fresh( 'camp-stove' );
		$page = $this->get_page( "/wp-admin/post.php?post=$id&action=edit" );
		$xp   = WPSB\ProductFields\dom( $page['body'] );
		$form = first( $xp, "//form[@id='post']" );
		$this->assertFalse( control_by_label( $xp, $form, 'In stock' )->hasAttribute( 'checked' ) );
		$this->assertFalse( control_by_label( $xp, $form, 'Featured product' )->hasAttribute( 'checked' ) );

		$this->classic_save( $id, static fn( $xp, $form, $pairs ) => set_by_label( $xp, $form, $pairs, 'Price', '45' ) );
		$this->assertFalse( acme_pf_is_in_stock( $id ) );
		$this->assertFalse( acme_pf_is_featured( $id ) );
		$this->assertSame( '45.00', WPSB\ProductFields\raw_meta( $id, '_acme_price' ) );
	}
}
