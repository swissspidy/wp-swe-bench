<?php
/**
 * Front end of existing cards (pass-to-pass: saved markup must keep rendering as before).
 */

class MediaCardFrontEndTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function cards( string $html ): array {
		preg_match_all( '#<div[^>]*class="wp-block-acme-media-card(?: [^"]*)?"[^>]*>.*?</div></div>#s', $html, $m );
		return $m[0];
	}

	public function test_1_2_cards_render_unchanged(): void {
		$res = $this->http( 'GET', '/spring-campaign/' );
		$this->assertSame( 200, $res['status'] );
		$cards = $this->cards( $res['body'] );
		$this->assertCount( 3, $cards, $res['body'] );
		$this->assertStringContainsString( 'id="spring-sale"', $cards[0] );
		$this->assertStringContainsString( 'alignwide', $cards[0] );
		$this->assertMatchesRegularExpression( '#<figure class="wp-block-acme-media-card__media"><a href="https://shop.example.org/spring"><img [^>]*src="[^"]+sunflowers[^"]*\.png" alt="Sunflowers in a vase" class="wp-image-\d+[^"]*"[^>]*></a></figure>#', $cards[0] );
		$this->assertStringContainsString( '<h3 class="wp-block-acme-media-card__heading">Spring <em>sale</em></h3>', $cards[0] );
		$this->assertMatchesRegularExpression( '#<a class="wp-block-acme-media-card__cta is-external" href="https://shop.example.org/spring" rel="noopener">Shop now</a>|<a class="wp-block-acme-media-card__cta is-external" rel="noopener" href="https://shop.example.org/spring">Shop now</a>|<a rel="noopener" class="wp-block-acme-media-card__cta is-external" href="https://shop.example.org/spring">Shop now</a>#', $cards[0] );
		$this->assertStringContainsString( 'has-accent-1-background-color', $cards[1] );
		$this->assertStringContainsString( 'is-style-outlined', $cards[2] );
		$this->assertStringContainsString( '<a class="wp-block-acme-media-card__cta" href="/team">Meet us</a>', $cards[2] );
	}

	public function test_1_0_card_renders_unchanged(): void {
		$res = $this->http( 'GET', '/about-acme/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( '<h2 class="wp-block-acme-media-card__title">Since 1999</h2>', $res['body'] );
		$this->assertStringContainsString( '<a class="wp-block-acme-media-card__button" href="/about">About us</a>', $res['body'] );
	}

	public function test_pattern_is_still_registered(): void {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'acme/promo-trio' );
		$this->assertNotNull( $pattern );
		$this->assertSame( 3, substr_count( $pattern['content'], '<!-- wp:acme/media-card' ) );
		$res = $this->http( 'GET', '/why-acme/' );
		$this->assertSame( 3, substr_count( $res['body'], '<h3 class="wp-block-acme-media-card__heading">' ) );
	}

	public function test_block_is_registered_with_styles(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'acme/media-card' );
		$this->assertNotNull( $type );
		$styles = wp_list_pluck( WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'acme/media-card' ), 'name' );
		$this->assertEqualsCanonicalizing( array( 'outlined', 'elevated' ), array_values( $styles ) );
	}
}
