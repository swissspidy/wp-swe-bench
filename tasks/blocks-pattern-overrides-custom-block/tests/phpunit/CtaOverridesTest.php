<?php
/**
 * Front-end rendering of CTAs in synced patterns with per-instance overrides.
 */

use function WPSB\CTA\ctas;
use function WPSB\CTA\instance;
use function WPSB\CTA\pattern_id;
use function WPSB\CTA\render_post;
use function WPSB\CTA\render_slug;
use function WPSB\CTA\split_url;

class CtaOverridesTest extends WPSB\TestCase {

	private function assertTracked( array $cta, string $base, string $campaign ): void {
		$this->assertNotNull( $cta['link'], 'CTA button link missing' );
		$href = $cta['link']['href'] ?? '';
		[ $url, $args ] = split_url( $href );
		$this->assertSame( $base, $url, "Unexpected link target in $href" );
		$this->assertSame( 'acme-blog', $args['utm_source'] ?? null, "utm_source missing in $href" );
		$this->assertSame( 'cta', $args['utm_medium'] ?? null, "utm_medium missing in $href" );
		$this->assertSame( $campaign, $args['utm_campaign'] ?? null, "utm_campaign wrong in $href" );
		$this->assertSame( $campaign, $cta['link']['data-acme-cta'] ?? null, 'data-acme-cta must be the campaign' );
	}

	private function render_content( string $content ): string {
		$id = $this->create_post( array( 'post_content' => wp_slash( $content ) ) );
		return render_post( get_post( $id ) );
	}

	public function test_each_instance_renders_its_own_overrides(): void {
		$html = render_slug( 'pricing', 'page' );
		$all  = ctas( $html );
		$this->assertCount( 4, $all, $html );

		// Instance 1: heading, button text and link overridden.
		$this->assertSame( 'Get the pricing digest', $all[0]['heading'] );
		$this->assertStringContainsString( '<em>pricing</em>', $all[0]['heading_html'], 'Bold/italic formatting in overrides must be kept' );
		$this->assertSame( 'h2', $all[0]['heading_tag'] );
		$this->assertSame( 'Send me prices', $all[0]['button'] );
		$this->assertContains( 'is-variant-primary', $all[0]['classes'] );
		$this->assertTracked( $all[0], 'https://news.example.com/pricing', 'newsletter' );

		// Instance 2: only the heading overridden, the rest falls back to the pattern.
		$this->assertSame( 'Only the heading changed', $all[1]['heading'] );
		$this->assertSame( 'Subscribe', $all[1]['button'] );
		$this->assertTracked( $all[1], 'https://news.example.com/subscribe', 'newsletter' );

		// Instance 3: no overrides at all.
		$this->assertSame( 'Join our newsletter', $all[2]['heading'] );
		$this->assertStringContainsString( '<strong>newsletter</strong>', $all[2]['heading_html'] );
		$this->assertSame( 'Subscribe', $all[2]['button'] );
		$this->assertTracked( $all[2], 'https://news.example.com/subscribe', 'newsletter' );

		// A pattern without overrides: internal link, untracked.
		$this->assertSame( 'Talk to sales', $all[3]['heading'] );
		$this->assertContains( 'is-variant-secondary', $all[3]['classes'] );
		$this->assertSame( '/contact/', $all[3]['link']['href'] ?? null );
	}

	public function test_core_block_overrides_in_the_same_pattern_keep_working(): void {
		$html = render_slug( 'pricing', 'page' );
		$this->assertSame( 1, substr_count( $html, 'Prices change every quarter.' ), 'Paragraph override of the first instance missing' );
		$this->assertSame( 2, substr_count( $html, 'Every week, straight to your inbox.' ), 'Instances without a paragraph override must show the pattern text' );
	}

	public function test_heading_level_new_tab_and_variant_come_from_the_pattern(): void {
		$html = render_slug( 'webinars', 'page' );
		$all  = ctas( $html );
		$this->assertCount( 2, $all, $html );

		$this->assertSame( 'h3', $all[0]['heading_tag'] );
		$this->assertSame( 'Webinar replay: patterns', $all[0]['heading'] );
		$this->assertStringContainsString( '<strong>patterns</strong>', $all[0]['heading_html'] );
		$this->assertSame( 'Watch the replay', $all[0]['button'] );
		$this->assertContains( 'is-variant-dark', $all[0]['classes'] );
		$this->assertSame( '_blank', $all[0]['link']['target'] ?? null );
		$this->assertStringContainsString( 'noopener', $all[0]['link']['rel'] ?? '' );
		$this->assertTracked( $all[0], 'https://events.example.com/replay', 'webinar' );

		$this->assertSame( 'Live webinar: blocks in depth', $all[1]['heading'] );
		$this->assertSame( 'h3', $all[1]['heading_tag'] );
		$this->assertTracked( $all[1], 'https://events.example.com/webinar', 'webinar' );
	}

