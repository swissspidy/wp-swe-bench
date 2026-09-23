<?php
/**
 * Previews and rewrite rule regeneration (real server).
 */

use function WPSB\Docs\doc;
use function WPSB\Docs\doc_by_title;
use function WPSB\Docs\shown;
use function WPSB\Docs\term_id;

class PreviewAndFlushTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private function admin_login(): array {
		return $this->http_login( $this->create_user( 'administrator' ) );
	}

	public function test_previews_of_unpublished_docs(): void {
		$login = $this->admin_login();
		foreach ( array( doc_by_title( 'Roadmap' ) => 'Secret roadmap draft.', doc( 'acme-cloud', 'api-reference/webhooks' ) => 'Webhooks pending review.' ) as $id => $text ) {
			$link = get_preview_post_link( $id );
			$this->assertStringStartsWith( home_url( '/' ), $link );
			$res = $this->http( 'GET', $link, array( 'login' => $login ) );
			$this->assertSame( 200, $res['status'], "preview of $id: $link" );
			$this->assertSame( $id, shown( $res['body'] )['id'], "preview of $id shows the doc" );
			$this->assertStringContainsString( $text, $res['body'] );

			// Logged out, the preview link doesn't reveal the unpublished doc.
			$res = $this->http( 'GET', $link );
			$this->assertStringNotContainsString( $text, $res['body'] );
		}
	}

	public function test_preview_of_published_docs(): void {
		$login = $this->admin_login();
		foreach ( array( doc( 'acme-cli', 'getting-started/installation' ), doc( 'acme-cloud', 'getting-started/installation', 'v2' ) ) as $id ) {
			// What the editor's "Preview" button opens for a published doc (after autosaving the changes).
			$link = get_preview_post_link(
				$id,
				array(
					'preview_id'    => $id,
					'preview_nonce' => $this->nonce_for( $login['user_id'], 'post_preview_' . $id, $login['logged_in'] ),
				)
			);
			$res  = $this->http( 'GET', $link, array( 'login' => $login ) );
			$this->assertSame( 200, $res['status'], $link );
			$this->assertSame( $id, shown( $res['body'] )['id'], "preview of $id: $link" );
		}
	}

	public function test_rules_are_not_regenerated_on_every_request(): void {
		$this->http( 'GET', '/' );
		wp_cache_flush();
		$rules = get_option( 'rewrite_rules' );
		$this->assertIsArray( $rules );
		$this->assertNotEmpty( $rules );

		$sentinel = array_merge( array( '^wpsb-sentinel/?$' => 'index.php?pagename=about' ), $rules );
		update_option( 'rewrite_rules', $sentinel );
		try {
			$login = $this->admin_login();
			$this->http( 'GET', '/docs/acme-cloud/' );
			$this->http( 'GET', '/docs/acme-cli/getting-started/installation/' );
			$this->http( 'GET', '/about/' );
			$this->http( 'GET', '/wp-json/wp/v2/doc?per_page=5' );
			$this->http( 'GET', '/wp-admin/edit.php?post_type=doc', array( 'login' => $login ) );
			$this->wp_cli( 'eval "echo 1;"' );
			wp_cache_flush();
			$this->assertSame( $sentinel, get_option( 'rewrite_rules' ), 'The rewrite rules were regenerated during normal requests' );
			$res = $this->http( 'GET', '/wpsb-sentinel/' );
			$this->assertSame( 200, $res['status'] );
		} finally {
			update_option( 'rewrite_rules', $rules );
		}
	}

	public function test_new_product_works_right_away(): void {
		$term = wp_insert_term( 'Acme Mobile', 'product', array( 'slug' => 'acme-mobile' ) );
		$this->assertIsArray( $term );
		$id = wp_insert_post(
			array(
				'post_type'    => 'doc',
				'post_status'  => 'publish',
				'post_title'   => 'Introduction',
				'post_name'    => 'intro',
				'post_content' => 'Mobile intro.',
			)
		);
		wp_set_object_terms( $id, array( $term['term_id'] ), 'product' );
		clean_post_cache( $id );
		try {
			$res = $this->http( 'GET', '/docs/acme-mobile/' );
			$this->assertSame( 200, $res['status'] );
			$this->assertSame( array( 'kind' => 'product', 'id' => $term['term_id'], 'paged' => 0 ), shown( $res['body'] ) );
			$res = $this->http( 'GET', '/docs/acme-mobile/intro/' );
			$this->assertSame( 200, $res['status'] );
			$this->assertSame( $id, shown( $res['body'] )['id'] );
			$this->assertSame( home_url( '/docs/acme-mobile/intro/' ), get_permalink( $id ) );
		} finally {
			wp_delete_post( $id, true );
			wp_delete_term( $term['term_id'], 'product' );
		}
		$this->assertSame( 404, $this->http( 'GET', '/docs/acme-mobile/' )['status'] );
	}
}
