<?php
/**
 * Recipe data helpers.
 *
 * Stored meta (post meta on `acme_recipe` posts):
 *
 * - `_acme_recipe_ingredients` serialized array of ingredients, each
 *   `array( 'amount' => '200', 'unit' => 'g', 'item' => 'flour' )`.
 *   Recipes saved with 1.x store a list of plain strings (`'200 g flour'`) instead.
 * - `_acme_recipe_prep_time`, `_acme_recipe_cook_time` minutes (int). 1.x stored free text
 *   such as `'20 min'` or `'1 h 30 min'`.
 * - `_acme_recipe_servings` int.
 * - `_acme_recipe_difficulty` `easy`, `medium` or `hard` (1.x: `Easy`, `Medium`, `Hard`).
 * - `_acme_recipe_notes` private kitchen notes (costs, suppliers); never shown to visitors.
 * - `_acme_recipe_staff_pick` '1' when the editors picked the recipe; only editors may set it.
 *
 * @package Acme\Recipes
 */

defined( 'ABSPATH' ) || exit;

const ACME_RECIPES_META_INGREDIENTS = '_acme_recipe_ingredients';
const ACME_RECIPES_META_PREP        = '_acme_recipe_prep_time';
const ACME_RECIPES_META_COOK        = '_acme_recipe_cook_time';
const ACME_RECIPES_META_SERVINGS    = '_acme_recipe_servings';
const ACME_RECIPES_META_DIFFICULTY  = '_acme_recipe_difficulty';
const ACME_RECIPES_META_NOTES       = '_acme_recipe_notes';
const ACME_RECIPES_META_STAFF_PICK  = '_acme_recipe_staff_pick';

/**
 * Difficulty levels (slug => label).
 *
 * @return array<string,string>
 */
function acme_recipes_difficulties() {
	/**
	 * Filters the available difficulty levels.
	 *
	 * @param array<string,string> $levels Slug => label.
	 */
	return apply_filters(
		'acme_recipes_difficulties',
		array(
			'easy'   => __( 'Easy', 'acme-recipes' ),
			'medium' => __( 'Medium', 'acme-recipes' ),
			'hard'   => __( 'Hard', 'acme-recipes' ),
		)
	);
}

/**
 * Units the ingredient parser recognizes in 1.x ingredient strings.
 *
 * @return string[]
 */
function acme_recipes_known_units() {
	return array( 'g', 'kg', 'mg', 'ml', 'cl', 'dl', 'l', 'tsp', 'tbsp', 'cup', 'cups', 'oz', 'lb', 'pinch', 'clove', 'cloves', 'slice', 'slices' );
}

/**
 * Normalize one stored ingredient (current array format or 1.x string) to amount/unit/item.
 *
 * @param mixed $raw Stored ingredient.
 * @return array{amount:string,unit:string,item:string}|null Null for empty/invalid entries.
 */
function acme_recipes_normalize_ingredient( $raw ) {
	if ( is_array( $raw ) ) {
		$ingredient = array(
			'amount' => isset( $raw['amount'] ) && is_scalar( $raw['amount'] ) ? trim( (string) $raw['amount'] ) : '',
			'unit'   => isset( $raw['unit'] ) && is_scalar( $raw['unit'] ) ? trim( (string) $raw['unit'] ) : '',
			'item'   => isset( $raw['item'] ) && is_scalar( $raw['item'] ) ? trim( (string) $raw['item'] ) : '',
		);
		return '' === $ingredient['item'] ? null : $ingredient;
	}
	if ( ! is_scalar( $raw ) ) {
		return null;
	}
	$raw = trim( preg_replace( '/\s+/', ' ', (string) $raw ) );
	if ( '' === $raw ) {
		return null;
	}
	// 1.x: "200 g flour", "2 onions", "1/2 tsp salt", "salt to taste".
	$units = implode( '|', array_map( 'preg_quote', acme_recipes_known_units() ) );
	if ( preg_match( '#^(\d+(?:[.,/]\d+)?|[½¼¾⅓⅔])\s*(?:(' . $units . ')\.?\s+)?(.+)$#iu', $raw, $m ) ) {
		return array(
			'amount' => $m[1],
			'unit'   => isset( $m[2] ) ? strtolower( $m[2] ) : '',
			'item'   => trim( $m[3] ),
		);
	}
	return array(
		'amount' => '',
		'unit'   => '',
		'item'   => $raw,
	);
}

/**
 * Ingredients of a recipe, normalized.
 *
 * @param int $post_id Recipe ID.
 * @return array<int,array{amount:string,unit:string,item:string}>
 */
function acme_recipes_get_ingredients( $post_id ) {
	$stored = get_post_meta( $post_id, ACME_RECIPES_META_INGREDIENTS, true );
	if ( is_string( $stored ) && '' !== $stored ) {
		// Very early imports stored one ingredient per line.
		$stored = preg_split( '/\r\n|\r|\n/', $stored );
	}
	$out = array();
	foreach ( (array) $stored as $raw ) {
		$ingredient = acme_recipes_normalize_ingredient( $raw );
		if ( $ingredient ) {
			$out[] = $ingredient;
		}
	}
	return $out;
}

