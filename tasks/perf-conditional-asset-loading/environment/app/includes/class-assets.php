<?php
/**
 * Scripts and styles.
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and loads the UI Kit assets.
 *
 * Handles (themes and plugins depend on / dequeue these):
 * - `acme-ui-core`       (script) runtime, window.AcmeUI; config printed before it (window.AcmeUIConfig)
 * - `acme-ui-motion`     (script) bundled AcmeMotion library (carousel animations)
 * - `acme-ui-components` (script) tabs, accordion and carousel behaviour
 * - `acme-ui`            (style)  tokens and component styles
 * - `acme-ui-icons`      (style)  icon set
 * - `acme-ui-admin`      (script + style) settings screen
 */
class Assets {

	/** @var Settings */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Register everything.
	 */
	public function register() {
		$url = ACME_UI_URL . 'assets/';
		$ver = ACME_UI_VERSION;

		wp_register_script( 'acme-ui-core', $url . 'js/core.js', array(), $ver, false );
		wp_add_inline_script( 'acme-ui-core', 'window.AcmeUIConfig = ' . wp_json_encode( $this->config() ) . ';', 'before' );
		wp_register_script( 'acme-ui-motion', $url . 'lib/acme-motion.min.js', array(), '3.4.1', false );
		wp_register_script( 'acme-ui-components', $url . 'js/components.js', array( 'acme-ui-core', 'acme-ui-motion' ), $ver, false );

		wp_register_style( 'acme-ui', $url . 'css/acme-ui.css', array(), $ver );
		wp_register_style( 'acme-ui-icons', $url . 'css/icons.css', array(), $ver );

		wp_register_script( 'acme-ui-admin', $url . 'admin/admin.js', array( 'jquery', 'wp-color-picker' ), $ver, true );
		wp_register_style( 'acme-ui-admin', $url . 'admin/admin.css', array( 'wp-color-picker' ), $ver );

		wp_register_script(
			'acme-ui-blocks-editor',
			$url . 'editor/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			$ver,
			true
		);
	}

	/**
	 * Runtime configuration (window.AcmeUIConfig).
	 *
	 * @return array
	 */
	public function config() {
		$s = $this->settings->all();
		/**
		 * Filters the configuration passed to the UI Kit runtime.
		 *
		 * @param array $config Config.
		 */
		return apply_filters(
			'acme_ui_config',
			array(
				'version'          => ACME_UI_VERSION,
				'accent'           => $s['accent'],
				'animationSpeed'   => (int) $s['speed'],
				'carouselAutoplay' => (bool) $s['autoplay'],
			)
		);
	}

	/**
	 * Enqueue the front-end assets.
	 *
	 * TODO: we load everything everywhere because it's simplest: the components can show up in
	 * posts, patterns, template parts, widgets, shortcodes and other plugins' markup.
	 */
	public function enqueue_front() {
		$this->enqueue();
	}

	/**
	 * Enqueue components (all of them when none are given).
	 *
	 * @param string[] $components Components.
	 */
	public function enqueue( array $components = array() ) {
		wp_enqueue_style( 'acme-ui' );
		wp_enqueue_style( 'acme-ui-icons' );
		wp_enqueue_script( 'acme-ui-core' );
		wp_enqueue_script( 'acme-ui-motion' );
		wp_enqueue_script( 'acme-ui-components' );
		$this->add_accent_css();
	}

	/**
	 * Accent colour from the settings.
	 */
	private function add_accent_css() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done   = true;
		$accent = sanitize_hex_color( $this->settings->get( 'accent' ) );
		if ( $accent ) {
			wp_add_inline_style( 'acme-ui', ':root{--acme-accent:' . $accent . ';--acme-speed:' . (int) $this->settings->get( 'speed' ) . 'ms}' );
		}
	}

	/**
	 * Admin: the settings screen needs the admin assets, the preview there needs the
	 * front-end assets; the block editor previews need them too.
	 *
	 * @param string $hook_suffix Screen.
	 */
	public function enqueue_admin( $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'acme-ui-admin' );
		wp_enqueue_script( 'acme-ui-admin' );
		$this->enqueue();
	}
}
