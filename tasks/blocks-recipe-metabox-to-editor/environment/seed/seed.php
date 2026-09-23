<?php
/**
 * Recipe fixtures (run with `wp eval-file`).
 */

$users = array();
foreach ( array( 'alice' => 'author', 'carl' => 'contributor', 'eddie' => 'editor', 'sam' => 'subscriber' ) as $login => $role ) {
	$users[ $login ] = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $login . '@example.org',
			'user_pass'    => 'password',
			'role'         => $role,
			'display_name' => ucfirst( $login ),
		)
	);
}

$cuisines = array();
foreach ( array( 'Breakfast', 'Hungarian', 'Salads', 'Baking' ) as $name ) {
	$cuisines[ $name ] = wp_insert_term( $name, 'acme_cuisine' )['term_id'];
}

function acme_seed_recipe( $args, $meta, $cuisine ) {
	$id = wp_insert_post(
		array_merge(
			array(
				'post_type'   => 'acme_recipe',
				'post_status' => 'publish',
				'post_author' => 1,
			),
			$args
		),
		true
	);
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	wp_set_object_terms( $id, array( $cuisine ), 'acme_cuisine' );
	return $id;
}

// Current (1.4+) format.
acme_seed_recipe(
	array(
		'post_title'   => 'Classic Pancakes',
		'post_name'    => 'classic-pancakes',
		'post_content' => "<p>Fluffy pancakes for a lazy Sunday.</p>\n<p>Serve with maple syrup.</p>",
	),
	array(
		'_acme_recipe_ingredients' => array(
			array( 'amount' => '200', 'unit' => 'g', 'item' => 'flour' ),
			array( 'amount' => '2', 'unit' => '', 'item' => 'eggs' ),
			array( 'amount' => '300', 'unit' => 'ml', 'item' => 'milk' ),
			array( 'amount' => '1', 'unit' => 'pinch', 'item' => 'salt & "good" sugar' ),
		),
		'_acme_recipe_prep_time'   => 10,
		'_acme_recipe_cook_time'   => 15,
		'_acme_recipe_servings'    => 4,
		'_acme_recipe_difficulty'  => 'easy',
		'_acme_recipe_notes'       => 'Supplier: Mill & Co, 2.10/kg. Secret: a splash of sparkling water.',
		'_acme_recipe_staff_pick'  => '1',
	),
	$cuisines['Breakfast']
);

// 1.x format: ingredients as strings, times as text, capitalized difficulty.
acme_seed_recipe(
	array(
		'post_title'   => "Grandma's Goulash",
		'post_name'    => 'grandmas-goulash',
		'post_content' => '<p>The family classic.</p>',
	),
	array(
		'_acme_recipe_ingredients' => array( '500 g beef', '2 onions', '1 tbsp paprika', 'salt to taste' ),
		'_acme_recipe_prep_time'   => '20 min',
		'_acme_recipe_cook_time'   => '1 h 30 min',
		'_acme_recipe_servings'    => '6',
		'_acme_recipe_difficulty'  => 'Medium',
		'_acme_recipe_notes'       => 'Use beef shin from the Tuesday market.',
	),
	$cuisines['Hungarian']
);

// Imported in 2019: one ingredient per line in a single string.
acme_seed_recipe(
	array(
		'post_title'   => 'Country Bread',
		'post_name'    => 'country-bread',
		'post_content' => '<p>A simple loaf.</p>',
	),
	array(
		'_acme_recipe_ingredients' => "500 g flour\n10 g salt\n7 g yeast\n350 ml water",
		'_acme_recipe_prep_time'   => '15 minutes',
		'_acme_recipe_cook_time'   => '45',
		'_acme_recipe_servings'    => '8',
		'_acme_recipe_difficulty'  => 'Hard',
	),
	$cuisines['Baking']
);

// Alice's (author) recipe.
acme_seed_recipe(
	array(
		'post_title'   => 'Quick Salad',
		'post_name'    => 'quick-salad',
		'post_content' => '<p>Ready in five minutes.</p>',
		'post_author'  => $users['alice'],
	),
	array(
		'_acme_recipe_ingredients' => array(
			array( 'amount' => '1', 'unit' => '', 'item' => 'lettuce' ),
			array( 'amount' => '2', 'unit' => 'tbsp', 'item' => 'olive oil' ),
		),
		'_acme_recipe_prep_time'   => 5,
		'_acme_recipe_servings'    => 2,
		'_acme_recipe_difficulty'  => 'easy',
		'_acme_recipe_notes'       => 'Alice: buy the lettuce at the organic shop.',
	),
	$cuisines['Salads']
);

// Carl's (contributor) draft.
acme_seed_recipe(
	array(
		'post_title'   => 'Draft Soup',
		'post_name'    => 'draft-soup',
		'post_status'  => 'draft',
		'post_content' => '<p>Work in progress.</p>',
		'post_author'  => $users['carl'],
	),
	array(
		'_acme_recipe_ingredients' => array( array( 'amount' => '1', 'unit' => 'l', 'item' => 'stock' ) ),
		'_acme_recipe_servings'    => 3,
	),
	$cuisines['Salads']
);

// A recipe without any details yet.
acme_seed_recipe(
	array(
		'post_title'   => 'Mystery Dish',
		'post_name'    => 'mystery-dish',
		'post_content' => '<p>Details coming soon.</p>',
	),
	array(),
	$cuisines['Salads']
);
