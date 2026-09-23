<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's components together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Components, keyed by name. Other plugins occasionally reach into these
	 * (e.g. to remove the content filter), so the keys are considered public.
	 *
	 * @var array<string, object>
	 */
	public $components = array();

	/**
	 * Returns the plugin instance.
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
	 * Registers all hooks.
	 */
	public function boot() {
		$this->components = array(
			'post_types'      => new Post_Types(),
			'settings'        => new Settings(),
			'content_filter'  => new Content_Filter(),
			'template_loader' => new Template_Loader(),
			'blocks'          => new Blocks(),
		);

		foreach ( $this->components as $component ) {
			$component->register();
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-courses', false, dirname( plugin_basename( ACME_COURSES_FILE ) ) . '/languages' );
	}

	/**
	 * Front-end stylesheet (summary box, course cards, enroll button).
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'acme-courses',
			ACME_COURSES_URL . 'assets/css/courses.css',
			array(),
			VERSION
		);
	}

	/**
	 * Activation: register post types and flush rewrite rules so /courses/ works.
	 */
	public static function activate() {
		( new Post_Types() )->register_types();
		flush_rewrite_rules();
		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
