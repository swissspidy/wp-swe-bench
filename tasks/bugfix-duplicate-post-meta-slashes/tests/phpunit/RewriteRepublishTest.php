<?php
/**
 * Rewrite & Republish: creating the copy (admin action) and republishing it (block editor REST
 * save, Classic editor form submission).
 */

use function WPSB\DuplicatePost\copies_of;
use function WPSB\DuplicatePost\location_args;
use function WPSB\DuplicatePost\raw_meta;
use function WPSB\DuplicatePost\raw_post;
use function WPSB\DuplicatePost\seeded;
use function WPSB\DuplicatePost\term_slugs;
use function WPSB\DuplicatePost\twin_of;

class RewriteRepublishTest extends WPSB\DuplicatePost\DuplicatePostTestCase {

	private function twin( string $slug, string $type, array $taxonomies ): int {
		$id              = twin_of( seeded( $slug, $type ), $taxonomies );
		$this->cleanup[] = $id;
		return $id;
	}

	private function rewrite( int $orig ): int {
		$res = $this->link_action( 'duplicate_post_rewrite', $orig );
		$this->assertContains( $res['status'], array( 302, 303 ), 'Rewrite & Republish did not redirect: ' . substr( strip_tags( $res['body'] ), 0, 500 ) );
		$copies = copies_of( $orig );
		$this->assertCount( 1, $copies );
		$copy = $copies[0];
		$this->assertSame( (string) $copy, location_args( $res )['post'] ?? '' );
		wp_cache_flush();
		$this->assertSame( '1', get_post_meta( $copy, '_dp_is_rewrite_republish_copy', true ) );
		$this->assertSame( (string) $copy, (string) get_post_meta( $orig, '_dp_has_rewrite_republish_copy', true ) );
		return $copy;
	}

	private function expected_meta_for_rewrite( int $orig ): array {
		// Rewrite & Republish copies all custom fields (the excludelist applies to Clone/New Draft only).
		return raw_meta( $orig );
	}

	public function test_rewrite_copy_matches_original(): void {
		$orig = $this->twin( 'pricing-cheatsheet', 'post', array( 'category', 'post_tag' ) );
		$copy = $this->rewrite( $orig );
		$o    = raw_post( $orig );
		$c    = raw_post( $copy );
		$this->assertSame( $o['post_title'], $c['post_title'] );
		$this->assertSame( $o['post_content'], $c['post_content'] );
		$this->assertSame( $o['post_excerpt'], $c['post_excerpt'] );
		$this->assertSameMeta( $this->expected_meta_for_rewrite( $orig ), $copy, 'rewrite copy' );
		$this->assertSameTerms( $orig, $copy, array( 'category', 'post_tag' ), 'rewrite copy' );
	}

	public function test_rewrite_copy_of_release_notes_keeps_terms(): void {
		$orig = $this->twin( 'acme-3-2', 'acme_release', array( 'release_channel', 'component' ) );
		$copy = $this->rewrite( $orig );
		$this->assertSameTerms( $orig, $copy, array( 'release_channel', 'component' ), 'rewrite copy' );
		$this->assertSameMeta( $this->expected_meta_for_rewrite( $orig ), $copy, 'rewrite copy' );
		$this->assertSame( raw_post( $orig )['post_content'], raw_post( $copy )['post_content'] );
	}

	public function test_republish_from_block_editor_keeps_content_meta_and_terms(): void {
		$orig    = $this->twin( 'pricing-cheatsheet', 'post', array( 'category', 'post_tag' ) );
		$content = raw_post( $orig )['post_content'];
		$copy    = $this->rewrite( $orig );

		// Editor changes on the copy: a custom field and a tag.
		update_post_meta( $copy, '_acme_export_path', wp_slash( 'D:\Archive\2026\exports' ) );
		wp_set_object_terms( $copy, array( 'regex', 'windows', 'billing', 'updated' ), 'post_tag' );
		$expected_meta = raw_meta( $copy );
		$this->assertSame( array( 'D:\Archive\2026\exports' ), $expected_meta['_acme_export_path'] );

		$this->republish_via_rest( $copy, 'posts', array( 'title' => 'Pricing & validation cheatsheet (2026)' ) );

		$o = raw_post( $orig );
		$this->assertSame( 'publish', $o['post_status'] );
		$this->assertSame( 'Pricing & validation cheatsheet (2026)', $o['post_title'] );
		$this->assertSame( $content, $o['post_content'], 'Republishing must not alter the block markup' );
		$this->assertSame( raw_post( $copy )['post_excerpt'], $o['post_excerpt'] );
		$actual = raw_meta( $orig );
		foreach ( $expected_meta as $key => $values ) {
			$this->assertSame( $values, $actual[ $key ] ?? null, "republish: meta $key differs" );
		}
		$this->assertSame( array( 'billing', 'regex', 'updated', 'windows' ), term_slugs( $orig, 'post_tag' ) );
		$this->assertSame( '1', get_post_meta( $copy, '_dp_has_been_republished', true ) );
	}

