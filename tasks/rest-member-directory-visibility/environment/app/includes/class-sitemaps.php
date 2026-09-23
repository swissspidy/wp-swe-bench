<?php
/**
 * Member profile pages in the XML sitemap (wp-sitemap-acmemembers-1.xml).
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the sitemap provider.
 */
class Sitemaps {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_provider' ), 20 );
	}

	/**
	 * Register the provider (core sitemaps must be enabled).
	 */
	public function register_provider() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}
		require_once __DIR__ . '/class-sitemap-provider.php';
		wp_register_sitemap_provider( Sitemap_Provider::NAME, new Sitemap_Provider() );
	}
}
