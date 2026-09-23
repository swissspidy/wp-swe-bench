<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Post type + taxonomy.
	 *
	 * @var Post_Type
	 */
	public $post_type;

	/**
	 * Classic meta box for the member fields.
	 *
	 * @var Meta_Box
	 */
	public $meta_box;

	/**
	 * Shortcodes.
	 *
	 * @var Shortcodes
	 */
	public $shortcodes;

	/**
	 * Block bindings source.
	 *
	 * @var Bindings
	 */
	public $bindings;

	/**
	 * REST field for the editor.
	 *
	 * @var Rest
	 */
	public $rest;

	/**
	 * Block patterns.
	 *
	 * @var Patterns
	 */
	public $patterns;

	/**
	 * Get the plugin instance.
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
	 * Hook everything up.
	 */
	public function boot() {
		$this->post_type  = new Post_Type();
		$this->meta_box   = new Meta_Box();
		$this->shortcodes = new Shortcodes();
		$this->bindings   = new Bindings();
		$this->rest       = new Rest();
		$this->patterns   = new Patterns();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		$this->post_type->register_hooks();
		$this->meta_box->register_hooks();
		$this->shortcodes->register_hooks();
		$this->bindings->register_hooks();
		$this->rest->register_hooks();
		$this->patterns->register_hooks();
	}

	/**
	 * Editor script (build/index.js).
	 */
	public function enqueue_editor_assets() {
		$asset_file = ACME_TEAM_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( 'acme-team-editor', ACME_TEAM_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'acme-team-editor', 'acme-team', ACME_TEAM_DIR . 'languages' );

		$fields = array();
		foreach ( acme_team_fields() as $key => $field ) {
			$fields[] = array(
				'key'   => $key,
				'label' => $field['label'],
				'type'  => $field['type'],
			);
		}
		wp_add_inline_script( 'acme-team-editor', 'window.acmeTeam = ' . wp_json_encode( array( 'fields' => $fields ) ) . ';', 'before' );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-team', false, dirname( plugin_basename( ACME_TEAM_FILE ) ) . '/languages' );
	}
}
