<?php
/**
 * Front-end recipe card.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Appends the recipe card (times, servings, difficulty, ingredients) to single recipes.
 */
class Card {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_filter( 'the_content', array( $this, 'append' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Append the card to the content of a single recipe.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append( $content ) {
		if ( ! is_singular( Post_Type::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		return $content . acme_recipes_render_card( get_the_ID() );
	}

	/**
	 * Card styles.
	 */
	public function enqueue() {
		if ( is_singular( Post_Type::POST_TYPE ) ) {
			wp_enqueue_style( 'acme-recipes-card', ACME_RECIPES_URL . 'assets/card.css', array(), ACME_RECIPES_VERSION );
		}
	}
}
