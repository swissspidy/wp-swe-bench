<?php
/**
 * Recipe details in the REST API: shape, legacy data, validation, permissions, private notes.
 */

use function WPSB\Recipes\recipe_id;
use function WPSB\Recipes\user_id;

class RecipeRestTest extends WPSB\TestCase {

	const NOTES = '_acme_recipe_notes';

	private function get( string $slug, array $query = array() ): WP_REST_Response {
		return $this->rest( 'GET', '/wp/v2/recipes/' . recipe_id( $slug ), $query );
	}

	private function update( string $slug, array $meta ): WP_REST_Response {
		return $this->rest( 'POST', '/wp/v2/recipes/' . recipe_id( $slug ), array(), array( 'meta' => $meta ) );
	}

	private function meta_of( WP_REST_Response $res ): array {
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$data = $res->get_data();
		$this->assertArrayHasKey( 'meta', $data );
		return (array) $data['meta'];
	}

	public function test_recipes_route_lists_recipes(): void {
		$res = $this->rest( 'GET', '/wp/v2/recipes', array( 'per_page' => 50 ) );
		$this->assertSame( 200, $res->get_status() );
		$slugs = wp_list_pluck( $res->get_data(), 'slug' );
		$this->assertContains( 'classic-pancakes', $slugs );
		$this->assertContains( 'grandmas-goulash', $slugs );
		$this->assertNotContains( 'draft-soup', $slugs );
	}

	public function test_current_format_is_exposed(): void {
		$meta = $this->meta_of( $this->get( 'classic-pancakes' ) );
		$this->assertSame(
			array(
				array( 'amount' => '200', 'unit' => 'g', 'item' => 'flour' ),
				array( 'amount' => '2', 'unit' => '', 'item' => 'eggs' ),
				array( 'amount' => '300', 'unit' => 'ml', 'item' => 'milk' ),
				array( 'amount' => '1', 'unit' => 'pinch', 'item' => 'salt & "good" sugar' ),
			),
			$meta['_acme_recipe_ingredients']
		);
		$this->assertSame( 10, $meta['_acme_recipe_prep_time'] );
		$this->assertSame( 15, $meta['_acme_recipe_cook_time'] );
		$this->assertSame( 4, $meta['_acme_recipe_servings'] );
		$this->assertSame( 'easy', $meta['_acme_recipe_difficulty'] );
		$this->assertTrue( $meta['_acme_recipe_staff_pick'] );
	}

	public function test_legacy_1x_format_is_normalized(): void {
		$meta = $this->meta_of( $this->get( 'grandmas-goulash' ) );
		$this->assertSame(
			array(
				array( 'amount' => '500', 'unit' => 'g', 'item' => 'beef' ),
				array( 'amount' => '2', 'unit' => '', 'item' => 'onions' ),
				array( 'amount' => '1', 'unit' => 'tbsp', 'item' => 'paprika' ),
				array( 'amount' => '', 'unit' => '', 'item' => 'salt to taste' ),
			),
			$meta['_acme_recipe_ingredients']
		);
		$this->assertSame( 20, $meta['_acme_recipe_prep_time'] );
		$this->assertSame( 90, $meta['_acme_recipe_cook_time'] );
		$this->assertSame( 6, $meta['_acme_recipe_servings'] );
		$this->assertSame( 'medium', $meta['_acme_recipe_difficulty'] );
		$this->assertFalse( $meta['_acme_recipe_staff_pick'] );
	}

