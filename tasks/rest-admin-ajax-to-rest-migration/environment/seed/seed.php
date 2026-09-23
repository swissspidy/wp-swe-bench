<?php
/**
 * Seed ~45 inventory items, including 2.x-era rows (no updated date), a legacy negative stock,
 * names with commas/quotes/unicode and formula-like values from a supplier import.
 */

use Acme\Inventory\Items;

global $wpdb;
$table = Items::table();
$n     = 0;
$item  = static function ( $sku, $name, $stock, $threshold = 5, $location = '', $updated = null ) use ( $wpdb, $table, &$n ) {
	$n++;
	$wpdb->insert(
		$table,
		array(
			'sku'                 => $sku,
			'name'                => $name,
			'stock'               => $stock,
			'low_stock_threshold' => $threshold,
			'location'            => $location,
			'updated_at'          => null === $updated ? gmdate( 'Y-m-d H:i:s', strtotime( '2026-08-01 09:00:00 UTC' ) + $n * 3600 ) : $updated,
			'updated_by'          => 1,
		)
	);
	return $wpdb->insert_id;
};

// Hand-picked items (tests refer to them by SKU).
$item( 'MUG-001', 'Classic Mug', 40, 10, 'A-01' );
$item( 'MUG-002', 'Travel Mug', 12, 10, 'A-02' );
$item( 'MUG-003', 'Mug, large', 7, 5, 'A-03' );
$item( 'MUG-004', 'Espresso Mug "Tiny"', 3, 5, 'A-04' );
$item( 'TSH-001', 'Logo T-Shirt', 25, 5, 'B-01' );
$item( 'TSH-002', 'Vintage T-Shirt', 6, 5, 'B-02' );
$item( 'PST-001', 'Mountain Poster', 18, 3, 'C-01' );
$item( 'PST-002', '12" Ocean Poster', 0, 3, 'C-02' );
$item( 'KIT-001', 'Crème brûlée torch', 9, 2, 'D-01' );
$item( 'LEG-001', 'Sticker Pack (2.x)', 14, 5, 'E-01', '0000-00-00 00:00:00' );
$item( 'LEG-002', 'Gift Card Sleeve (2.x)', -3, 0, 'E-02', '0000-00-00 00:00:00' );
$item( 'IMP-001', '=HYPERLINK("http://example.com/x","Click me")', 5, 1, '@SUM(A1:A9)' );
$item( 'IMP-002', '+Plus Bundle', 11, 2, '-B-9' );

// Filler stock.
$names = array( 'Notebook', 'Pen Set', 'Tote Bag', 'Water Bottle', 'Keychain', 'Cap', 'Socks', 'Hoodie', 'Sticker Sheet', 'Pin Badge', 'Lanyard', 'Coaster', 'Magnet', 'Postcard', 'Calendar', 'Apron' );
for ( $i = 1; $i <= 32; $i++ ) {
	$base = $names[ ( $i - 1 ) % count( $names ) ];
	$item( sprintf( 'GEN-%03d', $i ), $base . ' ' . chr( 64 + ( ( $i - 1 ) % 26 ) + 1 ) . $i, 20 + ( $i * 7 ) % 60, 5, sprintf( 'F-%02d', $i ) );
}

echo "seeded {$n} items\n";
