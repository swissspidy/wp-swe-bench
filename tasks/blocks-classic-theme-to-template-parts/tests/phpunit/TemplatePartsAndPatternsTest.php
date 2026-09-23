<?php
/**
 * Template parts (Site Editor), customizations, patterns and the synced Contact card.
 */

use function WPSB\Corp\cli;
use function WPSB\Corp\dom;
use function WPSB\Corp\footer_html;
use function WPSB\Corp\header_html;
use function WPSB\Corp\text;

class TemplatePartsAndPatternsTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function tearDown(): void {
		foreach ( get_posts( array( 'post_type' => array( 'wp_template_part' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
			wp_delete_post( $p->ID, true );
		}
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function parts(): array {
		$this->login_as( 1 );
		$res = $this->rest( 'GET', '/wp/v2/template-parts', array( 'context' => 'edit' ) );
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		$out = array();
		foreach ( $this->rest_data( $res ) as $part ) {
			$out[ $part['id'] ] = $part;
		}
		return $out;
	}

	public function test_theme_stays_classic(): void {
		$this->assertFalse( wp_is_block_theme(), 'The theme must stay a classic theme' );
		$this->assertSame( 'acme-corporate', get_stylesheet() );
		$this->assertSame( '4.0.0', wp_get_theme()->get( 'Version' ) );
		foreach ( array( 'acme_corporate_site_branding', 'acme_corporate_social_links', 'acme_corporate_footer_text', 'acme_corporate_contact_details', 'acme_corporate_get_social_links', 'acme_corporate_get_footer_text', 'acme_corporate_header_cta' ) as $fn ) {
			$this->assertTrue( function_exists( $fn ), "$fn() must stay available for child themes" );
		}
	}

	public function test_header_and_footer_parts_are_available_to_the_site_editor(): void {
		$parts = $this->parts();
		$this->assertArrayHasKey( 'acme-corporate//header', $parts );
		$this->assertArrayHasKey( 'acme-corporate//footer', $parts );
		$this->assertSame( 'header', $parts['acme-corporate//header']['area'] );
		$this->assertSame( 'footer', $parts['acme-corporate//footer']['area'] );
		foreach ( array( 'header', 'footer' ) as $slug ) {
			$content = $parts[ "acme-corporate//$slug" ]['content']['raw'];
			$this->assertNotSame( '', trim( $content ) );
			$blocks = array_filter( parse_blocks( $content ), static fn( $b ) => null !== $b['blockName'] );
			$this->assertNotEmpty( $blocks, "$slug part must contain blocks" );
		}
		$this->assertTrue( current_theme_supports( 'block-template-parts' ) || wp_is_block_theme() );
	}

	public function test_customized_footer_is_used_site_wide(): void {
		$this->login_as( 1 );
		$res = $this->rest(
			'POST',
			'/wp/v2/template-parts/acme-corporate//footer',
			array(),
			array( 'content' => "<!-- wp:paragraph -->\n<p>CUSTOM FOOTER BY MARKETING</p>\n<!-- /wp:paragraph -->" )
		);
		$this->assertSame( 200, $res->get_status(), wp_json_encode( $res->get_data() ) );
		wp_set_current_user( 0 );

		foreach ( array( '/', '/about/', '/new-plant-opens/', '/does-not-exist-at-all/' ) as $path ) {
			$page   = $this->http( 'GET', $path )['body'];
			$footer = text( dom( footer_html( $page ) )->query( '//body' )->item( 0 ) );
			$this->assertStringContainsString( 'CUSTOM FOOTER BY MARKETING', $footer, "Customized footer must be used on $path" );
			$this->assertStringNotContainsString( 'All rights reserved', $footer, "Old footer must be gone on $path" );
			$this->assertStringContainsString( 'Acme Corporation', text( dom( header_html( $page ) )->query( '//body' )->item( 0 ) ) );
		}
	}

	public function test_customized_header_is_used(): void {
		$this->login_as( 1 );
		$res = $this->rest(
			'POST',
			'/wp/v2/template-parts/acme-corporate//header',
			array(),
			array( 'content' => "<!-- wp:site-title /-->\n\n<!-- wp:paragraph -->\n<p>SPRING SALE BANNER</p>\n<!-- /wp:paragraph -->" )
		);
		$this->assertSame( 200, $res->get_status() );
		wp_set_current_user( 0 );
		$page   = $this->http( 'GET', '/services/' )['body'];
		$header = header_html( $page );
		$this->assertStringContainsString( 'SPRING SALE BANNER', $header );
		$this->assertStringNotContainsString( 'Request a quote', $header );
		$this->assertStringContainsString( 'All rights reserved', text( dom( footer_html( $page ) )->query( '//body' )->item( 0 ) ) );
	}

	public function test_patterns_keep_their_slugs_and_come_from_the_patterns_folder(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$expected = array(
			'acme-corporate/hero'         => 'Hero',
			'acme-corporate/services'     => 'Services',
			'acme-corporate/testimonials' => 'Testimonials',
			'acme-corporate/cta'          => 'Call to action',
		);
		$dir = wp_normalize_path( get_stylesheet_directory() . '/patterns/' );
		foreach ( $expected as $slug => $title ) {
			$this->assertTrue( $registry->is_registered( $slug ), "$slug must stay registered" );
			$p = $registry->get_registered( $slug );
			$this->assertSame( $title, $p['title'] );
			$this->assertContains( 'acme', $p['categories'] ?? array() );
			$this->assertNotSame( false, $p['inserter'] ?? true, "$slug must be in the inserter" );
			$this->assertArrayHasKey( 'filePath', $p, "$slug must be registered from a file in patterns/" );
			$this->assertStringStartsWith( $dir, wp_normalize_path( $p['filePath'] ) );
		}
		$this->assertTrue( WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'acme' ) );
		$this->assertSame( 'Acme', WP_Block_Pattern_Categories_Registry::get_instance()->get_registered( 'acme' )['label'] );

		// Helper patterns for the parts are hidden from the inserter.
		foreach ( $registry->get_all_registered() as $p ) {
			if ( 0 === strpos( $p['name'], 'acme-corporate/' ) && ! isset( $expected[ $p['name'] ] ) ) {
				$this->assertFalse( (bool) ( $p['inserter'] ?? true ), $p['name'] . ' must not be in the inserter' );
			}
		}

		$hero = do_blocks( '<!-- wp:pattern {"slug":"acme-corporate/hero"} /-->' );
		$this->assertStringContainsString( 'Industrial solutions that scale', $hero );
		$this->assertStringContainsString( 'has-secondary-background-color', $hero );
	}

	public function test_pages_embedding_patterns_render_as_before(): void {
		$page = $this->http( 'GET', '/' )['body'];
		$x    = dom( $page );
		$main = text( $x->query( '//main' )->item( 0 ) );
		foreach ( array( 'Industrial solutions that scale', 'What we do', 'Consulting', 'Manufacturing', 'Maintenance', 'Ready to start your project?', 'Talk to us' ) as $needle ) {
			$this->assertStringContainsString( $needle, $main );
		}
		$this->assertSame( 3, $x->query( '//main//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-column ")]' )->length );
		$about = text( dom( $this->http( 'GET', '/about/' )['body'] )->query( '//main' )->item( 0 ) );
		$this->assertStringContainsString( 'Acme delivered our production line two weeks early.', $about );
	}

	private function contact_cards(): array {
		return get_posts(
			array(
				'post_type'   => 'wp_block',
				'post_status' => 'any',
				'title'       => 'Contact card',
				'numberposts' => -1,
			)
		);
	}

	public function test_contact_card_synced_pattern_is_created_exactly_once(): void {
		// The updated theme has run: front end, admin and WP-CLI requests.
		$this->http( 'GET', '/' );
		$admin = $this->create_user( 'administrator' );
		$login = $this->http_login( $admin );
		$this->http( 'GET', '/wp-admin/', array( 'login' => $login ) );
		$this->http( 'GET', '/about/' );
		cli( 'option get blogname' );

		$cards = $this->contact_cards();
		$this->assertCount( 1, $cards, 'Exactly one "Contact card" pattern expected' );
		$card = $cards[0];
		$this->assertSame( 'publish', $card->post_status );
		$this->assertNotSame( 'unsynced', get_post_meta( $card->ID, 'wp_pattern_sync_status', true ), 'Contact card must be a synced pattern' );
		$this->assertStringContainsString( 'tel:+15550102030', $card->post_content );
		$this->assertStringContainsString( 'mailto:hello@acme-corp.example', $card->post_content );
		$terms = wp_get_object_terms( $card->ID, 'wp_pattern_category', array( 'fields' => 'names' ) );
		$this->assertContains( 'Acme', $terms, 'Contact card must be in the Acme pattern category' );
		$blocks = array_filter( parse_blocks( $card->post_content ), static fn( $b ) => null !== $b['blockName'] );
		$this->assertNotEmpty( $blocks, 'Contact card must contain blocks' );

		// Visible to the editor as a user pattern.
		$this->login_as( $admin );
		$res = $this->rest( 'GET', '/wp/v2/blocks', array( 'search' => 'Contact card', 'context' => 'edit' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertCount( 1, array_filter( $this->rest_data( $res ), static fn( $b ) => 'Contact card' === ( $b['title']['raw'] ?? '' ) ) );
		wp_set_current_user( 0 );

		// Editors own it: edits stay, deleting it is final.
		wp_update_post( array( 'ID' => $card->ID, 'post_content' => '<!-- wp:paragraph --><p>Call us!</p><!-- /wp:paragraph -->' ) );
		$this->http( 'GET', '/' );
		$this->assertSame( '<!-- wp:paragraph --><p>Call us!</p><!-- /wp:paragraph -->', get_post( $card->ID )->post_content );
		$this->assertCount( 1, $this->contact_cards() );

		wp_delete_post( $card->ID, true );
		$this->http( 'GET', '/' );
		$this->http( 'GET', '/wp-admin/', array( 'login' => $login ) );
		cli( 'option get blogname' );
		clean_post_cache( $card->ID );
		$this->assertCount( 0, $this->contact_cards(), 'A deleted Contact card must not come back' );
		wp_delete_user( $admin );
	}
}
