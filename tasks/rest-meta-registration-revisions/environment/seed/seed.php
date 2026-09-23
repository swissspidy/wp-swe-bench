<?php
/**
 * Seed the catalogue: products with 2.x structured specs, 1.x flat specs, both, none;
 * products with revisions from before specs were tracked; a buying guide using [acme_specs].
 */

use Acme\Specs\Frontend;

$user = static function ( $login ) {
	return get_user_by( 'login', $login )->ID;
};

$product = static function ( $slug, $title, $author, $content, $status = 'publish' ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'acme_product',
			'post_status'  => $status,
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_author'  => $author,
			'post_content' => '<!-- wp:paragraph --><p>' . $content . '</p><!-- /wp:paragraph -->',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	return $id;
};

$revise = static function ( $id, $content ) {
	wp_update_post(
		array(
			'ID'           => $id,
			'post_content' => '<!-- wp:paragraph --><p>' . $content . '</p><!-- /wp:paragraph -->',
		)
	);
};

// 2.x product with revisions from before specs were tracked.
$oak = $product( 'oak-desk', 'Oak Desk', $user( 'alice' ), 'Solid oak desk (2023 catalogue).' );
update_post_meta( $oak, '_acme_specs_dimensions', array( 'width' => 120.0, 'height' => 75.0, 'depth' => 60.0, 'unit' => 'cm' ) );
update_post_meta( $oak, '_acme_specs_materials', array( 'Oak', 'Steel' ) );
update_post_meta(
	$oak,
	'_acme_specs_certifications',
	array(
		array( 'code' => 'CE', 'issued' => '2019-03-01', 'expires' => '2029-03-01' ),
		array( 'code' => 'FSC', 'issued' => '2021-06-15' ),
	)
);
$revise( $oak, 'Solid oak desk with steel legs (2024 catalogue).' );
$revise( $oak, 'Solid oak desk with powder-coated steel legs.' );

// 1.x product (never re-saved since 2019).
$shelf = $product( 'steel-shelf', 'Steel Shelf', $user( 'bob' ), 'Industrial shelf (first edition).' );
update_post_meta( $shelf, '_acme_width', '90cm' );
update_post_meta( $shelf, '_acme_height', '180' );
update_post_meta( $shelf, '_acme_depth', '35,5' );
update_post_meta( $shelf, '_acme_materials', 'Steel, Powder coating, ' );
update_post_meta( $shelf, '_acme_certs', 'CE:2018-01-10|GS:12.05.2020:12.05.2025' );
$revise( $shelf, 'Industrial shelf, five boards.' );

// Re-saved with 2.x, but the 1.x fields were never cleaned up.
$lamp = $product( 'lamp-classic', 'Classic Lamp', $user( 'alice' ), 'Brass table lamp.' );
update_post_meta( $lamp, '_acme_specs_dimensions', array( 'width' => 20.0, 'height' => 45.0, 'depth' => 20.0, 'unit' => 'cm' ) );
update_post_meta( $lamp, '_acme_specs_materials', array( 'Brass', 'Linen' ) );
update_post_meta( $lamp, '_acme_width', '99' );
update_post_meta( $lamp, '_acme_height', '99' );
update_post_meta( $lamp, '_acme_depth', '99' );
update_post_meta( $lamp, '_acme_materials', 'Plastic' );
update_post_meta( $lamp, '_acme_certs', 'UL:2015-01-01' );

// 1.x product with messy data: height unknown, inches, one broken certification date.
$cabinet = $product( 'walnut-cabinet', 'Walnut Cabinet', $user( 'eddie' ), 'Mid-century cabinet.' );
update_post_meta( $cabinet, '_acme_width', '32' );
update_post_meta( $cabinet, '_acme_height', 'n/a' );
update_post_meta( $cabinet, '_acme_depth', '18' );
update_post_meta( $cabinet, '_acme_unit', 'inches' );
update_post_meta( $cabinet, '_acme_materials', 'Walnut veneer,MDF' );
update_post_meta( $cabinet, '_acme_certs', 'FSC:2020-02-30|UL:01.07.2022' );

// 1.x product in millimetres.
$stool = $product( 'bar-stool', 'Bar Stool', $user( 'bob' ), 'Stackable bar stool.' );
update_post_meta( $stool, '_acme_width', '420' );
update_post_meta( $stool, '_acme_height', '760' );
update_post_meta( $stool, '_acme_depth', '420' );
update_post_meta( $stool, '_acme_unit', 'mm' );
update_post_meta( $stool, '_acme_materials', 'Beech' );

// Draft with specs.
$chair = $product( 'draft-chair', 'Prototype Chair', $user( 'alice' ), 'Not announced yet.', 'draft' );
update_post_meta( $chair, '_acme_specs_dimensions', array( 'width' => 50.0, 'height' => 90.0, 'depth' => 55.0, 'unit' => 'cm' ) );
update_post_meta( $chair, '_acme_specs_materials', array( 'Secret alloy' ) );

// No specs at all.
$product( 'mystery-box', 'Mystery Box', $user( 'eddie' ), 'Contents vary.' );

// Buying guide embedding tables.
wp_insert_post(
	array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_name'    => 'desk-buying-guide',
		'post_title'   => 'Desk buying guide',
		'post_author'  => $user( 'eddie' ),
		'post_content' => "<!-- wp:paragraph --><p>Our favourites:</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->[acme_specs id=\"$oak\"]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[acme_specs id=\"$shelf\"]<!-- /wp:shortcode -->",
	)
);

// Production has warm table caches.
foreach ( array( $oak, $shelf, $lamp, $cabinet, $stool ) as $id ) {
	Frontend::render_table( $id );
}