/**
 * Parse a duration in minutes from an int or 1.x free text ("20 min", "1 h 30 min", "1h30", "90").
 *
 * @param mixed $value Stored value.
 * @return int Minutes (0 when unknown).
 */
function acme_recipes_parse_minutes( $value ) {
	if ( is_int( $value ) || is_float( $value ) ) {
		return max( 0, (int) $value );
	}
	$value = strtolower( trim( (string) $value ) );
	if ( '' === $value ) {
		return 0;
	}
	if ( ctype_digit( $value ) ) {
		return (int) $value;
	}
	$minutes = 0;
	if ( preg_match( '/(\d+)\s*(?:h|hr|hrs|hours?)\b\s*(\d+)?/', $value, $m ) ) {
		$minutes += 60 * (int) $m[1];
		if ( ! empty( $m[2] ) ) {
			$minutes += (int) $m[2];
		}
		$value = str_replace( $m[0], '', $value );
	}
	if ( preg_match( '/(\d+)\s*(?:m|min|mins|minutes?)?\b/', $value, $m ) ) {
		$minutes += (int) $m[1];
	}
	return $minutes;
}

/**
 * Prep or cook time of a recipe in minutes.
 *
 * @param int    $post_id Recipe ID.
 * @param string $key     ACME_RECIPES_META_PREP or ACME_RECIPES_META_COOK.
 * @return int
 */
function acme_recipes_get_minutes( $post_id, $key ) {
	return acme_recipes_parse_minutes( get_post_meta( $post_id, $key, true ) );
}

/**
 * Number of servings (0 when unknown).
 *
 * @param int $post_id Recipe ID.
 * @return int
 */
function acme_recipes_get_servings( $post_id ) {
	return max( 0, (int) get_post_meta( $post_id, ACME_RECIPES_META_SERVINGS, true ) );
}

/**
 * Difficulty slug ('' when unknown).
 *
 * @param int $post_id Recipe ID.
 * @return string
 */
function acme_recipes_get_difficulty( $post_id ) {
	$difficulty = strtolower( trim( (string) get_post_meta( $post_id, ACME_RECIPES_META_DIFFICULTY, true ) ) );
	return array_key_exists( $difficulty, acme_recipes_difficulties() ) ? $difficulty : '';
}

/**
 * Human readable duration: "45 min", "1 h 30 min".
 *
 * @param int $minutes Minutes.
 * @return string
 */
function acme_recipes_format_minutes( $minutes ) {
	$minutes = (int) $minutes;
	if ( $minutes < 60 ) {
		/* translators: %d: minutes */
		return sprintf( __( '%d min', 'acme-recipes' ), $minutes );
	}
	$hours = intdiv( $minutes, 60 );
	$rest  = $minutes % 60;
	return $rest
		/* translators: 1: hours, 2: minutes */
		? sprintf( __( '%1$d h %2$d min', 'acme-recipes' ), $hours, $rest )
		/* translators: %d: hours */
		: sprintf( __( '%d h', 'acme-recipes' ), $hours );
}

/**
 * Does a recipe have anything to show in a recipe card?
 *
 * @param int $post_id Recipe ID.
 * @return bool
 */
function acme_recipes_has_details( $post_id ) {
	return acme_recipes_get_ingredients( $post_id )
		|| acme_recipes_get_minutes( $post_id, ACME_RECIPES_META_PREP )
		|| acme_recipes_get_minutes( $post_id, ACME_RECIPES_META_COOK )
		|| acme_recipes_get_servings( $post_id );
}

/**
 * Recipe card markup.
 *
 * @param int $post_id Recipe ID.
 * @return string
 */
function acme_recipes_render_card( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || 'acme_recipe' !== get_post_type( $post_id ) || ! acme_recipes_has_details( $post_id ) ) {
		return '';
	}
	$vars = array(
		'post_id'     => $post_id,
		'ingredients' => acme_recipes_get_ingredients( $post_id ),
		'prep'        => acme_recipes_get_minutes( $post_id, ACME_RECIPES_META_PREP ),
		'cook'        => acme_recipes_get_minutes( $post_id, ACME_RECIPES_META_COOK ),
		'servings'    => acme_recipes_get_servings( $post_id ),
		'difficulty'  => acme_recipes_get_difficulty( $post_id ),
	);
	$template = locate_template( array( 'acme-recipes/recipe-card.php' ) );
	$template = $template ? $template : ACME_RECIPES_DIR . 'templates/recipe-card.php';

	ob_start();
	include $template;
	$html = (string) ob_get_clean();

	/**
	 * Filters the recipe card markup.
	 *
	 * @param string $html    Card markup.
	 * @param int    $post_id Recipe ID.
	 * @param array  $vars    Card data.
	 */
	return apply_filters( 'acme_recipes_card_html', $html, $post_id, $vars );
}
