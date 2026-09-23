<?php
/**
 * Dashboard widget "Newsroom numbers".
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard widget.
 */
class Dashboard_Widget {

	/** @var Stats */
	private $stats;

	/**
	 * Constructor.
	 *
	 * @param Stats $stats Stats.
	 */
	public function __construct( Stats $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_dashboard_setup', array( $this, 'add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the widget for users who can write posts.
	 */
	public function add() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'acme_stats_widget', __( 'Newsroom numbers', 'acme-dashboard-stats' ), array( $this, 'render' ) );
	}

	/**
	 * Styles on the dashboard and the stats page.
	 *
	 * @param string $hook_suffix Screen.
	 */
	public function enqueue( $hook_suffix ) {
		if ( in_array( $hook_suffix, array( 'index.php', 'dashboard_page_acme-stats' ), true ) ) {
			wp_enqueue_style( 'acme-stats-admin', ACME_STATS_URL . 'assets/admin.css', array(), ACME_STATS_VERSION );
		}
	}

	/**
	 * Widget content.
	 */
	public function render() {
		$stats = $this->stats->for_user( get_current_user_id() );
		if ( ! $stats ) {
			return;
		}
		echo Format::report( $stats ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Format.
		if ( current_user_can( 'edit_posts' ) ) {
			printf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'index.php?page=acme-stats' ) ), esc_html__( 'All numbers', 'acme-dashboard-stats' ) );
		}
	}
}
