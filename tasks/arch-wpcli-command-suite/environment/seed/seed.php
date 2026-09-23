<?php
/**
 * Seeds the production rule set, including rows written by 1.x.
 *
 * Run with `wp eval-file`.
 */

global $wpdb;
$table = $wpdb->prefix . 'acme_redirects';

$rules = array(
	// 1
	array( 'source' => '/old-about', 'target' => '/about/', 'match_type' => 'exact', 'status_code' => 301, 'priority' => 10, 'enabled' => 1, 'hits' => 12, 'last_hit' => '2026-08-30 09:12:00', 'note' => 'Site relaunch 2024', 'created_at' => '2024-03-01 10:00:00', 'updated_at' => '2024-03-01 10:00:00' ),
	// 2: 1.0 row (no leading slash, no match type, status 0, no priority / enabled flag).
	array( 'source' => 'old-contact', 'target' => '/contact/', 'match_type' => '', 'status_code' => 0, 'priority' => null, 'enabled' => null, 'hits' => 3, 'last_hit' => null, 'note' => '', 'created_at' => null, 'updated_at' => null ),
	// 3
	array( 'source' => '/shop', 'target' => '/store/*', 'match_type' => 'prefix', 'status_code' => 301, 'priority' => 20, 'enabled' => 1, 'hits' => 230, 'last_hit' => '2026-09-01 18:40:00', 'note' => 'Shop moved to /store', 'created_at' => '2025-01-10 08:00:00', 'updated_at' => '2025-01-10 08:00:00' ),
	// 4
	array( 'source' => '/shop/sale', 'target' => '/deals/', 'match_type' => 'prefix', 'status_code' => 302, 'priority' => 5, 'enabled' => 1, 'hits' => 17, 'last_hit' => '2026-08-12 12:00:00', 'note' => 'Sale section', 'created_at' => '2025-01-10 08:05:00', 'updated_at' => '2025-06-02 08:05:00' ),
	// 5: 1.2 regex row (no priority yet).
	array( 'source' => '^/blog/(\d{4})/(\d{2})/(.+?)/?$', 'target' => '/news/$3/', 'match_type' => 'regex', 'status_code' => 301, 'priority' => null, 'enabled' => null, 'hits' => 981, 'last_hit' => '2026-09-02 07:00:00', 'note' => '', 'created_at' => null, 'updated_at' => null ),
	// 6
	array( 'source' => '^/Products/(.*)$', 'target' => 'https://shop.example.com/p/$1', 'match_type' => 'regex', 'status_code' => 308, 'priority' => 15, 'enabled' => 1, 'hits' => 55, 'last_hit' => '2026-09-01 10:00:00', 'note' => 'Partner shop', 'created_at' => '2025-02-01 08:00:00', 'updated_at' => '2025-02-01 08:00:00' ),
	// 7
	array( 'source' => '/search?cat=5', 'target' => '/category/news/', 'match_type' => 'exact', 'status_code' => 301, 'priority' => 10, 'enabled' => 1, 'hits' => 4, 'last_hit' => '2026-07-01 10:00:00', 'note' => '', 'created_at' => '2025-02-01 08:00:00', 'updated_at' => '2025-02-01 08:00:00' ),
	// 8
	array( 'source' => '/retired-product', 'target' => '', 'match_type' => 'exact', 'status_code' => 410, 'priority' => 10, 'enabled' => 1, 'hits' => 9, 'last_hit' => '2026-08-01 10:00:00', 'note' => 'Discontinued', 'created_at' => '2025-03-01 08:00:00', 'updated_at' => '2025-03-01 08:00:00' ),
	// 9
	array( 'source' => '/promo', 'target' => '/deals/summer/', 'match_type' => 'exact', 'status_code' => 302, 'priority' => 10, 'enabled' => 1, 'hits' => 0, 'last_hit' => null, 'note' => 'Summer campaign', 'created_at' => '2026-06-01 08:00:00', 'updated_at' => '2026-06-01 08:00:00' ),
	// 10
	array( 'source' => '/disabled-page', 'target' => '/somewhere/', 'match_type' => 'exact', 'status_code' => 301, 'priority' => 10, 'enabled' => 0, 'hits' => 2, 'last_hit' => '2025-01-01 10:00:00', 'note' => 'Paused', 'created_at' => '2025-03-01 08:00:00', 'updated_at' => '2025-03-01 08:00:00' ),
	// 11: 1.x prefix row without enabled flag.
	array( 'source' => '/docs', 'target' => '/help/', 'match_type' => 'prefix', 'status_code' => 307, 'priority' => 50, 'enabled' => null, 'hits' => 77, 'last_hit' => '2026-09-01 11:00:00', 'note' => '', 'created_at' => null, 'updated_at' => null ),
	// 12
	array( 'source' => '^/docs/v1/(.*)', 'target' => '/help/legacy/$1', 'match_type' => 'regex', 'status_code' => 301, 'priority' => 40, 'enabled' => 1, 'hits' => 8, 'last_hit' => '2026-05-01 10:00:00', 'note' => 'Old API docs', 'created_at' => '2025-04-01 08:00:00', 'updated_at' => '2025-04-01 08:00:00' ),
	// 13
	array( 'source' => '/events', 'target' => '/calendar/', 'match_type' => 'prefix', 'status_code' => 301, 'priority' => 10, 'enabled' => 1, 'hits' => 31, 'last_hit' => '2026-08-20 10:00:00', 'note' => '', 'created_at' => '2025-05-01 08:00:00', 'updated_at' => '2025-05-01 08:00:00' ),
	// 14
	array( 'source' => '^/events/(.*)$', 'target' => '/whats-on/$1', 'match_type' => 'regex', 'status_code' => 301, 'priority' => 10, 'enabled' => 1, 'hits' => 0, 'last_hit' => null, 'note' => 'Never wins against #13', 'created_at' => '2025-05-01 08:01:00', 'updated_at' => '2025-05-01 08:01:00' ),
	// 15
	array( 'source' => '/team', 'target' => '/about/team/', 'match_type' => 'exact', 'status_code' => 301, 'priority' => 10, 'enabled' => 1, 'hits' => 42, 'last_hit' => '2026-09-03 10:00:00', 'note' => '', 'created_at' => '2025-05-02 08:00:00', 'updated_at' => '2025-05-02 08:00:00' ),
	// 16: 1.2 row, disabled, no priority, legacy source.
	array( 'source' => 'legacy-faq/', 'target' => '/help/faq/', 'match_type' => 'exact', 'status_code' => 302, 'priority' => null, 'enabled' => 0, 'hits' => 1, 'last_hit' => '2023-01-01 10:00:00', 'note' => '', 'created_at' => null, 'updated_at' => null ),
	// 17
	array( 'source' => '/careers', 'target' => 'https://jobs.example.org/acme', 'match_type' => 'exact', 'status_code' => 301, 'priority' => 10, 'enabled' => 1, 'hits' => 64, 'last_hit' => '2026-09-02 10:00:00', 'note' => 'Hosted job board', 'created_at' => '2025-06-01 08:00:00', 'updated_at' => '2025-06-01 08:00:00' ),
	// 18
	array( 'source' => '^/(\d+)$', 'target' => '/?p=$1', 'match_type' => 'regex', 'status_code' => 301, 'priority' => 90, 'enabled' => 1, 'hits' => 5, 'last_hit' => '2026-02-01 10:00:00', 'note' => 'Short links', 'created_at' => '2025-06-01 08:00:00', 'updated_at' => '2025-06-01 08:00:00' ),
);

foreach ( $rules as $row ) {
	if ( false === $wpdb->insert( $table, $row ) ) {
		WP_CLI::error( 'Seeding failed: ' . $wpdb->last_error );
	}
}

delete_transient( 'acme_redirects_rules' );
delete_option( 'acme_cdn_purge_log' );
WP_CLI::success( count( $rules ) . ' rules seeded.' );
