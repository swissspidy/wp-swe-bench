<?php
/**
 * schema.org Recipe structured data.
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Prints JSON-LD for single recipes (used by search engines and our newsletter tool).
 */
class Schema {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'print_json_ld' ) );
	}

	/**
	 * Structured data of a recipe.
	 *
	 * @param int $post_id Recipe ID.
	 * @return array
	 */
	public function data( $post_id ) {
		$data = array(
			'@context'           => 'https://schema.org',
			'@type'              => 'Recipe',
			'name'               => get_the_title( $post_id ),
			'datePublished'      => get_the_date( 'c', $post_id ),
			'author'             => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
			),
			'recipeIngredient'   => array(),
			'recipeYield'        => '',
		);
		foreach ( acme_recipes_get_ingredients( $post_id ) as $ingredient ) {
			$data['recipeIngredient'][] = trim( preg_replace( '/\s+/', ' ', $ingredient['amount'] . ' ' . $ingredient['unit'] . ' ' . $ingredient['item'] ) );
		}
		$prep = acme_recipes_get_minutes( $post_id, ACME_RECIPES_META_PREP );
		$cook = acme_recipes_get_minutes( $post_id, ACME_RECIPES_META_COOK );
		if ( $prep ) {
			$data['prepTime'] = 'PT' . $prep . 'M';
		}
		if ( $cook ) {
			$data['cookTime'] = 'PT' . $cook . 'M';
		}
		if ( $prep || $cook ) {
			$data['totalTime'] = 'PT' . ( $prep + $cook ) . 'M';
		}
		$servings = acme_recipes_get_servings( $post_id );
		if ( $servings ) {
			$data['recipeYield'] = (string) $servings;
		}

		/**
		 * Filters the Recipe structured data.
		 *
		 * @param array $data    JSON-LD data.
		 * @param int   $post_id Recipe ID.
		 */
		return apply_filters( 'acme_recipes_schema', $data, $post_id );
	}

	/**
	 * Print JSON-LD on single recipes.
	 */
	public function print_json_ld() {
		if ( ! is_singular( Post_Type::POST_TYPE ) ) {
			return;
		}
		echo '<script type="application/ld+json" class="acme-recipe-schema">' . wp_json_encode( $this->data( get_queried_object_id() ), JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}
