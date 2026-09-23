<?php
/**
 * Scripts and styles.
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the UI Kit assets and loads them only where a component is actually used.
 *
 * Components are requested when they are rendered (blocks – wherever they come from: post
 * content, synced patterns, template parts, widgets –, the legacy shortcode) or explicitly
 * through acme_ui_enqueue(). Styles requested while the page body is being printed end up
 * in the footer; that is fine for these components (they are hidden/unstyled for a moment
 * at most).
 *
 * Handles (themes and plugins depend on / dequeue these):
 * - `acme-ui-core`          (script) runtime, window.AcmeUI; config printed before it (window.AcmeUIConfig)
 * - `acme-ui-motion`        (script) bundled AcmeMotion library, only for the carousel
 * - `acme-ui-tabs`, `acme-ui-accordion`, `acme-ui-carousel` (scripts) component behaviour
 * - `acme-ui-components`    (script) all three components (kept for code that enqueues it)
 * - `acme-ui`               (style)  tokens and shared utilities
 * - `acme-tabs-style`, `acme-accordion-style`, `acme-carousel-style` (styles) per component
 * - `acme-ui-icons`         (style)  icon set (accordion and carousel)
 * - `acme-ui-admin`         (script + style) settings screen
 */
class Assets {

	/** @var Settings */
	private $settings;

	/** @var array<string, array{script: string, style: string}> */
	private $components = array(
		'tabs'      => array(
			'script' => 'acme-ui-tabs',
			'style'  => 'acme-tabs-style',
		),
		'accordion' => array(
			'script' => 'acme-ui-accordion',
			'style'  => 'acme-accordion-style',
		),
		'carousel'  => array(
			'script' => 'acme-ui-carousel',
			'style'  => 'acme-carousel-style',
		),
	);

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
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_canvas' ) );
	}

	/**
	 * Register everything (nothing is loaded here).
	 */
	public function register() {
		$url   = ACME_UI_URL . 'assets/';
		$ver   = ACME_UI_VERSION;
		$defer = array(
			'strategy'  => 'defer',
			'in_footer' => false,
		);

		// Runtime. Deferred; if some other code adds an inline script that needs AcmeUI right
		// after it, WordPress falls back to loading it normally so that code keeps working.
		wp_register_script( 'acme-ui-core', $url . 'js/core.js', array(), $ver, $defer );
		wp_add_inline_script( 'acme-ui-core', 'window.AcmeUIConfig = ' . wp_json_encode( $this->config() ) . ';', 'before' );
		wp_register_script( 'acme-ui-motion', $url . 'lib/acme-motion.min.js', array(), '3.4.1', $defer );

		wp_register_script( 'acme-ui-tabs', $url . 'js/tabs.js', array( 'acme-ui-core' ), $ver, $defer );
		wp_register_script( 'acme-ui-accordion', $url . 'js/accordion.js', array( 'acme-ui-core' ), $ver, $defer );
		wp_register_script( 'acme-ui-carousel', $url . 'js/carousel.js', array( 'acme-ui-core', 'acme-ui-motion' ), $ver, $defer );
		// Back-compat: themes that enqueue the old bundle handle get all three components.
		wp_register_script( 'acme-ui-components', false, array( 'acme-ui-tabs', 'acme-ui-accordion', 'acme-ui-carousel' ), $ver, $defer );

		wp_register_style( 'acme-ui', $url . 'css/acme-ui.css', array(), $ver );
		wp_register_style( 'acme-ui-icons', $url . 'css/icons.css', array(), $ver );
		wp_register_style( 'acme-tabs-style', $url . 'css/tabs.css', array( 'acme-ui' ), $ver );
		wp_register_style( 'acme-accordion-style', $url . 'css/accordion.css', array( 'acme-ui', 'acme-ui-icons' ), $ver );
		wp_register_style( 'acme-carousel-style', $url . 'css/carousel.css', array( 'acme-ui', 'acme-ui-icons' ), $ver );

		$accent = sanitize_hex_color( $this->settings->get( 'accent' ) );
		if ( $accent ) {
			wp_add_inline_style( 'acme-ui', ':root{--acme-accent:' . $accent . ';--acme-speed:' . (int) $this->settings->get( 'speed' ) . 'ms}' );
		}

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
	 * Load the runtime and the given components on the current page.
	 *
	 * @param string[] $components Components (all when empty). Unknown names are ignored.
	 */
	public function enqueue( array $components = array() ) {
		if ( ! did_action( 'init' ) ) {
			// Too early: assets aren't registered yet.
			add_action(
				'init',
				function () use ( $components ) {
					$this->enqueue( $components );
				},
				20
			);
			return;
		}
		$components = $components ? array_intersect( array_keys( $this->components ), array_map( 'strval', $components ) ) : array_keys( $this->components );

		wp_enqueue_script( 'acme-ui-core' );
		wp_enqueue_style( 'acme-ui' );
		foreach ( $components as $component ) {
			wp_enqueue_script( $this->components[ $component ]['script'] );
			wp_enqueue_style( $this->components[ $component ]['style'] );
		}
	}

	/**
	 * Front end: load the components the current post/page uses.
	 */
	public function enqueue_front() {
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$components = array();
		foreach ( array_keys( $this->components ) as $component ) {
			if ( has_block( 'acme/' . $component, $post ) ) {
				$components[] = $component;
			}
		}
		if ( has_shortcode( $post->post_content, 'acme_tabs' ) ) {
			$components[] = 'tabs';
		}
		if ( $components ) {
			$this->enqueue( array_unique( $components ) );
		}
	}

	/**
	 * A component was rendered: make sure its assets are loaded.
	 *
	 * @param string $component Component.
	 */
	public function rendered( $component ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$this->enqueue( array( $component ) );
	}

	/**
	 * Admin: only the settings screen needs anything (its preview shows tabs).
	 *
	 * @param string $hook_suffix Screen.
	 */
	public function enqueue_admin( $hook_suffix ) {
		if ( 'settings_page_acme-ui' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'acme-ui-admin' );
		wp_enqueue_script( 'acme-ui-admin' );
		$this->enqueue( array( 'tabs' ) );
	}

	/**
	 * Block editor canvas: component styles for the block previews.
	 */
	public function enqueue_editor_canvas() {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style( 'acme-ui' );
		foreach ( $this->components as $handles ) {
			wp_enqueue_style( $handles['style'] );
		}
	}
}
