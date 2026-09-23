<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything up.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	public function boot() {
		$post_types = new Post_Types();
		$block      = new Block();
		$shortcode  = new Shortcode();
		$settings   = new Settings();

		add_action( 'init', array( $post_types, 'register' ) );
		add_action( 'init', array( $block, 'register' ) );
		add_action( 'init', array( $shortcode, 'register' ) );
		add_action( 'admin_menu', array( $settings, 'add_page' ) );
		add_action( 'admin_init', array( $settings, 'register' ) );
	}
}
