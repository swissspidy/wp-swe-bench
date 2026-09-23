<?php
/**
 * Generated URLs (in-process).
 */

use function WPSB\Docs\doc;
use function WPSB\Docs\doc_by_title;
use function WPSB\Docs\term_id;

class PermalinkTest extends WPSB\TestCase {

	public function test_doc_permalinks(): void {
		$expected = array(
			'/docs/acme-cloud/getting-started/'                 => doc( 'acme-cloud', 'getting-started' ),
			'/docs/acme-cloud/getting-started/installation/'    => doc( 'acme-cloud', 'getting-started/installation' ),
			'/docs/acme-cloud/api-reference/authentication/'    => doc( 'acme-cloud', 'api-reference/authentication' ),
			'/docs/acme-cli/getting-started/'                   => doc( 'acme-cli', 'getting-started' ),
			'/docs/acme-cli/getting-started/installation/'      => doc( 'acme-cli', 'getting-started/installation' ),
			'/docs/acme-cloud/v2/getting-started/installation/' => doc( 'acme-cloud', 'getting-started/installation', 'v2' ),
			'/docs/acme-cli/v1/getting-started/'                => doc( 'acme-cli', 'getting-started', 'v1' ),
			'/docs/acme-cli/commands/deploy/'                   => doc( 'acme-cli', 'commands/deploy' ),
		);
		foreach ( $expected as $path => $id ) {
			$this->assertSame( home_url( $path ), get_permalink( $id ), "permalink of doc $id" );
		}
	}

	public function test_product_and_archive_links(): void {
		$this->assertSame( home_url( '/docs/acme-cloud/' ), get_term_link( term_id( 'product', 'acme-cloud' ), 'product' ) );
		$this->assertSame( home_url( '/docs/acme-cli/' ), acme_docs_product_url( 'acme-cli' ) );
	}

	public function test_doc_lookup_and_cross_links(): void {
		$this->assertSame( doc( 'acme-cli', 'getting-started/installation' ), acme_docs_get_doc_by_path( 'acme-cli', 'getting-started/installation' )->ID ?? null );
		$this->assertSame( doc( 'acme-cloud', 'getting-started/installation' ), acme_docs_get_doc_by_path( 'acme-cloud', 'getting-started/installation' )->ID ?? null );
		$this->assertSame( doc( 'acme-cloud', 'getting-started/installation', 'v2' ), acme_docs_get_doc_by_path( 'acme-cloud', 'getting-started/installation', 'v2' )->ID ?? null );
		$this->assertNull( acme_docs_get_doc_by_path( 'acme-cli', 'billing' ) );
		$this->assertNull( acme_docs_get_doc_by_path( 'acme-cloud', 'installation' ) );
		$this->assertNull( acme_docs_get_doc_by_path( 'acme-cloud', 'api-reference/webhooks' ), 'Pending docs are not linked' );

		$html = do_shortcode( '[acme_doc_link product="acme-cli" path="getting-started/installation"]CLI install[/acme_doc_link]' );
		$this->assertSame( '<a class="acme-doc-link" href="' . home_url( '/docs/acme-cli/getting-started/installation/' ) . '">CLI install</a>', $html );
		$html = do_shortcode( '[acme_doc_link product="acme-cloud" path="getting-started/installation" version="v2"]' );
		$this->assertSame( '<a class="acme-doc-link" href="' . home_url( '/docs/acme-cloud/v2/getting-started/installation/' ) . '">Installation</a>', $html );
		$html = do_shortcode( '[acme_doc_link product="acme-cli" path="nope"]Gone[/acme_doc_link]' );
		$this->assertStringContainsString( 'acme-doc-link--missing', $html );
	}

	public function test_rest_api_links(): void {
		$res = $this->rest( 'GET', '/wp/v2/doc/' . doc( 'acme-cli', 'getting-started/installation' ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( home_url( '/docs/acme-cli/getting-started/installation/' ), $res->get_data()['link'] );
	}

	public function test_new_docs_get_pretty_links_once_published(): void {
		$parent = doc( 'acme-cli', 'commands' );
		$id     = wp_insert_post(
			array(
				'post_type'   => 'doc',
				'post_status' => 'publish',
				'post_title'  => 'Rollback',
				'post_name'   => 'rollback',
				'post_parent' => $parent,
			)
		);
		wp_set_object_terms( $id, array( 'acme-cli' ), 'product' );
		clean_post_cache( $id );
		$this->assertSame( home_url( '/docs/acme-cli/commands/rollback/' ), get_permalink( $id ) );

		// Same slug in another product is allowed and keeps its own URL.
		$other = wp_insert_post(
			array(
				'post_type'   => 'doc',
				'post_status' => 'publish',
				'post_title'  => 'Billing',
				'post_parent' => 0,
			)
		);
		wp_set_object_terms( $other, array( 'acme-cli' ), 'product' );
		wp_update_post( array( 'ID' => $other, 'post_name' => 'billing' ) );
		clean_post_cache( $other );
		$this->assertSame( home_url( '/docs/acme-cli/billing/' ), get_permalink( $other ) );
		$this->assertSame( home_url( '/docs/acme-cloud/billing/' ), get_permalink( doc( 'acme-cloud', 'billing' ) ) );
	}

	public function test_draft_links_are_not_pretty_urls_without_a_slug(): void {
		$draft = doc_by_title( 'Roadmap' );
		$this->assertGreaterThan( 0, $draft );
		$link = get_preview_post_link( $draft );
		$this->assertStringNotContainsString( '/docs/acme-cloud/?', $link, 'A draft preview must not point to the product page' );
	}

	public function test_sample_permalinks_in_the_editor(): void {
		$pending = doc( 'acme-cloud', 'api-reference/webhooks' );
		$this->assertSame( array( home_url( '/docs/acme-cloud/api-reference/%pagename%/' ), 'webhooks' ), get_sample_permalink( $pending ) );
		$draft = doc_by_title( 'Roadmap' );
		$this->assertSame( array( home_url( '/docs/acme-cloud/%pagename%/' ), 'roadmap' ), get_sample_permalink( $draft ) );
		$v2 = doc( 'acme-cloud', 'getting-started/installation', 'v2' );
		$this->assertSame( array( home_url( '/docs/acme-cloud/v2/getting-started/%pagename%/' ), 'installation' ), get_sample_permalink( $v2 ) );
	}
}
