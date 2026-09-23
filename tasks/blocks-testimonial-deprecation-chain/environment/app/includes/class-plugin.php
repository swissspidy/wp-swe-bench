<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Testimonials
 */

namespace Acme\Testimonials;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Block registration.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Review structured data.
	 *
	 * @var Schema
	 */
	public $schema;

	/**
	 * Get the plugin instance.
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
	 * Hook everything up.
	 */
	public function boot() {
		$this->block  = new Block();
		$this->schema = new Schema();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->block->register_hooks();
		$this->schema->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acme-testimonials', CLI::class );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-testimonials', false, dirname( plugin_basename( ACME_TESTIMONIALS_FILE ) ) . '/languages' );
	}
}
