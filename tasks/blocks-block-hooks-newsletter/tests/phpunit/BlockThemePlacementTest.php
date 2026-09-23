<?php
/**
 * Automatic placement in a block theme (Twenty Twenty-Five), served by Playground.
 */

use function WPSB\Newsletter\analyse;
use function WPSB\Newsletter\legacy_settings;
use function WPSB\Newsletter\settings_with_placements;
use function WPSB\Newsletter\closest;
use function WPSB\Newsletter\field_value;
use const WPSB\Newsletter\OPTION;

class BlockThemePlacementTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_option;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_option = get_option( OPTION );
		$this->assertSame( 'twentytwentyfive', get_stylesheet() );
	}

	protected function tearDown(): void {
		update_option( OPTION, $this->saved_option );
		parent::tearDown();
	}

	private function page( string $path ): array {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		$this->assertStringNotContainsString( 'critical error', $res['body'] );
		return analyse( $res['body'] ) + array( 'html' => $res['body'] );
	}

	public function test_single_post_gets_form_after_the_content_and_in_the_footer(): void {
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertNotNull( $p['post_content'], 'post content block not found' );
		$this->assertCount( 0, $p['in_content'], 'In block themes the form must no longer be appended inside the post content' );
		$this->assertCount( 1, $p['after_content'], 'Expected the signup form directly after the post content' );
		$this->assertNotNull( $p['footer'], 'footer template part not found' );
		$this->assertCount( 1, $p['in_footer'], 'Expected the signup form in the footer' );
		$this->assertCount( 2, $p['forms'], 'Expected exactly two forms (after content + footer)' );

		// The footer form is the last thing in the footer template part.
		$last    = WPSB\Newsletter\last_element_child( $p['footer'] );
		$wrapper = closest( $p['in_footer'][0], 'acme-newsletter' );
		$this->assertNotNull( $wrapper );
		$this->assertTrue( $last && $last->isSameNode( $wrapper ), 'The footer form must be at the end of the footer template part' );

		// The site's customized Single template (stored in the DB) is still used.
		$this->assertStringContainsString( 'Thanks for reading the Acme blog.', $p['html'] );

		// Sources.
		$this->assertSame( 'content', field_value( $p['after_content'][0], 'acme_source' ) );
		$this->assertSame( 'footer', field_value( $p['in_footer'][0], 'acme_source' ) );

		// Form contract.
		foreach ( $p['forms'] as $form ) {
			$this->assertSame( 'post', strtolower( $form->getAttribute( 'method' ) ) );
			$this->assertStringContainsString( '/wp-admin/admin-post.php', $form->getAttribute( 'action' ) );
			$this->assertSame( 'acme_newsletter_subscribe', field_value( $form, 'action' ) );
			$this->assertNotEmpty( field_value( $form, '_acme_nonce' ) );
			$this->assertNotNull( field_value( $form, 'acme_email' ) );
			$this->assertStringContainsString( 'Get the Acme newsletter', closest( $form, 'acme-newsletter' )->textContent );
		}
	}

	public function test_pages_and_other_views_only_get_the_footer_form(): void {
		foreach ( array( '/about/', '/', '/tag/sponsored/' ) as $path ) {
			$p = $this->page( $path );
			$this->assertCount( 1, $p['in_footer'], "$path: expected the footer form" );
			// Anything else must be a form that is part of a post's own content (manual block/shortcode).
			foreach ( $p['forms'] as $form ) {
				if ( in_array( $form, $p['in_footer'], true ) ) {
					continue;
				}
				$this->assertNotNull( closest( $form->parentNode, 'wp-block-post-content' ), "$path: unexpected automatic form outside the footer" );
				$this->assertNotSame( 'content', field_value( $form, 'acme_source' ), "$path: automatic form outside single posts" );
			}
		}
		$this->assertCount( 1, $this->page( '/about/' )['forms'] );
	}

	public function test_element_ids_are_unique_and_labels_point_into_their_own_form(): void {
		$p     = $this->page( '/welcome-to-the-new-blog/' );
		$xpath = $p['xpath'];
		$ids   = array();
		foreach ( $xpath->query( '//*[@id]' ) as $el ) {
			$ids[] = $el->getAttribute( 'id' );
		}
		$dupes = array_keys( array_filter( array_count_values( $ids ), static fn( $n ) => $n > 1 ) );
		$this->assertSame( array(), $dupes, 'Duplicate id attributes on the page: ' . implode( ', ', $dupes ) );

		$this->assertCount( 2, $p['forms'] );
		foreach ( $p['forms'] as $form ) {
			$labels = $xpath->query( './/label[@for]', $form );
			$this->assertGreaterThanOrEqual( 3, $labels->length );
			foreach ( $labels as $label ) {
				$target = $xpath->query( '//*[@id="' . $label->getAttribute( 'for' ) . '"]' )->item( 0 );
				$this->assertNotNull( $target, 'label for=' . $label->getAttribute( 'for' ) . ' has no target' );
				$this->assertTrue( WPSB\Newsletter\contains_node( $form, $target ), 'label must point to a field of its own form' );
			}
			$email = $xpath->query( './/input[@name="acme_email"]', $form )->item( 0 );
			$this->assertNotEmpty( $email->getAttribute( 'id' ) );
			$this->assertSame( 1, $xpath->query( './/label[@for="' . $email->getAttribute( 'id' ) . '"]', $form )->length );
		}
	}

	public function test_posts_that_already_contain_a_form_do_not_get_a_second_one(): void {
		// Block placed manually in the post.
		$p = $this->page( '/spring-campaign/' );
		$this->assertCount( 1, $p['in_content'], 'The manually placed block must still render' );
		$this->assertSame( 'block', field_value( $p['in_content'][0], 'acme_source' ) );
		$this->assertStringContainsString( 'Join the spring list', closest( $p['in_content'][0], 'acme-newsletter' )->textContent );
		$this->assertCount( 0, $p['after_content'], 'No automatic form after content that already has one' );
		$this->assertCount( 1, $p['in_footer'] );
		$this->assertCount( 2, $p['forms'] );

		// Shortcode in a classic post.
		$p = $this->page( '/classic-post/' );
		$this->assertCount( 1, $p['in_content'] );
		$this->assertSame( 'shortcode', field_value( $p['in_content'][0], 'acme_source' ) );
		$this->assertCount( 0, $p['after_content'] );
		$this->assertCount( 2, $p['forms'] );
	}

	public function test_existing_auto_insert_filter_is_respected(): void {
		// mu-plugin: sponsored posts never get the automatic form.
		$p = $this->page( '/sponsored-review/' );
		$this->assertCount( 0, $p['after_content'] );
		$this->assertCount( 0, $p['in_content'] );
		$this->assertCount( 1, $p['in_footer'], 'The footer placement is not affected' );
	}

	public function test_placement_settings(): void {
		update_option( OPTION, settings_with_placements( array( 'footer' ) ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['after_content'], 'after_content disabled' );
		$this->assertCount( 1, $p['in_footer'] );
		$this->assertCount( 1, $p['forms'] );

		update_option( OPTION, settings_with_placements( array( 'after_content' ) ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['after_content'] );
		$this->assertCount( 0, $p['in_footer'], 'footer disabled' );
		$this->assertCount( 1, $p['forms'] );
		$p = $this->page( '/about/' );
		$this->assertCount( 0, $p['forms'] );

		update_option( OPTION, settings_with_placements( array() ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['forms'], 'no placements: no automatic forms at all' );
		$p = $this->page( '/spring-campaign/' );
		$this->assertCount( 1, $p['forms'], 'manually placed blocks are not affected by the placements' );
	}

	public function test_sites_that_have_not_saved_the_new_settings_yet(): void {
		// Old "auto_insert" off: no form after the content, but the footer placement is on.
		update_option( OPTION, legacy_settings( array( 'auto_insert' => false ) ) );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['after_content'] );
		$this->assertCount( 0, $p['in_content'] );
		$this->assertCount( 1, $p['in_footer'] );

		// Option missing entirely (fresh install): both on.
		delete_option( OPTION );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['after_content'] );
		$this->assertCount( 1, $p['in_footer'] );
		$this->assertStringContainsString( 'Get our newsletter', closest( $p['in_footer'][0], 'acme-newsletter' )->textContent, 'default heading' );
	}
}