	public function test_values_for_non_overridable_attributes_are_ignored(): void {
		$html = render_slug( 'cta-override-probe' );
		$all  = ctas( $html );
		$this->assertCount( 2, $all, $html );
		foreach ( $all as $i => $cta ) {
			$this->assertContains( 'is-variant-primary', $cta['classes'], "CTA $i: the variant is controlled by the pattern" );
			$this->assertNotContains( 'is-variant-dark', $cta['classes'] );
			$this->assertSame( 'h2', $cta['heading_tag'], "CTA $i: the heading level is controlled by the pattern" );
			$this->assertArrayNotHasKey( 'target', (array) $cta['link'], "CTA $i: 'open in new tab' is controlled by the pattern" );
			$this->assertArrayNotHasKey( 'onclick', $cta['attrs'] );
		}
		$this->assertStringNotContainsString( 'hijacked', $html, 'The campaign is controlled by the pattern' );
		$this->assertSame( 'newsletter', $all[1]['link']['data-acme-cta'] ?? null );
	}

	public function test_untrusted_override_values_are_neutralised(): void {
		$html = render_slug( 'cta-override-probe' );
		$all  = ctas( $html );
		$this->assertCount( 2, $all, $html );

		$this->assertStringNotContainsStringIgnoringCase( '<script', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'onerror', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'onclick', $html );
		$this->assertStringNotContainsStringIgnoringCase( 'javascript:', $html );
		$this->assertStringNotContainsStringIgnoringCase( '<img', $all[0]['heading_html'] );

		$this->assertStringContainsString( 'Hello', $all[0]['heading'] );
		$this->assertStringContainsString( 'world', $all[0]['heading'] );
		$this->assertStringNotContainsString( 'alert', $all[0]['heading'], 'Script contents must not end up as visible text' );
		$this->assertStringContainsString( 'Click', $all[0]['button'] );
		$href = $all[0]['link']['href'] ?? '';
		$this->assertDoesNotMatchRegularExpression( '/^\s*javascript/i', $href );
		$this->assertStringNotContainsString( 'cookie', $href );

		// The second instance overrides the link with a URL that tries to break out of the attribute.
		$href = $all[1]['link']['href'] ?? '';
		$this->assertStringStartsWith( 'https://news.example.com/', $href );
		$this->assertStringNotContainsString( '"', $href );
		$this->assertStringNotContainsString( '<', $href );
	}

