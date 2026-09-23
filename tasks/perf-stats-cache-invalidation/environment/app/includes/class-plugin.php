<?php
/**
 * Bootstrap.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Stats */
	public $stats;

	/**
	 * Instance.
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
	 * Wire everything up.
	 */
	private function boot() {
		$this->stats = new Stats( new Calculator() );

		( new Dashboard_Widget( $this->stats ) )->register_hooks();
		( new Admin_Page( $this->stats ) )->register_hooks();
		( new Shortcode( $this->stats ) )->register_hooks();
		( new Rest( $this->stats ) )->register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-dashboard-stats', false, dirname( plugin_basename( ACME_STATS_FILE ) ) . '/languages' );
	}

	/**
	 * Plugin settings.
	 *
	 * @return array
	 */
	public static function settings() {
		$s = get_option( 'acme_stats_settings', array() );
		return wp_parse_args(
			is_array( $s ) ? $s : array(),
			array(
				'top_posts'     => 5,
				'public_totals' => true,
			)
		);
	}
}