	public function test_imported_line_format_is_normalized(): void {
		$meta = $this->meta_of( $this->get( 'country-bread' ) );
		$this->assertSame(
			array(
				array( 'amount' => '500', 'unit' => 'g', 'item' => 'flour' ),
				array( 'amount' => '10', 'unit' => 'g', 'item' => 'salt' ),
				array( 'amount' => '7', 'unit' => 'g', 'item' => 'yeast' ),
				array( 'amount' => '350', 'unit' => 'ml', 'item' => 'water' ),
			),
			$meta['_acme_recipe_ingredients']
		);
		$this->assertSame( 15, $meta['_acme_recipe_prep_time'] );
		$this->assertSame( 45, $meta['_acme_recipe_cook_time'] );
		$this->assertSame( 8, $meta['_acme_recipe_servings'] );
		$this->assertSame( 'hard', $meta['_acme_recipe_difficulty'] );
	}

	public function test_recipe_without_details(): void {
		$meta = $this->meta_of( $this->get( 'mystery-dish' ) );
		$this->assertSame( array(), $meta['_acme_recipe_ingredients'] );
		$this->assertSame( 0, $meta['_acme_recipe_prep_time'] );
		$this->assertSame( 0, $meta['_acme_recipe_servings'] );
		$this->assertSame( '', $meta['_acme_recipe_difficulty'] );
	}

	public function test_notes_are_private(): void {
		$needle = 'Supplier: Mill';
		foreach ( array( 0, user_id( 'sam' ), user_id( 'alice' ), user_id( 'carl' ) ) as $user ) {
			wp_set_current_user( $user );
			$single = $this->get( 'classic-pancakes' );
			$this->assertSame( 200, $single->get_status() );
			$this->assertStringNotContainsString( $needle, wp_json_encode( $this->rest_data( $single ) ), "Notes leaked to user $user" );
			$list = $this->rest( 'GET', '/wp/v2/recipes', array( 'per_page' => 50 ) );
			$this->assertStringNotContainsString( $needle, wp_json_encode( $this->rest_data( $list ) ), "Notes leaked in the list to user $user" );
			$this->assertStringNotContainsString( 'organic shop', wp_json_encode( $this->rest_data( $list ) ), "Notes leaked in the list to user $user" );
		}
		wp_set_current_user( 0 );
		$this->assertContains( $this->get( 'classic-pancakes', array( 'context' => 'edit' ) )->get_status(), array( 401, 403 ) );
		wp_set_current_user( user_id( 'carl' ) );
		$this->assertSame( 403, $this->get( 'classic-pancakes', array( 'context' => 'edit' ) )->get_status() );

		// People who can edit the recipe get the notes when editing.
		wp_set_current_user( user_id( 'eddie' ) );
		$this->assertStringContainsString( $needle, $this->meta_of( $this->get( 'classic-pancakes', array( 'context' => 'edit' ) ) )[ self::NOTES ] ?? '' );
		wp_set_current_user( user_id( 'alice' ) );
		$this->assertSame( 'Alice: buy the lettuce at the organic shop.', $this->meta_of( $this->get( 'quick-salad', array( 'context' => 'edit' ) ) )[ self::NOTES ] ?? null );
	}

