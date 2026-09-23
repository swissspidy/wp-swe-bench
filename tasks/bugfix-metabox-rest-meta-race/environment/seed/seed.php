<?php
/**
 * Seeds the shop's products (runs with `wp eval-file`). Meta is written raw, as stored by the
 * current (2.x) and the old (1.x) versions of the plugin.
 */

// phpcs:ignoreFile

global $wpdb;

$users = array(
	'shopmanager' => 'editor',
	'intern'      => 'author',
	'customer'    => 'subscriber',
);
foreach ( $users as $login => $role ) {
	wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'password', 'user_email' => "$login@shop.test", 'role' => $role, 'display_name' => ucfirst( $login ) ) );
}
$intern = get_user_by( 'login', 'intern' )->ID;

$products = array(
	'trail-runner-pro' => array(
		'title'   => 'Trail Runner Pro',
		'content' => "<!-- wp:paragraph -->\n<p>Our lightest trail shoe.</p>\n<!-- /wp:paragraph -->",
		'meta'    => array(
			'_acme_price'          => '129.00',
			'_acme_sku'            => 'TRP-01',
			'_acme_badge'          => 'New "Pro" model',
			'_acme_featured'       => '1',
			'_acme_in_stock'       => '1',
			'_acme_internal_notes' => "Supplier contact: \"Maya\"\nOrders: C:\\orders\\trp.xlsx",
			'_acme_supplier'       => 'Alpine Goods',
		),
	),
	'city-backpack'    => array(
		'title'   => 'City Backpack',
		'content' => "<!-- wp:paragraph -->\n<p>20 litres, laptop sleeve.</p>\n<!-- /wp:paragraph -->",
		'meta'    => array(
			'_acme_price'          => '79.50',
			'_acme_sku'            => 'CBP-2',
			'_acme_badge'          => '',
			'_acme_featured'       => '',
			'_acme_in_stock'       => '1',
			'_acme_internal_notes' => 'Reorder in May.',
			'_acme_supplier'       => 'Urban Carry',
		),
	),
	'rain-jacket'      => array(
		'title'   => 'Rain Jacket',
		'content' => "<!-- wp:paragraph -->\n<p>Fully taped seams.</p>\n<!-- /wp:paragraph -->",
		'meta'    => array(
			'_acme_price'          => '189.00',
			'_acme_sku'            => 'RJ-7',
			'_acme_badge'          => '15% off',
			'_acme_featured'       => '1',
			'_acme_in_stock'       => '',
			'_acme_internal_notes' => 'Back in stock in week 42.',
			'_acme_supplier'       => 'Nordic Wear',
		),
	),
	// Imported from the 1.x shop: legacy checkbox values.
	'wool-beanie'      => array(
		'title'   => 'Wool Beanie',
		'content' => '<p>Merino wool.</p>',
		'meta'    => array(
			'_acme_price'          => '24.00',
			'_acme_sku'            => 'WB-1',
			'_acme_badge'          => 'Bestseller',
			'_acme_featured'       => 'on',
			'_acme_in_stock'       => 'yes',
			'_acme_internal_notes' => 'Imported from the old shop.',
			'_acme_supplier'       => 'Highland Knit',
		),
	),
	'camp-stove'       => array(
		'title'   => 'Camp Stove',
		'content' => '<p>Compact gas stove.</p>',
		'meta'    => array(
			'_acme_price'          => '59.90',
			'_acme_sku'            => 'CS-3',
			'_acme_badge'          => '',
			'_acme_featured'       => 'off',
			'_acme_in_stock'       => 'no',
			'_acme_internal_notes' => 'Discontinued by the supplier?',
			'_acme_supplier'       => 'Fire & Co',
		),
	),
	'intern-socks'     => array(
		'title'   => 'Hiking Socks',
		'author'  => $intern,
		'status'  => 'draft',
		'content' => '<p>Three pairs.</p>',
		'meta'    => array(
			'_acme_price'    => '19.00',
			'_acme_sku'      => 'HS-3',
			'_acme_in_stock' => '1',
		),
	),
);

foreach ( $products as $slug => $p ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'acme_product',
			'post_name'   => $slug,
			'post_title'  => $p['title'],
			'post_status' => $p['status'] ?? 'publish',
			'post_author' => $p['author'] ?? 1,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id );
	}
	$wpdb->update( $wpdb->posts, array( 'post_content' => $p['content'] ), array( 'ID' => $id ) );
	foreach ( $p['meta'] as $key => $value ) {
		$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $key, 'meta_value' => $value ) );
	}
	clean_post_cache( $id );
	WP_CLI::log( "$slug => $id" );
}

update_option( 'acme_pf_settings', array( 'currency' => '$', 'editor' => 'block' ) );
