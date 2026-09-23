<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the services together.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Product storage.
	 *
	 * @var Product_Repository
	 */
	public $products;

	/**
	 * Importer.
	 *
	 * @var Importer
	 */
	public $importer;

	/**
	 * Admin screens.
	 *
	 * @var Admin|null
	 */
	public $admin = null;

	/**
	 * Get (and boot) the plugin.
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
	 * Boot.
	 */
	private function boot() {
		load_plugin_textdomain( 'acme-importer', false, dirname( plugin_basename( ACME_IMPORTER_FILE ) ) . '/languages' );

		Installer::maybe_upgrade();

		$product_type = new Product_Type();
		$product_type->register();

		$this->products = new Product_Repository();
		$this->importer = new Importer( $this->products, new Row_Mapper() );

		if ( is_admin() ) {
			$this->admin = new Admin( $this->importer );
			$this->admin->register();
		}

		/**
		 * Fires once Acme Importer is loaded.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'acme_importer_loaded', $this );
	}
}
