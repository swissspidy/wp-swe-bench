<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the components.
 */
final class Plugin {

	/**
	 * Installed version option.
	 */
	const VERSION_OPTION = 'acme_re_version';

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get / boot the plugin.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function boot() {
		load_plugin_textdomain( 'acme-real-estate', false, dirname( plugin_basename( ACME_RE_FILE ) ) . '/languages' );

		( new Post_Type() )->register();
		( new Shortcode() )->register();
		( new Rest() )->register();

		if ( is_admin() ) {
			( new Meta_Box() )->register();
		}

		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		( new Post_Type() )->register_post_type();
		flush_rewrite_rules();
		update_option( self::VERSION_OPTION, ACME_RE_VERSION );
	}

	/**
	 * Upgrade routine.
	 */
	public function maybe_upgrade() {
		if ( version_compare( get_option( self::VERSION_OPTION, '0' ), ACME_RE_VERSION, '>=' ) ) {
			return;
		}
		update_option( self::VERSION_OPTION, ACME_RE_VERSION );
	}

	/**
	 * Front-end CSS.
	 */
	public function assets() {
		wp_register_style( 'acme-re-search', ACME_RE_URL . 'assets/search.css', array(), ACME_RE_VERSION );
	}
}