	public function test_link_rules_for_overridden_urls(): void {
		$ref  = pattern_id( 'newsletter-signup' );
		$html = $this->render_content(
			implode(
				"\n\n",
				array(
					instance( $ref, array( 'Newsletter CTA' => array( 'buttonUrl' => 'mailto:news@example.com' ) ) ),
					instance( $ref, array( 'Newsletter CTA' => array( 'buttonUrl' => '/internal/offer/' ) ) ),
					instance( $ref, array( 'Newsletter CTA' => array( 'buttonUrl' => 'tel:+15551234' ) ) ),
					instance( $ref, array( 'Newsletter CTA' => array( 'buttonUrl' => 'http://partner.example.org/deal?x=1' ) ) ),
					instance( $ref, array( 'Newsletter CTA' => array( 'buttonUrl' => 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==' ) ) ),
					instance( $ref, array( 'Newsletter CTA' => array( 'buttonUrl' => home_url( '/landing/' ) ) ) ),
				)
			)
		);
		$all = ctas( $html );
		$this->assertCount( 6, $all, $html );
		$this->assertSame( 'mailto:news@example.com', $all[0]['link']['href'] ?? null );
		$this->assertSame( '/internal/offer/', $all[1]['link']['href'] ?? null );
		$this->assertSame( 'tel:+15551234', $all[2]['link']['href'] ?? null );

		[ $url, $args ] = split_url( $all[3]['link']['href'] ?? '' );
		$this->assertSame( 'http://partner.example.org/deal', $url );
		$this->assertSame( '1', $args['x'] ?? null );
		$this->assertSame( 'newsletter', $args['utm_campaign'] ?? null );

		$this->assertStringNotContainsString( 'data:', $all[4]['link']['href'] ?? '' );
		$this->assertStringNotContainsString( 'base64', $html );
		$this->assertSame( home_url( '/landing/' ), $all[5]['link']['href'] ?? null, 'Links to our own site stay untracked' );

		foreach ( $all as $cta ) {
			$this->assertSame( 'Join our newsletter', $cta['heading'] );
			$this->assertSame( 'Subscribe', $cta['button'] );
		}
	}

	public function test_empty_heading_override_omits_the_heading(): void {
		$html = $this->render_content( instance( pattern_id( 'newsletter-signup' ), array( 'Newsletter CTA' => array( 'heading' => '' ) ) ) );
		$all  = ctas( $html );
		$this->assertCount( 1, $all, $html );
		$this->assertNull( $all[0]['heading_tag'], 'An empty heading must not be output' );
		$this->assertSame( 'Subscribe', $all[0]['button'] );
	}

	public function test_same_pattern_on_other_posts_is_unaffected(): void {
		$all = ctas( render_slug( 'newsletter-everywhere' ) );
		$this->assertCount( 1, $all );
		$this->assertSame( 'Join our newsletter', $all[0]['heading'] );
		$this->assertTracked( $all[0], 'https://news.example.com/subscribe', 'newsletter' );
	}

	public function test_tracking_setting_and_filter_apply_to_overridden_links(): void {
		update_option( 'acme_cta_tracking', array( 'enabled' => false, 'utm_source' => 'acme-blog', 'utm_medium' => 'cta' ) );
		$all = ctas( render_slug( 'pricing', 'page' ) );
		$this->assertSame( 'https://news.example.com/pricing', $all[0]['link']['href'] ?? null, 'Tracking disabled: plain overridden link' );
		$this->assertSame( 'https://news.example.com/subscribe', $all[2]['link']['href'] ?? null );

		update_option( 'acme_cta_tracking', array( 'enabled' => true, 'utm_source' => 'spring', 'utm_medium' => 'banner' ) );
		$filter = static function ( $tracked, $url, $campaign ) {
			return $tracked . '&acme_ref=' . rawurlencode( $url );
		};
		add_filter( 'acme_cta_tracked_url', $filter, 10, 3 );
		try {
			$all = ctas( render_slug( 'pricing', 'page' ) );
		} finally {
			remove_filter( 'acme_cta_tracked_url', $filter, 10 );
		}
		[ $url, $args ] = split_url( $all[0]['link']['href'] ?? '' );
		$this->assertSame( 'https://news.example.com/pricing', $url );
		$this->assertSame( 'spring', $args['utm_source'] ?? null );
		$this->assertSame( 'banner', $args['utm_medium'] ?? null );
		$this->assertSame( 'https://news.example.com/pricing', $args['acme_ref'] ?? null, 'acme_cta_tracked_url must receive the displayed URL' );
	}

	public function test_standalone_ctas_keep_their_markup(): void {
		$all = ctas( render_slug( 'standalone-ctas' ) );
		$this->assertCount( 2, $all );

		$this->assertContains( 'is-variant-secondary', $all[0]['classes'] );
		$this->assertSame( 'Talk to sales', $all[0]['heading'] );
		$this->assertStringContainsString( '<em>sales</em>', $all[0]['heading_html'] );
		$this->assertSame( 'h2', $all[0]['heading_tag'] );
		$this->assertSame( '/contact/', $all[0]['link']['href'] ?? null );
		$this->assertStringContainsString( 'wp-element-button', $all[0]['link']['class'] ?? '' );

		$this->assertSame( 'report', $all[1]['attrs']['id'] ?? null, 'The HTML anchor must be kept' );
		$this->assertSame( 'h4', $all[1]['heading_tag'] );
		$this->assertSame( 'Get the PDF', $all[1]['button'] );
		$this->assertSame( '_blank', $all[1]['link']['target'] ?? null );
		[ $url, $args ] = split_url( $all[1]['link']['href'] ?? '' );
		$this->assertSame( 'https://files.example.org/report.pdf', $url );
		$this->assertSame( 'report-2026', $args['utm_campaign'] ?? null );
	}

	public function test_1x_cta_still_renders(): void {
		$html = render_slug( 'legacy-cta' );
		$all  = ctas( $html, true );
		$this->assertCount( 1, $all, $html );
		$this->assertSame( 'Try Acme free', $all[0]['heading'] );
		$this->assertSame( 'Start trial', $all[0]['button'] );
		[ $url ] = split_url( $all[0]['link']['href'] ?? '' );
		$this->assertSame( 'https://app.example.net/signup', $url );
		$this->assertStringContainsString( 'From the 1.x days.', $html );
	}

	public function test_wrapper_classes_and_variants_filter(): void {
		$filter = static function ( $variants ) {
			$variants['brand'] = 'Brand';
			return $variants;
		};
		add_filter( 'acme_cta_variants', $filter );
		try {
			$html = $this->render_content(
				'<!-- wp:acme/cta {"heading":"Wide one","buttonText":"Go","buttonUrl":"/go/","variant":"brand","align":"wide","className":"my-cta"} -->' . "\n"
				. '<div class="wp-block-acme-cta alignwide my-cta is-variant-brand"><h2 class="wp-block-acme-cta__heading">Wide one</h2><a class="wp-block-acme-cta__button wp-element-button" href="/go/">Go</a></div>' . "\n"
				. '<!-- /wp:acme/cta -->'
			);
		} finally {
			remove_filter( 'acme_cta_variants', $filter );
		}
		$all = ctas( $html );
		$this->assertCount( 1, $all, $html );
		foreach ( array( 'wp-block-acme-cta', 'alignwide', 'my-cta', 'is-variant-brand' ) as $class ) {
			$this->assertContains( $class, $all[0]['classes'], "Missing wrapper class $class" );
		}
		$this->assertSame( '/go/', $all[0]['link']['href'] ?? null );
	}
}
