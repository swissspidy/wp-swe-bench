<?php
/**
 * What the Site Editor sees (templates REST API) and what happens when an editor
 * moves or removes the automatically placed form and saves the template.
 */

use function WPSB\Newsletter\analyse;
use function WPSB\Newsletter\settings_with_placements;
use function WPSB\Newsletter\field_value;
use const WPSB\Newsletter\OPTION;

class SiteEditorTemplatesTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_option;
	private array $saved_templates = array();
	private array $existing_ids    = array();

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->saved_option = get_option( OPTION );
		foreach ( $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part')" ) as $row ) {
			$this->saved_templates[ (int) $row->ID ] = array(
				'content' => $row->post_content,
				'meta'    => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $row->ID ), ARRAY_A ),
			);
		}
		$this->existing_ids = array_keys( $this->saved_templates );
		$this->login_as( 1 );
	}

	protected function tearDown(): void {
		global $wpdb;
		update_option( OPTION, $this->saved_option );
		// Restore the templates the site had before the test.
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part')" );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( ! isset( $this->saved_templates[ $id ] ) ) {
				wp_delete_post( $id, true );
				continue;
			}
			$wpdb->update( $wpdb->posts, array( 'post_content' => $this->saved_templates[ $id ]['content'] ), array( 'ID' => $id ) );
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ) );
			foreach ( $this->saved_templates[ $id ]['meta'] as $m ) {
				$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $m['meta_key'], 'meta_value' => $m['meta_value'] ) );
			}
			clean_post_cache( $id );
		}
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('post','page'))" );
		parent::tearDown();
	}

	private function get_template( string $type, string $id ): array {
		$res = $this->rest( 'GET', "/wp/v2/$type/$id", array( 'context' => 'edit' ) );
		$this->assertSame( 200, $res->get_status(), "GET $type $id: " . wp_json_encode( $res->get_data() ) );
		return $this->rest_data( $res );
	}

	private function save_template( string $type, string $id, string $content ): void {
		$res = $this->rest( 'POST', "/wp/v2/$type/$id", array(), array( 'content' => $content ) );
		$this->assertSame( 200, $res->get_status(), "save $type $id: " . wp_json_encode( $res->get_data() ) );
		wp_cache_flush();
	}

	/** Top-level-agnostic flat list of block names in document order. */
	private function names( string $content ): array {
		$out  = array();
		$walk = static function ( array $blocks ) use ( &$walk, &$out ) {
			foreach ( $blocks as $b ) {
				if ( $b['blockName'] ) {
					$out[] = $b['blockName'];
				}
				$walk( $b['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $content ) );
		return $out;
	}

	/** The block that directly follows core/post-content among its siblings (null if none). */
	private function sibling_after_post_content( string $content ): ?array {
		$found = null;
		$walk  = static function ( array $blocks ) use ( &$walk, &$found ) {
			$blocks = array_values( array_filter( $blocks, static fn( $b ) => null !== $b['blockName'] ) );
			foreach ( $blocks as $i => $b ) {
				if ( 'core/post-content' === $b['blockName'] ) {
					$found = $blocks[ $i + 1 ] ?? array( 'blockName' => null );
					return;
				}
				$walk( $b['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $content ) );
		return $found;
	}

	private function page( string $path ): array {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		return analyse( $res['body'] ) + array( 'html' => $res['body'] );
	}

	public function test_site_editor_sees_the_form_in_the_single_template_and_footer(): void {
		$single = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$raw    = $single['content']['raw'];
		$next   = $this->sibling_after_post_content( $raw );
		$this->assertNotNull( $next, 'core/post-content missing from the Single template' );
		$this->assertSame( 'acme/newsletter-signup', $next['blockName'], 'The Site Editor must show the signup block right after the post content' );
		$this->assertSame( 1, count( array_keys( $this->names( $raw ), 'acme/newsletter-signup', true ) ) );
		$this->assertStringContainsString( 'acme-thanks', $raw, 'the site customization is kept' );

		$page = $this->get_template( 'templates', 'twentytwentyfive//page' );
		$this->assertNotContains( 'acme/newsletter-signup', $this->names( $page['content']['raw'] ), 'Pages must not get the form' );

		$footer = $this->get_template( 'template-parts', 'twentytwentyfive//footer' );
		$top    = array_values( array_filter( parse_blocks( $footer['content']['raw'] ), static fn( $b ) => null !== $b['blockName'] ) );
		$this->assertNotEmpty( $top );
		$this->assertSame( 'acme/newsletter-signup', end( $top )['blockName'], 'The footer template part must end with the signup block' );

		$header = $this->get_template( 'template-parts', 'twentytwentyfive//header' );
		$this->assertNotContains( 'acme/newsletter-signup', $this->names( $header['content']['raw'] ) );
	}

	public function test_site_editor_follows_the_placement_settings(): void {
		update_option( OPTION, settings_with_placements( array( 'footer' ) ) );
		$single = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$this->assertNotContains( 'acme/newsletter-signup', $this->names( $single['content']['raw'] ) );
		$footer = $this->get_template( 'template-parts', 'twentytwentyfive//footer' );
		$this->assertContains( 'acme/newsletter-signup', $this->names( $footer['content']['raw'] ) );

		update_option( OPTION, settings_with_placements( array( 'after_content' ) ) );
		$single = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$this->assertContains( 'acme/newsletter-signup', $this->names( $single['content']['raw'] ) );
		$footer = $this->get_template( 'template-parts', 'twentytwentyfive//footer' );
		$this->assertNotContains( 'acme/newsletter-signup', $this->names( $footer['content']['raw'] ) );
	}

	public function test_removing_the_form_from_the_single_template_is_respected(): void {
		$single  = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$raw     = $single['content']['raw'];
		$removed = preg_replace( '#\s*<!-- wp:acme/newsletter-signup\b[^>]*?/-->#', '', $raw, -1, $n );
		$this->assertSame( 1, $n, 'expected one self-closing signup block in the template' );
		$this->save_template( 'templates', 'twentytwentyfive//single', $removed );

		// Not re-inserted, neither in the editor nor on the front end.
		$single = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$this->assertNotContains( 'acme/newsletter-signup', $this->names( $single['content']['raw'] ), 'removed block was re-inserted into the template' );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['after_content'], 'removed form came back on the front end' );
		$this->assertCount( 0, $p['in_content'] );
		$this->assertCount( 1, $p['in_footer'], 'The untouched footer keeps its form' );
		$this->assertStringContainsString( 'Thanks for reading the Acme blog.', $p['html'] );
	}

	public function test_moving_the_form_keeps_a_single_copy(): void {
		$single = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$raw    = $single['content']['raw'];
		$this->assertSame( 1, preg_match( '#<!-- wp:acme/newsletter-signup\b[^>]*?/-->#', $raw, $m ) );
		$block = $m[0];
		$moved = str_replace( $block, '', $raw );
		$moved = preg_replace( '#(<!-- wp:post-content\b)#', $block . "\n$1", $moved, 1 );
		$this->save_template( 'templates', 'twentytwentyfive//single', $moved );

		$single = $this->get_template( 'templates', 'twentytwentyfive//single' );
		$this->assertSame( 1, count( array_keys( $this->names( $single['content']['raw'] ), 'acme/newsletter-signup', true ) ), 'moved block must not be inserted a second time' );

		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['before_content'], 'moved form renders where the editor put it' );
		$this->assertCount( 0, $p['after_content'] );
		$this->assertCount( 2, $p['forms'] );
	}

	public function test_removing_the_form_from_the_footer_is_respected(): void {
		$footer  = $this->get_template( 'template-parts', 'twentytwentyfive//footer' );
		$removed = preg_replace( '#\s*<!-- wp:acme/newsletter-signup\b[^>]*?/-->#', '', $footer['content']['raw'], -1, $n );
		$this->assertSame( 1, $n );
		$this->save_template( 'template-parts', 'twentytwentyfive//footer', $removed );

		$footer = $this->get_template( 'template-parts', 'twentytwentyfive//footer' );
		$this->assertNotContains( 'acme/newsletter-signup', $this->names( $footer['content']['raw'] ) );

		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 0, $p['in_footer'], 'removed footer form came back' );
		$this->assertCount( 1, $p['after_content'], 'The Single template was not touched and keeps its form' );
		$this->assertCount( 1, $p['forms'] );
		$p = $this->page( '/about/' );
		$this->assertCount( 0, $p['forms'] );
	}

	public function test_template_saved_before_the_update_still_gets_the_form(): void {
		// The seeded Single template was customized (and saved) before this version:
		// it never contained the form, but nobody removed it either.
		global $wpdb;
		$stored = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE post_type = 'wp_template' AND post_name = 'single'" );
		$this->assertNotEmpty( $stored );
		$this->assertStringNotContainsString( 'acme/newsletter-signup', $stored );
		$p = $this->page( '/welcome-to-the-new-blog/' );
		$this->assertCount( 1, $p['after_content'] );
		$this->assertSame( 'content', field_value( $p['after_content'][0], 'acme_source' ) );
	}
}
