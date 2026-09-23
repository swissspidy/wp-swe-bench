<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the components.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Components.
	 *
	 * @var object[]
	 */
	private $components = array();

	/**
	 * Instance.
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
	 * Activation: register the post type and flush rewrite rules.
	 */
	public static function activate() {
		( new Post_Type() )->register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Register everything.
	 */
	public function boot() {
		$this->components = array(
			'post_type' => new Post_Type(),
			'meta'      => new Meta(),
			'shortcode' => new Shortcode(),
			'loop'      => new Query_Loop(),
			'blocks'    => new Blocks(),
			'rest'      => new Rest(),
			'admin'     => new Admin(),
			'editor'    => new Editor(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Get a component.
	 *
	 * @param string $key Key.
	 * @return object|null
	 */
	public function get( $key ) {
		return $this->components[ $key ] ?? null;
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-events-lite', false, dirname( plugin_basename( ACME_EVENTS_FILE ) ) . '/languages' );
	}
}
