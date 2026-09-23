<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

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
	 * Components.
	 *
	 * @var object[]
	 */
	public $components = array();

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
		$this->components = array(
			'post_type'   => new Post_Type(),
			'cache'       => new Term_Cache(),
			'shortcodes'  => new Shortcodes(),
			'rest'        => new Rest(),
			'index_block' => new Index_Block(),
			'assets'      => new Assets(),
			'renderer'    => new Renderer(),
			'editor'      => new Editor(),
		);
		foreach ( $this->components as $component ) {
			$component->register_hooks();
		}
		add_action( 'init', array( $this, 'load_textdomain' ) );
		register_activation_hook( ACME_GLOSSARY_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-glossary', false, dirname( plugin_basename( ACME_GLOSSARY_FILE ) ) . '/languages' );
	}

	/**
	 * Flush rewrite rules for the glossary archive on activation.
	 */
	public function activate() {
		$this->components['post_type']->register();
		flush_rewrite_rules();
	}
}
