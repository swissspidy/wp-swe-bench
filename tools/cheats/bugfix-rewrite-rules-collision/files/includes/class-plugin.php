<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Docs
 */

namespace Acme\Docs;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components.
 */
class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Permalinks.
	 *
	 * @var Permalinks
	 */
	public $permalinks;

	/**
	 * Router.
	 *
	 * @var Router
	 */
	public $router;

	/**
	 * Navigation.
	 *
	 * @var Navigation
	 */
	public $navigation;

	/**
	 * Admin.
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * Gets (and boots) the plugin.
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
	protected function boot() {
		$this->permalinks = new Permalinks();
		$this->router     = new Router();
		$this->navigation = new Navigation();
		$this->admin      = new Admin();

		add_action( 'init', array( Post_Types::class, 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->permalinks->register();
		$this->router->register();
		$this->navigation->register();
		if ( is_admin() ) {
			$this->admin->register();
		}
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-docs', false, dirname( plugin_basename( ACME_DOCS_FILE ) ) . '/languages' );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		Post_Types::register();
		( new Router() )->add_rules();
		flush_rewrite_rules();
		update_option( Router::RULES_OPTION, Router::RULES_VERSION );
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
