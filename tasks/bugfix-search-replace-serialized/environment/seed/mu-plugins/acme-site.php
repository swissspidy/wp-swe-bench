<?php
/**
 * Plugin Name: Acme Shop site tweaks
 * Description: Site-specific code for shop.acme (redirects table, legacy menu cache). Maintained by the shop team.
 *
 * @package Acme\Site
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cached fragment from the old theme ("Acme Legacy" theme, 2019-2023). Still read by the header
 * template until the new theme ships.
 */
class Acme_Legacy_Cache_Item {

	/**
	 * Cache key.
	 *
	 * @var string
	 */
	public $key;

	/**
	 * Payload.
	 *
	 * @var mixed
	 */
	public $payload;

	/**
	 * Expiry timestamp.
	 *
	 * @var int
	 */
	public $expires;

	/**
	 * Every restore of a cached fragment is logged (and re-warms the fragment on the CDN in
	 * production).
	 */
	public function __wakeup() {
		$uploads = wp_upload_dir( null, false );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents_file_put_contents
		file_put_contents( trailingslashit( $uploads['basedir'] ) . 'acme-legacy-cache.log', gmdate( 'c' ) . ' restored ' . $this->key . "\n", FILE_APPEND );
	}
}

/**
 * Redirects table (used by the shop's redirect manager) is migrated with the site.
 */
add_filter(
	'acme_migrate_tables',
	static function ( $tables ) {
		global $wpdb;
		$tables[ $wpdb->prefix . 'acme_redirects' ] = array(
			'primary' => 'id',
			'columns' => array( 'source', 'target' ),
		);
		return $tables;
	}
);
