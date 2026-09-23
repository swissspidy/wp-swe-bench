<?php
/**
 * Seeds the production catalog (run with `wp eval-file`).
 *
 * - 1.x products: SKUs stored as typed (lower-case), prices in cents.
 * - Draft, pending, private and trashed products.
 */

$products = array(
	// SKU (as stored), title, status, price (cents), stock, categories.
	array( 'acme-1001', 'Claw hammer 16oz', 'publish', 1299, 40, array( 'Tools' ) ),
	array( 'acme-1002', 'Sledge hammer 4kg', 'publish', 3450, 8, array( 'Tools' ) ),
	array( 'Acme-1003', 'Rubber mallet', 'publish', 899, '', array( 'Tools' ) ),
	array( 'acme-1004 ', 'Tack hammer', 'publish', 750, 12, array( 'Tools' ) ),
	array( 'acme-1005', 'Hatchet', 'private', 2490, 3, array( 'Tools', 'Garden' ) ),
	array( 'DRAFT-2001', 'Garden hose 25m (draft)', 'draft', 2999, 0, array( 'Garden' ) ),
	array( 'DRAFT-2002', 'Hose reel (draft)', 'draft', 4999, 0, array( 'Garden' ) ),
	array( 'DRAFT-2003', 'Sprinkler (draft)', 'draft', 1599, 0, array( 'Garden' ) ),
	array( 'PEND-3001', 'Pruning shears (pending)', 'pending', 1899, 25, array( 'Garden' ) ),
	array( 'PRIV-4001', 'Workbench (private)', 'private', 18900, 2, array( 'Tools' ) ),
	array( 'TRASH-5001', 'Old catalog item', 'trash', 100, 0, array() ),
);
for ( $i = 1; $i <= 20; $i++ ) {
	$products[] = array( sprintf( 'SCR-%04d', $i ), sprintf( 'Screws %d mm (100 pcs)', 10 + $i ), 'publish', 300 + $i * 10, 100 + $i, array( 'Fasteners' ) );
}

foreach ( $products as list( $sku, $title, $status, $price, $stock, $cats ) ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'acme_product',
			'post_title'   => $title,
			'post_status'  => 'trash' === $status ? 'publish' : $status,
			'post_content' => '',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( $id, '_acme_sku', $sku );
	update_post_meta( $id, '_acme_price', $price );
	update_post_meta( $id, '_acme_stock', $stock );
	if ( $cats ) {
		wp_set_object_terms( $id, $cats, 'acme_product_cat' );
	}
	if ( 'trash' === $status ) {
		wp_trash_post( $id );
	}
}

update_option(
	'acme_importer_last_run',
	array(
		'file'     => 'supplier-2026-08.csv',
		'finished' => strtotime( '2026-08-30 10:00:00 UTC' ),
		'user'     => 1,
		'created'  => 20,
		'updated'  => 0,
		'skipped'  => 1,
		'failed'   => 0,
		'errors'   => array(),
	),
	false
);

WP_CLI::success( count( $products ) . ' products seeded.' );
