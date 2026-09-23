<?php
/**
 * URL → content matrix against the real server.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use function WPSB\Docs\doc;
use function WPSB\Docs\page;
use function WPSB\Docs\shown;
use function WPSB\Docs\term_id;

class RoutingTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public static function urls(): array {
		return array(
			// URL, expected kind, what (doc: product|version|path, page: path, product: slug).
			'docs landing page'              => array( '/docs/', 'page', 'docs' ),
			'page below docs'                => array( '/docs/contributing/', 'page', 'docs/contributing' ),
			'another page below docs'        => array( '/docs/style-guide/', 'page', 'docs/style-guide' ),
			'product'                        => array( '/docs/acme-cloud/', 'product', 'acme-cloud' ),
			'product page 2'                 => array( '/docs/acme-cloud/page/2/', 'product', 'acme-cloud', 2 ),
			'product page 3 (past the end)'  => array( '/docs/acme-cloud/page/3/', '404', '' ),
			'other product'                  => array( '/docs/acme-cli/', 'product', 'acme-cli' ),
			'doc'                            => array( '/docs/acme-cloud/getting-started/', 'doc', 'acme-cloud||getting-started' ),
			'child doc'                      => array( '/docs/acme-cloud/getting-started/installation/', 'doc', 'acme-cloud||getting-started/installation' ),
			'same path, other product'       => array( '/docs/acme-cli/getting-started/', 'doc', 'acme-cli||getting-started' ),
			'same child path, other product' => array( '/docs/acme-cli/getting-started/installation/', 'doc', 'acme-cli||getting-started/installation' ),
			'versioned doc'                  => array( '/docs/acme-cloud/v2/getting-started/', 'doc', 'acme-cloud|v2|getting-started' ),
			'versioned child doc'            => array( '/docs/acme-cloud/v2/getting-started/installation/', 'doc', 'acme-cloud|v2|getting-started/installation' ),
			'old version of other product'   => array( '/docs/acme-cli/v1/getting-started/', 'doc', 'acme-cli|v1|getting-started' ),
			'deep doc'                       => array( '/docs/acme-cli/commands/deploy/', 'doc', 'acme-cli||commands/deploy' ),
			'top-level doc'                  => array( '/docs/acme-cloud/billing/', 'doc', 'acme-cloud||billing' ),
			'doc of another product'         => array( '/docs/acme-cli/billing/', '404', '' ),
			'doc missing in version'         => array( '/docs/acme-cloud/v2/billing/', '404', '' ),
			'unknown version'                => array( '/docs/acme-cloud/v9/getting-started/', '404', '' ),
			'unknown doc'                    => array( '/docs/acme-cloud/getting-started/nope/', '404', '' ),
			'unknown product'                => array( '/docs/unknown-product/', '404', '' ),
			'unknown product with path'      => array( '/docs/unknown-product/getting-started/', '404', '' ),
			'pending doc'                    => array( '/docs/acme-cloud/api-reference/webhooks/', '404', '' ),
			'regular page'                   => array( '/about/', 'page', 'about' ),
		);
	}

	#[DataProvider( 'urls' )]
	public function test_url( string $url, string $kind, string $what, int $paged = 0 ): void {
		$res = $this->http( 'GET', $url );
		$this->assertArrayNotHasKey( 'location', $res['headers'], "$url must not redirect (to " . ( $res['headers']['location'] ?? '' ) . ')' );
		$this->assertSame( '404' === $kind ? 404 : 200, $res['status'], "$url status" );

		$shown = shown( $res['body'] );
		switch ( $kind ) {
			case 'page':
				$expected = array( 'kind' => 'page', 'id' => page( $what ), 'paged' => 0 );
				break;
			case 'product':
				$expected = array( 'kind' => 'product', 'id' => term_id( 'product', $what ), 'paged' => $paged );
				break;
			case 'doc':
				list( $product, $version, $path ) = explode( '|', $what );
				$expected = array( 'kind' => 'doc', 'id' => doc( $product, $path, $version ), 'paged' => 0 );
				break;
			default:
				$expected = array( 'kind' => '404', 'id' => 0, 'paged' => $shown['paged'] );
		}
		$this->assertSame( $expected, $shown, "$url shows the wrong thing" );
	}

	public function test_doc_content_and_breadcrumbs(): void {
		$res = $this->http( 'GET', '/docs/acme-cli/getting-started/installation/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'npm i -g acme', $res['body'] );
		$this->assertStringContainsString( 'href="' . home_url( '/docs/acme-cli/getting-started/' ) . '"', $res['body'], 'Breadcrumb to the CLI parent' );
		$this->assertStringContainsString( 'href="' . home_url( '/docs/acme-cli/' ) . '"', $res['body'], 'Breadcrumb to the product' );
		$this->assertStringContainsString( '<a class="acme-doc-link" href="' . home_url( '/docs/acme-cloud/getting-started/installation/' ) . '">the Cloud agent</a>', $res['body'] );

		$res = $this->http( 'GET', '/docs/acme-cloud/v2/getting-started/installation/' );
		$this->assertStringContainsString( 'Install the Acme Cloud v2 agent.', $res['body'] );

		$res = $this->http( 'GET', '/docs/acme-cloud/getting-started/' );
		$this->assertStringContainsString( 'href="' . home_url( '/docs/acme-cloud/getting-started/installation/' ) . '"', $res['body'], 'Child docs navigation' );
		$this->assertStringNotContainsString( '/docs/acme-cli/', $res['body'] );
	}

	public function test_multi_page_doc(): void {
		$res = $this->http( 'GET', '/docs/acme-cloud/api-reference/authentication/2/' );
		$this->assertSame( 200, $res['status'] );
		$this->assertSame( doc( 'acme-cloud', 'api-reference/authentication' ), shown( $res['body'] )['id'] );
		$this->assertStringContainsString( 'OAuth flows: part two.', $res['body'] );
		$this->assertStringNotContainsString( 'Tokens: part one.', $res['body'] );
	}

	public function test_docs_archive_without_landing_page(): void {
		$landing = page( 'docs' );
		wp_update_post( array( 'ID' => $landing, 'post_status' => 'draft' ) );
		try {
			$res = $this->http( 'GET', '/docs/' );
			$this->assertSame( 200, $res['status'] );
			$this->assertSame( 'archive', shown( $res['body'] )['kind'], 'Without a docs page, /docs/ lists the docs' );
		} finally {
			wp_update_post( array( 'ID' => $landing, 'post_status' => 'publish' ) );
		}
		$res = $this->http( 'GET', '/docs/' );
		$this->assertSame( array( 'kind' => 'page', 'id' => $landing, 'paged' => 0 ), shown( $res['body'] ) );
	}
}
