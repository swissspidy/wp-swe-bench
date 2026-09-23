<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Faq
 */

namespace Acme\Faq;

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
	 * Blocks.
	 *
	 * @var Blocks
	 */
	public $blocks;

	/**
	 * Structured data.
	 *
	 * @var Schema
	 */
	public $schema;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	public $settings;

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
		$this->blocks   = new Blocks();
		$this->schema   = new Schema();
		$this->settings = new Settings();

		add_action( 'init', array( $this->blocks, 'register' ) );
		add_action( 'wp_head', array( $this->schema, 'print_json_ld' ) );
		add_action( 'admin_menu', array( $this->settings, 'add_page' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
	}
}
