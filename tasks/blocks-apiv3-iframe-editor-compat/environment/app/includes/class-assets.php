<?php
/**
 * Scripts and styles.
 *
 * @package Acme\Charts
 */

namespace Acme\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues the chart renderer, the front-end bootstrap and the stylesheets.
 *
 * The renderer (`assets/js/chart-renderer.js`) is shared by the front end and the editor
 * preview so that charts look the same everywhere.
 */
class Assets {

	const RENDERER = 'acme-charts-renderer';
	const FRONTEND = 'acme-charts-frontend';
	const STYLE    = 'acme-charts';
	const EDITOR   = 'acme-charts-editor';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_scripts' ) );
	}

	/**
	 * Register all handles.
	 */
	public function register() {
		wp_register_script(
			self::RENDERER,
			ACME_CHARTS_URL . 'assets/js/chart-renderer.js',
			array( 'jquery' ),
			ACME_CHARTS_VERSION,
			true
		);
		wp_register_script(
			self::FRONTEND,
			ACME_CHARTS_URL . 'assets/js/frontend.js',
			array( 'jquery', self::RENDERER ),
			ACME_CHARTS_VERSION,
			true
		);
		wp_register_style(
			self::STYLE,
			ACME_CHARTS_URL . 'assets/css/acme-charts.css',
			array(),
			ACME_CHARTS_VERSION
		);
		wp_register_style(
			self::EDITOR,
			ACME_CHARTS_URL . 'assets/css/editor.css',
			array( self::STYLE ),
			ACME_CHARTS_VERSION
		);
	}

	/**
	 * Expose the palette and strings to the renderer.
	 */
	protected function localize_renderer() {
		wp_localize_script( self::RENDERER, 'acmeChartsSettings', acme_charts_script_settings() );
	}

	/**
	 * Front end: styles + renderer + bootstrap.
	 *
	 * @todo Only load this on pages that actually contain a chart (#41).
	 */
	public function enqueue_frontend() {
		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::FRONTEND );
		$this->localize_renderer();
	}

	/**
	 * Editor screens: chart styles + editor-only styles.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_admin_styles( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( self::EDITOR );
	}

	/**
	 * Block editor: the renderer is needed for the live preview.
	 */
	public function enqueue_editor_scripts() {
		wp_enqueue_script( self::RENDERER );
		$this->localize_renderer();
	}
}