	public function test_republish_release_notes_updates_custom_terms(): void {
		$orig = $this->twin( 'acme-3-2', 'acme_release', array( 'release_channel', 'component' ) );
		$copy = $this->rewrite( $orig );

		// The editor adds the "Docs" component and moves the release from LTS to Beta.
		wp_set_object_terms( $copy, array( 'api', 'cli', 'docs' ), 'component' );
		wp_set_object_terms( $copy, array( 'beta', 'stable' ), 'release_channel' );

		$this->republish_via_rest( $copy, 'releases', array( 'title' => 'Acme 3.2.1' ) );

		$this->assertSame( 'Acme 3.2.1', raw_post( $orig )['post_title'] );
		$this->assertSame( array( 'api', 'cli', 'docs' ), term_slugs( $orig, 'component' ) );
		$this->assertSame( array( 'beta', 'stable' ), term_slugs( $orig, 'release_channel' ) );
		$this->assertSame( raw_post( $copy )['post_content'], raw_post( $orig )['post_content'] );
		$this->assertStringContainsString( 'C:\ProgramData\Acme\config.json', raw_post( $orig )['post_content'] );
		$this->assertStringContainsString( '\u002d\u002d', raw_post( $orig )['post_content'] );
		$this->assertSame( raw_meta( $copy ), raw_meta( $orig ) );
	}

	public function test_republish_from_classic_editor(): void {
		$orig    = $this->twin( 'pricing-cheatsheet', 'post', array( 'category', 'post_tag' ) );
		$content = raw_post( $orig )['post_content'] . "\n\n<!-- wp:paragraph -->\n<p>Escape a backslash as <code>\\\\</code>.</p>\n<!-- /wp:paragraph -->";
		$copy    = $this->rewrite( $orig );

		$res = $this->http(
			'POST',
			'/wp-admin/post.php',
			array(
				'login' => $this->login,
				'body'  => array(
					'_wpnonce'             => $this->nonce_for( $this->admin, 'update-post_' . $copy, $this->login['logged_in'] ),
					'_wp_http_referer'     => '/wp-admin/post.php?post=' . $copy . '&action=edit',
					'user_ID'              => $this->admin,
					'action'               => 'editpost',
					'originalaction'       => 'editpost',
					'post_author'          => 1,
					'post_type'            => 'post',
					'original_post_status' => 'draft',
					'post_ID'              => $copy,
					'post_title'           => 'Pricing cheatsheet (classic)',
					'content'              => $content,
					'excerpt'              => 'Regexes like \d+ and paths like C:\Temp explained.',
					'hidden_post_status'   => 'draft',
					'post_status'          => 'draft',
					'visibility'           => 'public',
					'publish'              => 'Republish',
				),
			)
		);
		$this->assertContains( $res['status'], array( 302, 303 ), substr( strip_tags( $res['body'] ), 0, 800 ) );
		$args = location_args( $res );
		$this->assertSame( '1', $args['dprepublished'] ?? null, 'Redirects back to the original after republishing' );
		wp_cache_flush();

		$o = raw_post( $orig );
		$this->assertSame( 'Pricing cheatsheet (classic)', $o['post_title'] );
		$this->assertSame( $content, $o['post_content'] );
		$this->assertSame( 'Regexes like \d+ and paths like C:\Temp explained.', $o['post_excerpt'] );
		$this->assertSame( raw_meta( $copy ), raw_meta( $orig ) );

		// Following the redirect cleans up the copy.
		$res = $this->http( 'GET', $res['headers']['location'], array( 'login' => $this->login ) );
		$this->assertSame( 200, $res['status'] );
		wp_cache_flush();
		$this->assertNull( raw_post( $copy ) );
		$this->assertSame( '', get_post_meta( $orig, '_dp_has_rewrite_republish_copy', true ) );
	}

	public function test_republish_plain_post(): void {
		$orig = $this->twin( 'team-offsite-notes', 'post', array( 'category', 'post_tag' ) );
		$meta = raw_meta( $orig );
		$copy = $this->rewrite( $orig );
		$this->republish_via_rest( $copy, 'posts', array( 'title' => 'Team offsite notes (final)' ) );
		$this->assertSame( 'Team offsite notes (final)', raw_post( $orig )['post_title'] );
		$this->assertSame( 'publish', raw_post( $orig )['post_status'] );
		$this->assertSame( $meta, raw_meta( $orig ) );
		$this->assertSame( array( 'offsite', 'planning' ), term_slugs( $orig, 'post_tag' ) );
	}

	public function test_rewrite_needs_a_valid_nonce(): void {
		$orig = $this->twin( 'team-offsite-notes', 'post', array( 'category' ) );
		$res  = $this->link_action( 'duplicate_post_rewrite', $orig, null, 'nope' );
		$this->assertSame( 403, $res['status'] );
		$this->assertSame( array(), copies_of( $orig ) );
	}
}
