<?php
/**
 * "Clone" / "New Draft" / bulk "Clone" through the plugin's real admin actions (HTTP, nonces).
 */

use function WPSB\DuplicatePost\copies_of;
use function WPSB\DuplicatePost\raw_meta;
use function WPSB\DuplicatePost\raw_post;
use function WPSB\DuplicatePost\seeded;
use function WPSB\DuplicatePost\term_slugs;
use function WPSB\DuplicatePost\location_args;

class CloneTest extends WPSB\DuplicatePost\DuplicatePostTestCase {

	public function test_fixture_sanity(): void {
		$orig = seeded( 'pricing-cheatsheet' );
		$post = raw_post( $orig );
		$this->assertStringContainsString( '\u003cb\u003e', $post['post_content'] );
		$this->assertStringContainsString( 'C:\Users\Public\Exports', $post['post_content'] );
		$meta = raw_meta( $orig );
		$this->assertSame( array( 'C:\Users\Public\Exports' ), $meta['_acme_export_path'] );
		$this->assertCount( 3, $meta['_acme_keywords'] );
		$this->assertArrayHasKey( '_acme_cache_rendered', $meta );
	}

	public function test_clone_keeps_content_and_meta_byte_for_byte(): void {
		$orig = seeded( 'pricing-cheatsheet' );
		$copy = $this->duplicate_via( 'duplicate_post_clone', $orig );

		$o = raw_post( $orig );
		$c = raw_post( $copy );
		$this->assertSame( 'draft', $c['post_status'] );
		$this->assertSame( $o['post_title'], $c['post_title'] );
		$this->assertSame( $o['post_content'], $c['post_content'], 'Block markup (escaped attributes, code) must be copied unchanged' );
		$this->assertSame( $o['post_excerpt'], $c['post_excerpt'] );

		$this->assertSameMeta( $this->expected_meta( $orig ), $copy, 'clone' );
		$this->assertArrayNotHasKey( '_acme_cache_rendered', raw_meta( $copy ), 'Excluded meta (duplicate_post_blacklist) must still be skipped' );
		$this->assertArrayNotHasKey( '_acme_cache_etag', raw_meta( $copy ) );

		// Values still decode to the same data.
		$this->assertSame( get_post_meta( $orig, '_acme_price_rows', true ), get_post_meta( $copy, '_acme_price_rows', true ) );
		$this->assertEquals( get_post_meta( $orig, '_acme_layout', true ), get_post_meta( $copy, '_acme_layout', true ) );
		$this->assertNotNull( json_decode( get_post_meta( $copy, '_acme_pricing_json', true ) ), 'JSON meta must still be valid JSON' );

		$this->assertSameTerms( $orig, $copy, array( 'category', 'post_tag' ), 'clone' );
	}

	public function test_new_draft_keeps_content_meta_and_terms(): void {
		$orig = seeded( 'pricing-cheatsheet' );
		$before = copies_of( $orig );
		$res    = $this->link_action( 'duplicate_post_new_draft', $orig );
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$new = array_values( array_diff( copies_of( $orig ), $before ) );
		$this->assertCount( 1, $new );
		$copy = $new[0];
		$this->assertSame( (string) $copy, location_args( $res )['post'] ?? '', 'Redirects to the editor of the new draft' );

		$o = raw_post( $orig );
		$c = raw_post( $copy );
		$this->assertSame( 'draft', $c['post_status'] );
		$this->assertSame( $o['post_content'], $c['post_content'] );
		$this->assertSame( $o['post_excerpt'], $c['post_excerpt'] );
		$this->assertSameMeta( $this->expected_meta( $orig ), $copy, 'new draft' );
		$this->assertSameTerms( $orig, $copy, array( 'category', 'post_tag' ), 'new draft' );
	}

	public function test_clone_plain_post(): void {
		$orig = seeded( 'team-offsite-notes' );
		$copy = $this->duplicate_via( 'duplicate_post_clone', $orig );
		$o    = raw_post( $orig );
		$c    = raw_post( $copy );
		$this->assertSame( $o['post_title'], $c['post_title'] );
		$this->assertSame( $o['post_content'], $c['post_content'] );
		$this->assertSame( 'draft', $c['post_status'] );
		$this->assertSame( $this->expected_meta( $orig ), raw_meta( $copy ) );
		$this->assertSameTerms( $orig, $copy, array( 'category', 'post_tag' ), 'clone' );
		$this->assertSame( (string) $orig, get_post_meta( $copy, '_dp_original', true ) );
	}

	public function test_clone_recipe_keeps_cuisines(): void {
		$orig = seeded( 'pesto', 'acme_recipe' );
		$copy = $this->duplicate_via( 'duplicate_post_clone', $orig );
		$this->assertSame( 'acme_recipe', raw_post( $copy )['post_type'] );
		$this->assertSame( raw_post( $orig )['post_content'], raw_post( $copy )['post_content'] );
		$this->assertSame( $this->expected_meta( $orig ), raw_meta( $copy ) );
		$this->assertSameTerms( $orig, $copy, array( 'cuisine' ), 'recipe clone' );
	}

