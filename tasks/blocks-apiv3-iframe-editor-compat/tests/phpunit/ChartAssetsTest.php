<?php
/**
 * Front-end asset loading and markup of Acme Charts (through the Playground HTTP server).
 */

class ChartAssetsTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** @var int[] */
	private array $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $id ) {
			wp_delete_post( $id, true );
		}
		$this->created = array();
		parent::tearDown();
	}

	private function page( string $path ): string {
		$res = $this->http( 'GET', $path );
		$this->assertSame( 200, $res['status'], "GET $path" );
		return $res['body'];
	}

	/** Script and stylesheet URLs served from the plugin directory. */
	private function plugin_scripts( string $html ): array {
		preg_match_all( '#<script\b[^>]*\bsrc=["\']([^"\']*/wp-content/plugins/acme-charts/[^"\']*)#i', $html, $m );
		return $m[1];
	}

	private function slug_url( string $slug, string $type = 'post' ): string {
		$post = get_page_by_path( $slug, OBJECT, $type );
		$this->assertNotNull( $post, "seeded $type $slug" );
		return wp_make_link_relative( get_permalink( $post ) );
	}

	private function publish( string $content ): int {
		$id              = $this->create_post( array( 'post_content' => $content ) );
		$this->created[] = $id;
		return $id;
	}

	public function test_pages_without_charts_load_no_chart_scripts(): void {
		foreach ( array( $this->slug_url( 'no-charts' ), $this->slug_url( 'sample-page', 'page' ), $this->slug_url( 'hello-world' ) ) as $path ) {
			$html = $this->page( $path );
			$this->assertSame( array(), $this->plugin_scripts( $html ), "No Acme Charts script expected on $path" );
			$this->assertStringNotContainsString( 'acmeChartsSettings', $html, "Chart settings printed on $path" );
		}
	}

	public function test_new_post_without_chart_loads_no_chart_scripts(): void {
		$id   = $this->publish( "<!-- wp:paragraph -->\n<p>Just text, and the words acme-chart and wp:acme/chart in it.</p>\n<!-- /wp:paragraph -->" );
		$html = $this->page( wp_make_link_relative( get_permalink( $id ) ) );
		$this->assertSame( array(), $this->plugin_scripts( $html ) );
	}

	public function test_chart_pages_load_the_chart_scripts(): void {
		foreach (
			array(
				$this->slug_url( 'quarterly-visitors' ),
				$this->slug_url( 'legacy-chart' ),
				$this->slug_url( 'wide-chart' ),
				$this->slug_url( 'budget-overview', 'page' ),
			) as $path
		) {
			$html = $this->page( $path );
			$this->assertNotEmpty( $this->plugin_scripts( $html ), "Acme Charts scripts missing on $path" );
		}
	}

	public function test_chart_in_synced_pattern_loads_the_chart_scripts(): void {
		$html = $this->page( $this->slug_url( 'uses-synced-chart' ) );
		$this->assertStringContainsString( 'id="revenue"', $html, 'Synced pattern chart not rendered' );
		$this->assertNotEmpty( $this->plugin_scripts( $html ), 'Acme Charts scripts missing for a chart inside a synced pattern' );
	}

	public function test_chart_nested_in_group_of_a_new_post_loads_scripts(): void {
		$chart = '<!-- wp:acme/chart {"series":[{"label":"A","value":1}],"anchor":"nested-x"} -->' . "\n"
			. '<figure class="wp-block-acme-chart acme-chart" data-chart="{&quot;series&quot;:[{&quot;label&quot;:&quot;A&quot;,&quot;value&quot;:1}],&quot;height&quot;:240,&quot;showValues&quot;:false}" id="nested-x"><div class="acme-chart__canvas" style="height:240px" aria-hidden="true"></div><table class="acme-chart__table"><tbody><tr><th scope="row">A</th><td>1</td></tr></tbody></table></figure>' . "\n"
			. '<!-- /wp:acme/chart -->';
		$content = "<!-- wp:group -->\n<div class=\"wp-block-group\">" . $chart . "</div>\n<!-- /wp:group -->";
		$id      = $this->publish( $content );
		$html    = $this->page( wp_make_link_relative( get_permalink( $id ) ) );
		$this->assertStringContainsString( 'id="nested-x"', $html );
		$this->assertNotEmpty( $this->plugin_scripts( $html ) );
	}

	public function test_saved_markup_is_output_unchanged(): void {
		foreach ( array( 'quarterly-visitors', 'legacy-chart', 'wide-chart' ) as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'post' );
			foreach ( parse_blocks( $post->post_content ) as $block ) {
				if ( 0 !== strpos( (string) $block['blockName'], 'acme/' ) ) {
					continue;
				}
				$rendered = render_block( $block );
				$this->assertSame( trim( $block['innerHTML'] ), trim( $rendered ), "Front-end markup of {$block['blockName']} in $slug changed" );
			}
		}
	}

	public function test_palette_filter_and_settings_still_apply(): void {
		// The palette is part of the public contract: Settings → Charts + the acme_charts_palette filter.
		$this->assertSame( array( '#0b3d91', '#fc3d21', '#3858e9', '#e26f56', '#1a8f5c', '#dba617' ), acme_charts_get_palette() );
		$html = $this->page( $this->slug_url( 'quarterly-visitors' ) );
		$this->assertStringContainsString( '#0b3d91', str_replace( '\\/', '/', $html ), 'The filtered palette must reach the front end' );
	}
}
