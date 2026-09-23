<?php
/**
 * Block editor integration: the "Recipe details" document panel and the Recipe card block.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the recipe card block and loads the recipe details panel in the block editor.
 */
class Editor {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the acme/recipe-card block.
	 */
	public function register_blocks() {
		$block = register_block_type( ACME_RECIPES_DIR . 'build/recipe-card' );
		if ( $block && ! empty( $block->editor_script_handles[0] ) ) {
			wp_set_script_translations( $block->editor_script_handles[0], 'acme-recipes', ACME_RECIPES_DIR . 'languages' );
		}
	}

	/**
	 * Load the recipe details panel when editing a recipe.
	 */
	public function enqueue() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$asset_file = ACME_RECIPES_DIR . 'build/editor/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script( 'acme-recipes-editor', ACME_RECIPES_URL . 'build/editor/index.js', $asset['dependencies'], $asset['version'], true );
		wp_add_inline_script(
			'acme-recipes-editor',
			'window.acmeRecipesEditor = ' . wp_json_encode(
				array(
					'canStaffPick' => current_user_can( 'edit_others_posts' ),
					'difficulties' => acme_recipes_difficulties(),
				)
			) . ';',
			'before'
		);
		wp_set_script_translations( 'acme-recipes-editor', 'acme-recipes', ACME_RECIPES_DIR . 'languages' );
	}
}
