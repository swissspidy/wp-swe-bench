<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

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
	 * Post type.
	 *
	 * @var Post_Type
	 */
	public $post_type;

	/**
	 * Recipe meta (REST API / block editor).
	 *
	 * @var Meta
	 */
	public $meta;

	/**
	 * Block editor integration.
	 *
	 * @var Editor
	 */
	public $editor;

	/**
	 * Front-end recipe card.
	 *
	 * @var Card
	 */
	public $card;

	/**
	 * Structured data.
	 *
	 * @var Schema
	 */
	public $schema;

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
		$this->post_type = new Post_Type();
		$this->meta      = new Meta();
		$this->editor    = new Editor();
		$this->card      = new Card();
		$this->schema    = new Schema();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->post_type->register_hooks();
		$this->meta->register_hooks();
		$this->editor->register_hooks();
		$this->card->register_hooks();
		$this->schema->register_hooks();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-recipes', false, dirname( plugin_basename( ACME_RECIPES_FILE ) ) . '/languages' );
	}
}
