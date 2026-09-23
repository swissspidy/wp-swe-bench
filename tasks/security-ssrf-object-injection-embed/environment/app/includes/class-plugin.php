<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private $booted = false;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Shortcode() )->register_hooks();
		( new Rest() )->register_hooks();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-link-previews', false, dirname( plugin_basename( ACME_LP_FILE ) ) . '/languages' );
	}
}
