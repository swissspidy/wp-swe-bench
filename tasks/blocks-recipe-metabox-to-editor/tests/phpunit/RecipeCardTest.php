<?php
/**
 * Recipe card on the front end: automatic card, the Recipe card block, no double cards.
 */

use function WPSB\Recipes\cards;
use function WPSB\Recipes\recipe_id;

class RecipeCardTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		parent::tearDown();
	}

	private function recipe( string $content, array $meta = array() ): int {
		$id = $this->create_post( array( 'post_type' => 'acme_recipe', 'post_content' => $content ) );
		foreach ( $meta + array(
			'_acme_recipe_ingredients' => array( array( 'amount' => '3', 'unit' => '', 'item' => 'apples' ) ),
			'_acme_recipe_servings'    => 2,
			'_acme_recipe_prep_time'   => 25,
			'_acme_recipe_notes'       => 'SECRET-NOTE',
		) as $k => $v ) {
			update_post_meta( $id, $k, $v );
		}
		$this->created[] = $id;
		return $id;
	}

	private function page( int $id ): string {
		$res = $this->http( 'GET', get_permalink( $id ) );
		$this->assertSame( 200, $res['status'] );
		$html = $res['body'];
		$this->assertStringNotContainsString( 'SECRET-NOTE', $html, 'Kitchen notes must never be shown' );
		return $html;
	}

	public function test_legacy_recipe_card_is_unchanged(): void {
		$html  = $this->page( recipe_id( 'grandmas-goulash' ) );
		$cards = cards( $html );
		$this->assertCount( 1, $cards );
		$this->assertSame(
			array(
				'acme-recipe-card__prep'       => '20 min',
				'acme-recipe-card__cook'       => '1 h 30 min',
				'acme-recipe-card__servings'   => '6',
				'acme-recipe-card__difficulty' => 'Medium',
			),
			$cards[0]['meta']
		);
		$this->assertSame( array( array( '500 g', 'beef' ), array( '2', 'onions' ), array( '1 tbsp', 'paprika' ), array( '', 'salt to taste' ) ), $cards[0]['ingredients'] );
		$this->assertStringNotContainsString( 'Tuesday market', $html );
		$this->assertLessThan( strpos( $html, 'acme-recipe-card' ), strpos( $html, 'The family classic.' ), 'Automatic card comes after the content' );

		$this->assertMatchesRegularExpression( '#<script type="application/ld\+json" class="acme-recipe-schema">.*"recipeIngredient":\["500 g beef","2 onions","1 tbsp paprika","salt to taste"\].*"cookTime":"PT90M"#', $html );

		$cards = cards( $this->page( recipe_id( 'country-bread' ) ) );
		$this->assertCount( 1, $cards );
		$this->assertSame( array( '350 ml', 'water' ), $cards[0]['ingredients'][3] );
		$this->assertSame( 'Hard', $cards[0]['meta']['acme-recipe-card__difficulty'] );

		$this->assertCount( 0, cards( $this->page( recipe_id( 'mystery-dish' ) ) ), 'No card without details' );
	}

	public function test_block_places_the_card_and_prevents_the_automatic_one(): void {
		$id    = $this->recipe( "<!-- wp:paragraph -->\n<p>BEFORE-CARD</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:acme/recipe-card /-->\n\n<!-- wp:paragraph -->\n<p>AFTER-CARD</p>\n<!-- /wp:paragraph -->" );
		$html  = $this->page( $id );
		$cards = cards( $html );
		$this->assertCount( 1, $cards, 'Exactly one card when the block is used' );
		$this->assertSame( array( array( '3', 'apples' ) ), $cards[0]['ingredients'] );
		$this->assertSame( '25 min', $cards[0]['meta']['acme-recipe-card__prep'] );
		$before = strpos( $html, 'BEFORE-CARD' );
		$card   = strpos( $html, 'class="acme-recipe-card' );
		$after  = strpos( $html, 'AFTER-CARD' );
		$this->assertTrue( $before < $card && $card < $after, 'The card must be rendered where the block is' );
	}

	public function test_block_nested_in_a_group(): void {
		$id    = $this->recipe( "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\"><!-- wp:acme/recipe-card /--></div>\n<!-- /wp:group -->\n\n<!-- wp:paragraph -->\n<p>TAIL</p>\n<!-- /wp:paragraph -->" );
		$html  = $this->page( $id );
		$this->assertCount( 1, cards( $html ) );
		$this->assertLessThan( strpos( $html, 'TAIL' ), strpos( $html, 'class="acme-recipe-card' ) );
	}

	public function test_recipe_without_block_still_gets_the_card(): void {
		$id    = $this->recipe( "<!-- wp:paragraph -->\n<p>ONLY-TEXT</p>\n<!-- /wp:paragraph -->" );
		$html  = $this->page( $id );
		$cards = cards( $html );
		$this->assertCount( 1, $cards );
		$this->assertLessThan( strpos( $html, 'class="acme-recipe-card' ), strpos( $html, 'ONLY-TEXT' ) );
	}

	public function test_block_is_dynamic_and_uses_the_card_filter(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme/recipe-card' );
		$this->assertNotNull( $type, 'acme/recipe-card must be registered' );
		$this->assertTrue( $type->is_dynamic() );

		$id     = recipe_id( 'classic-pancakes' );
		$filter = static fn( $html ) => str_replace( 'acme-recipe-card__title', 'acme-recipe-card__title filtered-card', $html );
		add_filter( 'acme_recipes_card_html', $filter );
		try {
			$block = new WP_Block( array( 'blockName' => 'acme/recipe-card', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'postId' => $id, 'postType' => 'acme_recipe' ) );
			$html  = $block->render();
		} finally {
			remove_filter( 'acme_recipes_card_html', $filter );
		}
		$cards = cards( $html );
		$this->assertCount( 1, $cards );
		$this->assertStringContainsString( 'filtered-card', $html );
		$this->assertCount( 4, $cards[0]['ingredients'] );
		$this->assertStringNotContainsString( 'Mill & Co', $html );
	}
}