	public function test_author_updates_own_recipe_in_the_stored_format(): void {
		wp_set_current_user( user_id( 'alice' ) );
		$id  = recipe_id( 'quick-salad' );
		$res = $this->update(
			'quick-salad',
			array(
				'_acme_recipe_ingredients' => array(
					array( 'amount' => '1/2', 'unit' => '', 'item' => 'lemon' ),
					array( 'amount' => '3', 'unit' => 'tbsp', 'item' => 'olive oil & "vinegar"' ),
					array( 'item' => 'pepper' ),
				),
				'_acme_recipe_prep_time'   => 7,
				'_acme_recipe_cook_time'   => 0,
				'_acme_recipe_servings'    => 3,
				'_acme_recipe_difficulty'  => 'medium',
				self::NOTES                => "Line one\nLine two",
			)
		);
		$meta = $this->meta_of( $res );
		$this->assertSame( 3, $meta['_acme_recipe_servings'] );

		$stored = get_post_meta( $id, '_acme_recipe_ingredients', true );
		$this->assertIsArray( $stored, 'Ingredients must be stored as a serialized array' );
		$this->assertCount( 3, $stored );
		foreach ( $stored as $row ) {
			$this->assertIsArray( $row );
			$this->assertEqualsCanonicalizing( array( 'amount', 'unit', 'item' ), array_keys( $row ), 'Each stored ingredient has exactly amount/unit/item' );
		}
		$this->assertSame( array( '1/2', '', 'lemon' ), array( $stored[0]['amount'], $stored[0]['unit'], $stored[0]['item'] ) );
		$this->assertSame( 'olive oil & "vinegar"', $stored[1]['item'] );
		$this->assertSame( array( '', '', 'pepper' ), array( $stored[2]['amount'], $stored[2]['unit'], $stored[2]['item'] ) );
		$this->assertSame( 7, (int) get_post_meta( $id, '_acme_recipe_prep_time', true ) );
		$this->assertSame( 3, (int) get_post_meta( $id, '_acme_recipe_servings', true ) );
		$this->assertSame( 'medium', get_post_meta( $id, '_acme_recipe_difficulty', true ) );
		$this->assertSame( "Line one\nLine two", get_post_meta( $id, self::NOTES, true ) );
	}

	public function test_legacy_recipe_can_be_updated_by_an_editor(): void {
		wp_set_current_user( user_id( 'eddie' ) );
		$id   = recipe_id( 'grandmas-goulash' );
		$meta = $this->meta_of( $this->update( 'grandmas-goulash', array( '_acme_recipe_servings' => 8, '_acme_recipe_staff_pick' => true ) ) );
		$this->assertSame( 8, $meta['_acme_recipe_servings'] );
		$this->assertTrue( $meta['_acme_recipe_staff_pick'] );
		$this->assertTrue( (bool) get_post_meta( $id, '_acme_recipe_staff_pick', true ) );
		// Untouched legacy details still read the same.
		$this->assertSame( 90, $meta['_acme_recipe_cook_time'] );
		$this->assertCount( 4, $meta['_acme_recipe_ingredients'] );
	}

	public function test_staff_pick_is_editors_only(): void {
		wp_set_current_user( user_id( 'alice' ) );
		$id  = recipe_id( 'quick-salad' );
		$res = $this->update( 'quick-salad', array( '_acme_recipe_staff_pick' => true ) );
		$this->assertContains( $res->get_status(), array( 401, 403 ), 'Authors must not be able to set the staff pick flag' );
		wp_cache_flush();
		$this->assertFalse( (bool) get_post_meta( $id, '_acme_recipe_staff_pick', true ) );

		wp_set_current_user( user_id( 'eddie' ) );
		$meta = $this->meta_of( $this->update( 'quick-salad', array( '_acme_recipe_staff_pick' => true ) ) );
		$this->assertTrue( $meta['_acme_recipe_staff_pick'] );
		wp_set_current_user( 1 );
		$meta = $this->meta_of( $this->update( 'quick-salad', array( '_acme_recipe_staff_pick' => false ) ) );
		$this->assertFalse( $meta['_acme_recipe_staff_pick'] );
	}

	public function test_author_can_save_with_the_full_meta_object_the_editor_sends(): void {
		// The block editor sends all meta values back, including the unchanged staff pick flag.
		wp_set_current_user( user_id( 'alice' ) );
		$meta               = $this->meta_of( $this->get( 'quick-salad', array( 'context' => 'edit' ) ) );
		$meta['_acme_recipe_prep_time'] = 9;
		unset( $meta['footnotes'] );
		$res = $this->update( 'quick-salad', $meta );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$this->assertSame( 9, (int) get_post_meta( recipe_id( 'quick-salad' ), '_acme_recipe_prep_time', true ) );
		$this->assertFalse( (bool) get_post_meta( recipe_id( 'quick-salad' ), '_acme_recipe_staff_pick', true ) );
	}

