<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the plugin.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Block integration.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Settings screen.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Get the instance.
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
	 * Register hooks.
	 */
	public function boot() {
		$this->block    = new Block();
		$this->settings = new Settings();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this->block, 'register' ) );
		add_action( 'admin_menu', array( $this->settings, 'add_page' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_end_styles' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FILE ), array( $this->settings, 'action_links' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-toc', false, dirname( plugin_basename( FILE ) ) . '/languages' );
	}

	/**
	 * Smooth scrolling for in-page TOC links (Settings → Table of Contents).
	 */
	public function front_end_styles() {
		$options = get_options();
		if ( empty( $options['smooth_scroll'] ) ) {
			return;
		}
		wp_register_style( 'acme-toc-smooth-scroll', false, array(), VERSION );
		wp_enqueue_style( 'acme-toc-smooth-scroll' );
		wp_add_inline_style( 'acme-toc-smooth-scroll', '@media (prefers-reduced-motion: no-preference){html{scroll-behavior:smooth}}' );
	}
}
