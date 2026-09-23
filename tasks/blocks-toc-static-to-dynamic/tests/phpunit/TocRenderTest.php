<?php
/**
 * Front-end rendering of the TOC block from the current headings (in-process).
 */

use function WPSB\Toc\fragment;
use function WPSB\Toc\headings;
use function WPSB\Toc\ids;
use function WPSB\Toc\post_by_slug;
use function WPSB\Toc\render_post;
use function WPSB\Toc\render_slug;
use function WPSB\Toc\tocs;

class TocRenderTest extends WPSB\TestCase {

	protected function tearDown(): void {
		remove_all_filters( 'acme_toc_excluded_blocks' );
		remove_filter( 'gettext', 'WPSB\Toc\translate_default_title', 10 );
		remove_filter( 'gettext_with_context', 'WPSB\Toc\translate_default_title', 10 );
		parent::tearDown();
	}

	private function the_toc( string $html ): array {
		$all = tocs( $html );
		$this->assertCount( 1, $all, "Expected exactly one TOC in:\n$html" );
		$toc = $all[0];
		$this->assertSame( 'nav', $toc['tag'], 'The TOC must be a <nav> element' );
		$this->assertSame( 'ol', $toc['list_tag'], 'The list must be an <ol class="acme-toc__list">' );
		foreach ( $toc['items'] as $item ) {
			$this->assertContains( 'acme-toc__item', $item['classes'] );
			$this->assertNotNull( $item['level'], 'Each item needs an acme-toc__item--hN class' );
		}
		return $toc;
	}

	/** [ [level, text, fragment], ... ] */
	private function summary( array $toc ): array {
		return array_map( fn( $i ) => array( $i['level'], $this->plain( $i['text'] ), fragment( $i['href'] ) ), $toc['items'] );
	}

	/** Ignore typographic quote/dash conversion (wptexturize) when comparing texts. */
	private function plain( string $s ): string {
		return str_replace( array( '’', '‘', '“', '”', '–', '—' ), array( "'", "'", '"', '"', '-', '-' ), $s );
	}

	/** Every TOC link resolves to exactly one element with that id in the page, and that element is the heading with the link text. */
	private function assertLinksResolve( array $toc, string $html ): void {
		$ids      = ids( $html );
		$headings = headings( $html );
		foreach ( $toc['items'] as $item ) {
			$frag = fragment( $item['href'] );
			$this->assertNotNull( $frag, 'Link without fragment: ' . $item['href'] );
			$this->assertSame( 1, count( array_keys( $ids, $frag, true ) ), "id \"$frag\" must exist exactly once in the page" );
			$match = array_values( array_filter( $headings, static fn( $h ) => $h['id'] === $frag ) );
			$this->assertCount( 1, $match, "No heading with id \"$frag\"" );
			$this->assertSame( $this->plain( $item['text'] ), $this->plain( $match[0]['text'] ), "Link text and heading text differ for #$frag" );
			$this->assertSame( 'h' . $item['level'], $match[0]['tag'] );
		}
	}

