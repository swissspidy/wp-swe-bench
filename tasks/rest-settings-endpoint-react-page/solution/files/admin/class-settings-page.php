<?php
/**
 * Settings → SEO screen.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * The settings screen: a React app (src/settings) that reads and saves the
 * `acme_seo_settings` setting through the REST API (/wp/v2/settings).
 */
class Settings_Page {

	const SLUG = 'acme-seo';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability needed to manage the settings. Since 2.0 this is always `manage_options`:
	 * the settings are saved through /wp/v2/settings, which requires it.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_options';
	}

	/**
	 * Register Settings → SEO.
	 */
	public static function add_page() {
		add_options_page(
			__( 'SEO settings', 'acme-seo' ),
			__( 'SEO', 'acme-seo' ),
			self::capability(),
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ), esc_html__( 'Settings', 'acme-seo' ) ) );
		return $links;
	}

	/**
	 * Enqueue the app on our screen only.
	 *
	 * @param string $hook_suffix Current screen.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		$dir   = plugin_dir_path( PLUGIN_FILE ) . 'build/settings/';
		$asset = file_exists( $dir . 'index.asset.php' ) ? require $dir . 'index.asset.php' : array(
			'dependencies' => array(),
			'version'      => VERSION,
		);

		wp_enqueue_script( 'acme-seo-settings', plugins_url( 'build/settings/index.js', PLUGIN_FILE ), $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'wp-components' );
		wp_set_script_translations( 'acme-seo-settings', 'acme-seo', plugin_dir_path( PLUGIN_FILE ) . 'languages' );

		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$post_types[] = array(
				'name'  => $type->name,
				'label' => $type->labels->name,
			);
		}
		wp_add_inline_script(
			'acme-seo-settings',
			'window.acmeSeoSettings = ' . wp_json_encode(
				array(
					'postTypes'  => $post_types,
					'separators' => Options::SEPARATORS,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Page shell; the app renders into it.
	 */
	public static function render() {
		?>
		<div class="wrap acme-seo-settings">
			<h1><?php esc_html_e( 'SEO settings', 'acme-seo' ); ?></h1>
			<div id="acme-seo-settings-app"><p><?php esc_html_e( 'Loading…', 'acme-seo' ); ?></p></div>
		</div>
		<?php
	}
}
