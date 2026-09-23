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
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'exclude_hidden_authors' ) );
	}

	/**
	 * Members whose profile is not public are left out of the users sitemap too.
	 *
	 * @param array $args WP_User_Query args.
	 * @return array
	 */
	public function exclude_hidden_authors( $args ) {
		$hidden = array();
		foreach ( Members::all_ids() as $id ) {
			if ( ! Visibility::can_view_profile( $id, 0 ) ) {
				$hidden[] = $id;
			}
		}
		if ( $hidden ) {
			$args['exclude'] = array_merge( isset( $args['exclude'] ) ? (array) $args['exclude'] : array(), $hidden );
		}
		return $args;
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