	public function test_stale_toc_from_1_3_lists_the_current_headings(): void {
		$html = render_slug( 'stale-toc' );
		$toc  = $this->the_toc( $html );
		$this->assertSame(
			array(
				array( 2, 'Introduction', 'introduction' ),
				array( 2, 'Installing the plugin', 'installation' ),
				array( 2, 'Configuration', 'configuration' ),
				array( 3, 'Advanced options', 'advanced-options' ),
				array( 2, 'Troubleshooting', 'troubleshooting' ),
			),
			$this->summary( $toc )
		);
		$this->assertSame( 'Table of contents', $toc['title'] );
		$this->assertSame( 'p', $toc['title_tag'] );
		$this->assertSame( 'Table of contents', $toc['label'] );
		foreach ( $toc['items'] as $item ) {
			$this->assertStringStartsWith( '#', $item['href'], 'Links to headings on the same page must be fragment-only' );
		}
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_headings_without_anchor_get_the_generated_id(): void {
		$html = render_slug( 'stale-toc' );
		$ids  = array_column( headings( $html ), 'id', 'text' );
		$this->assertSame( 'introduction', $ids['Introduction'] ?? null, 'Existing anchors must not change' );
		$this->assertSame( 'installation', $ids['Installing the plugin'] ?? null, 'Existing anchors must not change' );
		$this->assertSame( 'advanced-options', $ids['Advanced options'] ?? null );
		$this->assertSame( 'troubleshooting', $ids['Troubleshooting'] ?? null );
	}

	public function test_toc_from_1_0_renders_the_current_markup(): void {
		$html = render_slug( 'legacy-contents', 'page' );
		$toc  = $this->the_toc( $html );
		$this->assertSame(
			array(
				array( 2, 'Overview', 'overview' ),
				array( 2, 'Grouped heading', 'grouped-heading' ),
				array( 3, 'Left column', 'left-column' ),
				array( 3, 'Right column', 'right-col' ),
				array( 2, 'Summary', 'summary' ),
			),
			$this->summary( $toc ),
			'Headings in groups/columns are listed, headings inside Details blocks are not'
		);
		$this->assertSame( 'Table of contents', $toc['title'], 'The untouched 1.0 default title ("Contents") becomes the current default' );
		$this->assertStringNotContainsString( 'Old section', $html, 'The stale saved list must not be output' );
		$this->assertDoesNotMatchRegularExpression( '/<ul class="acme-toc__list"/', $html );
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_duplicate_headings_and_existing_anchors(): void {
		$html = render_slug( 'duplicate-headings' );
		$toc  = $this->the_toc( $html );
		$this->assertSame(
			array(
				array( 2, 'Setup', 'setup' ),
				array( 2, 'Setup', 'setup-3' ),
				array( 2, 'FAQ', 'faq-2' ),
				array( 2, 'FAQ', 'faq' ),
				array( 2, 'Notes', 'setup-2' ),
			),
			$this->summary( $toc )
		);
		$this->assertSame( array( 'setup', 'setup-3', 'faq-2', 'faq', 'setup-2' ), array_column( headings( $html ), 'id' ) );
		$this->assertSame( 'In this guide', $toc['title'] );
		$this->assertSame( 'In this guide', $toc['label'] );
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_levels_exclusions_and_deleted_title_from_saved_block(): void {
		$html = render_slug( 'no-title-toc' );
		$toc  = $this->the_toc( $html );
		$this->assertNull( $toc['title'], 'A TOC whose title was removed must not print a title' );
		$this->assertSame( 'Table of contents', $toc['label'] );
		$this->assertSame(
			array(
				array( 2, 'Alpha', 'alpha' ),
				array( 3, 'Beta', 'beta' ),
				array( 4, 'Gamma', 'gamma' ),
			),
			$this->summary( $toc )
		);
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_min_and_max_level_attributes(): void {
		$post = get_post(
			$this->create_post(
				array(
					'post_content' => '<!-- wp:acme/toc {"minLevel":3,"maxLevel":4} /-->' . "\n\n"
						. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Two</h2>\n<!-- /wp:heading -->\n\n"
						. "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Three</h3>\n<!-- /wp:heading -->\n\n"
						. "<!-- wp:heading {\"level\":4} -->\n<h4 class=\"wp-block-heading\">Four</h4>\n<!-- /wp:heading -->\n\n"
						. "<!-- wp:heading {\"level\":5} -->\n<h5 class=\"wp-block-heading\">Five</h5>\n<!-- /wp:heading -->",
				)
			)
		);
		$html = render_post( $post );
		$toc  = $this->the_toc( $html );
		$this->assertSame( array( array( 3, 'Three', 'three' ), array( 4, 'Four', 'four' ) ), $this->summary( $toc ) );
		$this->assertSame( 'Table of contents', $toc['title'], 'A new TOC shows the default title' );
		$this->assertLinksResolve( $toc, $html );

		// Defaults: H2 and H3.
		$post->post_content = str_replace( '{"minLevel":3,"maxLevel":4} ', '', $post->post_content );
		wp_update_post( $post );
		$toc = $this->the_toc( render_post( get_post( $post->ID ) ) );
		$this->assertSame( array( array( 2, 'Two', 'two' ), array( 3, 'Three', 'three' ) ), $this->summary( $toc ) );
	}

	public function test_excluded_blocks_filter_and_attribute(): void {
		$content = '<!-- wp:acme/toc {"excludedBlocks":["core/quote"]} /-->' . "\n\n"
			. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Top</h2>\n<!-- /wp:heading -->\n\n"
			. "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:heading -->\n<h2 class=\"wp-block-heading\">In group</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:group -->\n\n"
			. "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:heading -->\n<h2 class=\"wp-block-heading\">In quote</h2>\n<!-- /wp:heading --></blockquote>\n<!-- /wp:quote -->\n\n"
			. "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>More</summary><!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Deep in details</h2>\n<!-- /wp:heading --></div>\n<!-- /wp:group --></details>\n<!-- /wp:details -->";
		$post = get_post( $this->create_post( array( 'post_content' => $content ) ) );

		$toc = $this->the_toc( render_post( $post ) );
		$this->assertSame( array( 'Top', 'In group' ), array_column( $toc['items'], 'text' ), 'Block attribute + default site-wide exclusions (Details, at any depth)' );

		add_filter(
			'acme_toc_excluded_blocks',
			static function ( $blocks ) {
				$blocks   = array_diff( $blocks, array( 'core/details' ) );
				$blocks[] = 'core/group';
				return $blocks;
			}
		);
		$toc = $this->the_toc( render_post( $post ) );
		$this->assertSame( array( 'Top' ), array_column( $toc['items'], 'text' ), 'acme_toc_excluded_blocks must be honoured on the front end' );
	}

	public function test_special_characters_slugs_and_escaping(): void {
		$html = render_slug( 'special-chars' );
		$toc  = $this->the_toc( $html );
		$this->assertSame(
			array(
				array( 2, 'Café & Crème', 'cafe-creme' ),
				array( 2, "What's new?", 'what-s-new' ),
				array( 2, 'WP_Query tips', 'wp-query-tips' ),
				array( 2, '???', 'heading' ),
				array( 2, '日本語', 'heading-2' ),
				array( 2, 'Say <script>alert(1)</script>', 'say-script-alert-1-script' ),
			),
			$this->summary( $toc )
		);
		$this->assertStringNotContainsString( '<script>alert(1)', $toc['html'] );
		$this->assertStringNotContainsString( '<code>', $toc['html'], 'Link texts are plain text' );
		$this->assertStringNotContainsString( '<em>', $toc['html'], 'Link texts are plain text' );
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_toc_without_headings_renders_nothing(): void {
		$html = render_slug( 'no-headings' );
		$this->assertCount( 0, tocs( $html ), 'A TOC without headings must not be output' );
		$this->assertStringNotContainsString( 'Old heading', $html );
		$this->assertStringContainsString( 'No headings here any more.', $html );
	}

	public function test_headings_changed_after_saving_are_reflected(): void {
		$post = post_by_slug( 'stale-toc' );
		$post->post_content = str_replace( '>Configuration</h2>', '>Settings screen</h2>', $post->post_content )
			. "\n\n<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">Getting help</h3>\n<!-- /wp:heading -->";
		wp_update_post( $post );
		$html = render_post( get_post( $post->ID ) );
		$toc  = $this->the_toc( $html );
		$texts = array_column( $toc['items'], 'text' );
		$this->assertContains( 'Settings screen', $texts );
		$this->assertNotContains( 'Configuration', $texts );
		$this->assertSame( 'Getting help', end( $texts ) );
		$this->assertSame( 'configuration', fragment( $toc['items'][2]['href'] ), 'A renamed heading keeps its existing anchor' );
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_new_format_anchor_attribute_and_empty_headings(): void {
		$post = get_post(
			$this->create_post(
				array(
					'post_content' => '<!-- wp:acme/toc {"title":"Guide overview"} /-->' . "\n\n"
						. "<!-- wp:heading {\"anchor\":\"custom-start\"} -->\n<h2 id=\"custom-start\" class=\"wp-block-heading\">Start here</h2>\n<!-- /wp:heading -->\n\n"
						. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\"></h2>\n<!-- /wp:heading -->\n\n"
						. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Custom start</h2>\n<!-- /wp:heading -->",
				)
			)
		);
		$html = render_post( $post );
		$toc  = $this->the_toc( $html );
		$this->assertSame( 'Guide overview', $toc['title'] );
		$this->assertSame( 'Guide overview', $toc['label'] );
		$this->assertSame(
			array( array( 2, 'Start here', 'custom-start' ), array( 2, 'Custom start', 'custom-start-2' ) ),
			$this->summary( $toc ),
			'Empty headings are skipped; generated anchors never reuse an existing one'
		);
		$this->assertLinksResolve( $toc, $html );
	}

	public function test_title_attribute_is_escaped_and_can_be_empty(): void {
		$this->login_as( 'administrator' ); // Content from a user with unfiltered_html (or an importer).
		$post = get_post(
			$this->create_post(
				array(
					'post_content' => wp_slash(
						'<!-- wp:acme/toc ' . wp_json_encode( array( 'title' => '<img src=x onerror=alert(1)>Guide "quoted"' ), JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP ) . ' /-->' . "\n\n"
						. "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Only</h2>\n<!-- /wp:heading -->"
					),
				)
			)
		);
		$html = render_post( $post );
		$toc  = $this->the_toc( $html );
		$this->assertStringNotContainsString( 'onerror', $toc['html'] );
		$this->assertStringNotContainsString( '<img', $toc['html'] );
		$this->assertStringContainsString( 'Guide', (string) $toc['title'] );

		$post->post_content = '<!-- wp:acme/toc {"title":""} /-->' . "\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Only</h2>\n<!-- /wp:heading -->";
		wp_update_post( $post );
		$toc = $this->the_toc( render_post( get_post( $post->ID ) ) );
		$this->assertNull( $toc['title'], 'An empty title attribute means "no title"' );
		$this->assertSame( 'Table of contents', $toc['label'] );
	}

	public function test_default_title_is_translatable(): void {
		add_filter( 'gettext', 'WPSB\Toc\translate_default_title', 10, 3 );
		add_filter( 'gettext_with_context', 'WPSB\Toc\translate_default_title', 10, 4 );

		$post = get_post(
			$this->create_post(
				array( 'post_content' => "<!-- wp:acme/toc /-->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Only</h2>\n<!-- /wp:heading -->" )
			)
		);
		$toc = $this->the_toc( render_post( $post ) );
		$this->assertSame( 'Inhaltsverzeichnis', $toc['title'], 'New TOC: default title must be translated' );
		$this->assertSame( 'Inhaltsverzeichnis', $toc['label'] );

		$toc = $this->the_toc( render_slug( 'stale-toc' ) );
		$this->assertSame( 'Inhaltsverzeichnis', $toc['title'], '1.3 TOC with the untouched default title' );

		$toc = $this->the_toc( render_slug( 'legacy-contents', 'page' ) );
		$this->assertSame( 'Inhaltsverzeichnis', $toc['title'], '1.0 TOC with the untouched default title ("Contents")' );

		$toc = $this->the_toc( render_slug( 'no-title-toc' ) );
		$this->assertNull( $toc['title'] );
		$this->assertSame( 'Inhaltsverzeichnis', $toc['label'] );

		$toc = $this->the_toc( render_slug( 'duplicate-headings' ) );
		$this->assertSame( 'In this guide', $toc['title'], 'Custom titles are not translated' );
	}

	public function test_first_page_of_paginated_post_links_to_other_pages(): void {
		$post = post_by_slug( 'paged-guide' );
		$html = render_post( $post );
		$toc  = $this->the_toc( $html );
		$this->assertSame(
			array( 'Introduction', 'Requirements', 'Installation', 'Introduction', 'Verifying', 'Wrap-up' ),
			array_column( $toc['items'], 'text' ),
			'Headings of all pages are listed'
		);
		$this->assertSame( '#introduction', $toc['items'][0]['href'] );
		$this->assertSame( '#requirements', $toc['items'][1]['href'] );
		$this->assertSame( array( 'introduction-2' ), array( fragment( $toc['items'][3]['href'] ) ), 'Anchors are unique across all pages' );
		$this->assertStringNotContainsString( 'Wrap-up</h2>', $html, 'Only the first page is rendered' );
	}
}
