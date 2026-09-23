<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

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
	 * Components by key.
	 *
	 * @var array<string, object>
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
	 * Get a component (e.g. to unhook it from a theme).
	 *
	 * @param string $key Component key.
	 * @return object|null
	 */
	public function get( $key ) {
		return $this->components[ $key ] ?? null;
	}

	/**
	 * Register everything.
	 */
	public function boot() {
		$this->components = array(
			'handler'     => new Handler(),
			'content'     => new Content(),
			'shortcode'   => new Shortcode(),
			'block'       => new Block(),
			'block_hooks' => new Block_Hooks(),
			'settings'    => new Settings(),
			'subscribers' => new Admin_Subscribers(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}

		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-newsletter', false, dirname( plugin_basename( ACME_NEWSLETTER_FILE ) ) . '/languages' );
	}

	/**
	 * Widget.
	 */
	public function register_widget() {
		register_widget( Widget::class );
	}

	/**
	 * The shortcode, widget and automatic form use the block's stylesheet too.
	 */
	public function enqueue_styles() {
		if ( is_singular() || is_active_widget( false, false, 'acme_newsletter' ) ) {
			wp_enqueue_style( 'acme-newsletter-signup-style' );
		}
	}
}
