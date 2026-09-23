<?php
/**
 * Scripts and styles.
 *
 * @package Acme\Charts
 */

namespace Acme\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the chart renderer, the front-end bootstrap and the stylesheets.
 *
 * The renderer (`assets/js/chart-renderer.js`) is shared by the front end and the editor
 * preview so that charts look the same everywhere.
 *
 * Nothing is enqueued globally: the blocks declare the handles they need in their
 * block.json (front-end script and styles, editor canvas styles), so WordPress loads
 * them only where a chart or legend is rendered, and inside the editor canvas.
 */
class Assets {

	const RENDERER = 'acme-charts-renderer';
	const FRONTEND = 'acme-charts-frontend';
	const STYLE    = 'acme-charts';
	const CANVAS   = 'acme-charts-editor-canvas';
	const EDITOR   = 'acme-charts-editor';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		// Before the blocks are registered (init, 10): block.json refers to these handles.
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_ui_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
	}

	/**
	 * Front end: only on posts that contain a chart or a legend.
	 */
	public function enqueue_frontend() {
		$post = get_post();
		if ( ! is_singular() || ! $post ) {
			return;
		}
		if ( has_block( 'acme/chart', $post ) || has_block( 'acme/chart-legend', $post ) ) {
			wp_enqueue_script( self::FRONTEND );
		}
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
		$this->localize_renderer();

		// Chart + legend styles: front end and editor canvas.
		wp_register_style(
			self::STYLE,
			ACME_CHARTS_URL . 'assets/css/acme-charts.css',
			array(),
			ACME_CHARTS_VERSION
		);
		// Editor-only styles for things inside the canvas (placeholders).
		wp_register_style(
			self::CANVAS,
			ACME_CHARTS_URL . 'assets/css/editor-canvas.css',
			array(),
			ACME_CHARTS_VERSION
		);
		// Editor-only styles for the editor UI around the canvas (block settings sidebar).
		wp_register_style(
			self::EDITOR,
			ACME_CHARTS_URL . 'assets/css/editor.css',
			array(),
			ACME_CHARTS_VERSION
		);
	}

	/**
	 * Expose the palette and strings to the renderer (front end and editor).
	 */
	protected function localize_renderer() {
		wp_localize_script( self::RENDERER, 'acmeChartsSettings', acme_charts_script_settings() );
	}

	/**
	 * Block editor: styles for the sidebar controls (outside the canvas).
	 */
	public function enqueue_editor_ui_styles() {
		wp_enqueue_style( self::EDITOR );
	}
}