	public function test_clone_release_notes_keep_channels_and_components(): void {
		$orig = seeded( 'acme-3-2', 'acme_release' );
		$copy = $this->duplicate_via( 'duplicate_post_clone', $orig );
		$this->assertSame( 'acme_release', raw_post( $copy )['post_type'] );
		$this->assertSame( array( 'lts', 'stable' ), term_slugs( $copy, 'release_channel' ) );
		$this->assertSame( array( 'api', 'cli' ), term_slugs( $copy, 'component' ) );
		$this->assertSame( raw_post( $orig )['post_content'], raw_post( $copy )['post_content'] );
		$this->assertSameMeta( $this->expected_meta( $orig ), $copy, 'release clone' );
	}

	public function test_new_draft_of_release_notes_keeps_terms(): void {
		$orig = seeded( 'acme-3-2', 'acme_release' );
		$copy = $this->duplicate_via( 'duplicate_post_new_draft', $orig );
		$this->assertSameTerms( $orig, $copy, array( 'release_channel', 'component' ), 'release new draft' );
	}

	public function test_bulk_clone(): void {
		$pricing = seeded( 'pricing-cheatsheet' );
		$offsite = seeded( 'team-offsite-notes' );
		$nonce   = $this->nonce_for( $this->admin, 'bulk-posts', $this->login['logged_in'] );
		$res     = $this->http(
			'GET',
			'/wp-admin/edit.php?' . http_build_query(
				array(
					'post_type' => 'post',
					'action'    => 'duplicate_post_bulk_clone',
					'post'      => array( $pricing, $offsite ),
					'_wpnonce'  => $nonce,
				)
			),
			array( 'login' => $this->login )
		);
		$this->assertContains( $res['status'], array( 302, 303 ), substr( strip_tags( $res['body'] ), 0, 500 ) );
		$this->assertSame( '2', location_args( $res )['bulk_cloned'] ?? null );
		foreach ( array( $pricing, $offsite ) as $orig ) {
			$copies = copies_of( $orig );
			$this->assertCount( 1, $copies );
			$this->assertSame( raw_post( $orig )['post_content'], raw_post( $copies[0] )['post_content'] );
			$this->assertSameMeta( $this->expected_meta( $orig ), $copies[0], 'bulk clone' );
			$this->assertSameTerms( $orig, $copies[0], array( 'category', 'post_tag' ), 'bulk clone' );
		}

		$release = seeded( 'acme-3-2', 'acme_release' );
		$res     = $this->http(
			'GET',
			'/wp-admin/edit.php?' . http_build_query(
				array(
					'post_type' => 'acme_release',
					'action'    => 'duplicate_post_bulk_clone',
					'post'      => array( $release ),
					'_wpnonce'  => $nonce,
				)
			),
			array( 'login' => $this->login )
		);
		$this->assertContains( $res['status'], array( 302, 303 ) );
		$copies = copies_of( $release );
		$this->assertCount( 1, $copies );
		$this->assertSameTerms( $release, $copies[0], array( 'release_channel', 'component' ), 'release bulk clone' );
	}

	public function test_taxonomy_and_meta_excludelists_still_apply(): void {
		$this->set_option( 'duplicate_post_taxonomies_blacklist', array( 'post_tag', 'component' ) );
		$this->set_option( 'duplicate_post_blacklist', '_acme_cache_*,_acme_summary' );

		$orig = seeded( 'team-offsite-notes' );
		$copy = $this->duplicate_via( 'duplicate_post_clone', $orig );
		$this->assertSame( array(), term_slugs( $copy, 'post_tag' ) );
		$this->assertSame( term_slugs( $orig, 'category' ), term_slugs( $copy, 'category' ) );
		$meta = raw_meta( $copy );
		$this->assertArrayNotHasKey( '_acme_summary', $meta );
		$this->assertSame( raw_meta( $orig )['_acme_agenda'], $meta['_acme_agenda'] );

		$release = seeded( 'acme-3-2', 'acme_release' );
		$copy    = $this->duplicate_via( 'duplicate_post_clone', $release );
		$this->assertSame( array(), term_slugs( $copy, 'component' ), 'Excluded taxonomy must not be copied' );
		$this->assertSame( array( 'lts', 'stable' ), term_slugs( $copy, 'release_channel' ) );
	}

	public function test_invalid_nonce_and_missing_capability_do_not_copy(): void {
		$orig = seeded( 'pricing-cheatsheet' );

		$res = $this->link_action( 'duplicate_post_clone', $orig, null, 'deadbeef00' );
		$this->assertSame( 403, $res['status'] );
		$this->assertSame( array(), copies_of( $orig ) );

		$sub   = $this->create_user( 'subscriber' );
		$login = $this->http_login( $sub );
		$res   = $this->link_action( 'duplicate_post_clone', $orig, $login );
		$this->assertNotContains( $res['status'], array( 200, 302 ) );
		$this->assertSame( array(), copies_of( $orig ) );

		// Nonce minted for another post.
		$other = seeded( 'team-offsite-notes' );
		$nonce = $this->nonce_for( $this->admin, 'duplicate_post_clone_' . $other, $this->login['logged_in'] );
		$res   = $this->link_action( 'duplicate_post_clone', $orig, null, $nonce );
		$this->assertSame( 403, $res['status'] );
		$this->assertSame( array(), copies_of( $orig ) );
	}
}