	public function test_permissions_follow_who_can_edit_the_recipe(): void {
		$before = get_post_meta( recipe_id( 'classic-pancakes' ), '_acme_recipe_servings', true );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->update( 'classic-pancakes', array( '_acme_recipe_servings' => 99 ) )->get_status() );
		wp_set_current_user( user_id( 'sam' ) );
		$this->assertSame( 403, $this->update( 'classic-pancakes', array( '_acme_recipe_servings' => 99 ) )->get_status() );
		wp_set_current_user( user_id( 'alice' ) );
		$this->assertSame( 403, $this->update( 'classic-pancakes', array( '_acme_recipe_servings' => 99 ) )->get_status(), "Authors can't edit other people's recipes" );
		wp_set_current_user( user_id( 'carl' ) );
		$this->assertSame( 403, $this->update( 'quick-salad', array( '_acme_recipe_servings' => 99 ) )->get_status(), "Contributors can't edit other people's recipes" );
		wp_cache_flush();
		$this->assertSame( (string) $before, (string) get_post_meta( recipe_id( 'classic-pancakes' ), '_acme_recipe_servings', true ) );
		$this->assertNotSame( '99', (string) get_post_meta( recipe_id( 'quick-salad' ), '_acme_recipe_servings', true ) );

		// Contributors can edit their own drafts.
		$meta = $this->meta_of( $this->update( 'draft-soup', array( '_acme_recipe_servings' => 5, '_acme_recipe_ingredients' => array( array( 'amount' => '2', 'unit' => 'l', 'item' => 'stock' ) ) ) ) );
		$this->assertSame( 5, $meta['_acme_recipe_servings'] );
		$this->assertSame( '2', get_post_meta( recipe_id( 'draft-soup' ), '_acme_recipe_ingredients', true )[0]['amount'] );
	}

	public static function invalid_values(): array {
		return array(
			'unknown difficulty'       => array( array( '_acme_recipe_difficulty' => 'extreme' ) ),
			'servings too high'        => array( array( '_acme_recipe_servings' => 101 ) ),
			'negative servings'        => array( array( '_acme_recipe_servings' => -2 ) ),
			'servings not a number'    => array( array( '_acme_recipe_servings' => 'lots' ) ),
			'negative prep time'       => array( array( '_acme_recipe_prep_time' => -5 ) ),
			'cook time not a number'   => array( array( '_acme_recipe_cook_time' => 'an hour' ) ),
			'ingredient without item'  => array( array( '_acme_recipe_ingredients' => array( array( 'amount' => '1', 'unit' => 'g' ) ) ) ),
			'ingredient empty item'    => array( array( '_acme_recipe_ingredients' => array( array( 'amount' => '1', 'unit' => 'g', 'item' => '' ) ) ) ),
			'ingredient extra field'   => array( array( '_acme_recipe_ingredients' => array( array( 'item' => 'salt', 'price' => '1.00' ) ) ) ),
			'ingredient as string'     => array( array( '_acme_recipe_ingredients' => array( '200 g flour' ) ) ),
			'ingredients not an array' => array( array( '_acme_recipe_ingredients' => 'flour, eggs' ) ),
			'staff pick not boolean'   => array( array( '_acme_recipe_staff_pick' => 'maybe' ) ),
		);
	}

	/**
	 * @dataProvider invalid_values
	 */
	public function test_invalid_values_are_rejected( array $meta ): void {
		wp_set_current_user( 1 );
		$id     = recipe_id( 'classic-pancakes' );
		$key    = array_key_first( $meta );
		$before = get_post_meta( $id, $key, true );
		$res    = $this->update( 'classic-pancakes', $meta );
		$this->assertSame( 400, $res->get_status(), 'Expected 400 for ' . wp_json_encode( $meta ) . ', got ' . wp_json_encode( $res->get_data() ) );
		wp_cache_flush();
		$this->assertSame( $before, get_post_meta( $id, $key, true ), 'Nothing may be saved' );
	}
}
