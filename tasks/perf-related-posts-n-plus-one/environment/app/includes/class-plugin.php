<?php
/**
 * Bootstrap: wires the components together.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class (singleton).
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Settings */
	public $settings;

	/** @var Engine */
	public $engine;

	/** @var Renderer */
	public $renderer;

	/** @var Views */
	public $views;

	/**
	 * Get the plugin instance.
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
	 * Create the services and register hooks.
	 */
	private function boot() {
		$this->settings = new Settings();
		$this->views    = new Views();
		$this->engine   = new Engine( $this->settings );
		$this->renderer = new Renderer( $this->settings, $this->views );

		$this->settings->register_hooks();
		$this->views->register_hooks();
		( new Content( $this->settings, $this->engine, $this->renderer ) )->register_hooks();
		( new Block( $this->settings, $this->engine, $this->renderer ) )->register_hooks();
		( new Rest( $this->settings, $this->engine, $this->renderer, $this->views ) )->register_hooks();
		( new Metabox( $this->engine ) )->register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this->views, 'maybe_upgrade' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-related', false, dirname( plugin_basename( ACME_RELATED_FILE ) ) . '/languages' );
	}

	/**
	 * Front-end stylesheet and the view-counter beacon.
	 */
	public function enqueue_assets() {
		wp_register_style( 'acme-related', ACME_RELATED_URL . 'assets/related.css', array(), ACME_RELATED_VERSION );
		if ( 'none' !== $this->settings->get( 'display' ) ) {
			wp_enqueue_style( 'acme-related' );
		}
		if ( is_singular( 'post' ) ) {
			wp_enqueue_script( 'acme-related-views', ACME_RELATED_URL . 'assets/views.js', array(), ACME_RELATED_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_localize_script(
				'acme-related-views',
				'acmeRelatedViews',
				array(
					'endpoint' => esc_url_raw( rest_url( 'acme-related/v1/views/' . get_queried_object_id() ) ),
				)
			);
		}
	}
}
