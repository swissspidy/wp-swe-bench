<?php
/**
 * Seeds the shop content (runs with `wp eval-file`). Values are written raw so that they are
 * stored byte for byte as they were on the old host.
 */

// phpcs:ignoreFile

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$charset = $wpdb->get_charset_collate();
dbDelta(
	"CREATE TABLE {$wpdb->prefix}acme_redirects (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source varchar(255) NOT NULL DEFAULT '',
		target text NOT NULL,
		hits bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id)
	) $charset;"
);

// Users.
$anna = wp_insert_user( array( 'user_login' => 'shopkeeper', 'user_email' => 'anna@shop.test', 'user_pass' => 'password', 'role' => 'editor', 'display_name' => 'Anna' ) );

// Terms.
$news = wp_insert_term( 'News', 'category', array( 'slug' => 'news' ) );

// Posts.
$ids   = array();
$posts = array(
	array( 'classic-shop-page', 'page', 'Shop' ),
	array( 'summer-sale', 'post', 'Summer sale' ),
	array( 'home', 'page', 'Home' ),
	array( 'about-us', 'page', 'About us' ),
);
foreach ( $posts as $p ) {
	$ids[ $p[1] . ':' . $p[0] ] = wp_insert_post( array( 'post_name' => $p[0], 'post_type' => $p[1], 'post_title' => $p[2], 'post_status' => 'publish', 'post_author' => 1 ), true );
}
wp_set_post_categories( $ids['post:summer-sale'], array( $news['term_id'] ) );

$template = wp_insert_post( array( 'post_name' => 'index', 'post_type' => 'wp_template', 'post_title' => 'Index', 'post_status' => 'publish', 'post_author' => 1, 'tax_input' => array() ), true );
wp_set_object_terms( $template, get_stylesheet(), 'wp_theme' );
$ids['wp_template:index'] = $template;

$comment = wp_insert_comment( array( 'comment_post_ID' => $ids['post:summer-sale'], 'comment_author' => 'Ben', 'comment_author_email' => 'ben@example.org', 'comment_content' => 'x', 'comment_approved' => 1 ) );

// Other rows that must never be touched: our own history mentions the old URL.
update_option(
	'acme_migrate_history',
	array(
		array( 'time' => 1717200000, 'user' => 1, 'search' => 'http://staging.old-shop.test', 'replace' => 'http://old-shop.test', 'dry_run' => false, 'rows' => 42, 'replacements' => 57 ),
	),
	false
);

$fixtures = json_decode( file_get_contents( __DIR__ . '/content.json' ), true );
foreach ( $fixtures as $f ) {
	switch ( $f['table'] ) {
		case 'posts':
			$id = $ids[ $f['post_type'] . ':' . $f['post_name'] ];
			$wpdb->update( $wpdb->posts, array( $f['column'] => $f['input'] ), array( 'ID' => $id ) );
			break;
		case 'postmeta':
			$id = $ids[ $f['post_type'] . ':' . $f['post_name'] ];
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $f['meta_key'], 'meta_value' => $f['input'] ) );
			break;
		case 'options':
			$wpdb->delete( $wpdb->options, array( 'option_name' => $f['option_name'] ) );
			$wpdb->insert( $wpdb->options, array( 'option_name' => $f['option_name'], 'option_value' => $f['input'], 'autoload' => $f['autoload'] ) );
			break;
		case 'term_taxonomy':
			$wpdb->update( $wpdb->term_taxonomy, array( 'description' => $f['input'] ), array( 'term_id' => $news['term_id'], 'taxonomy' => 'category' ) );
			break;
		case 'termmeta':
			$wpdb->insert( $wpdb->termmeta, array( 'term_id' => $news['term_id'], 'meta_key' => $f['meta_key'], 'meta_value' => $f['input'] ) );
			break;
		case 'users':
			$wpdb->update( $wpdb->users, array( 'user_url' => $f['input'] ), array( 'ID' => $anna ) );
			break;
		case 'usermeta':
			$wpdb->update( $wpdb->usermeta, array( 'meta_value' => $f['input'] ), array( 'user_id' => $anna, 'meta_key' => $f['meta_key'] ) );
			break;
		case 'comments':
			$wpdb->update( $wpdb->comments, array( $f['column'] => $f['input'] ), array( 'comment_ID' => $comment ) );
			break;
		case 'acme_redirects':
			$wpdb->insert( $wpdb->prefix . 'acme_redirects', array( 'source' => $f['source'], 'target' => $f['input'], 'hits' => 3 ) );
			break;
		default:
			WP_CLI::error( 'Unknown fixture table ' . $f['table'] );
	}
}

// A redirect that has nothing to do with the old host.
$wpdb->insert( $wpdb->prefix . 'acme_redirects', array( 'source' => '/jobs/', 'target' => 'https://careers.example.org/acme', 'hits' => 12 ) );

wp_cache_flush();
WP_CLI::success( 'Seeded ' . count( $fixtures ) . ' fixtures.' );
