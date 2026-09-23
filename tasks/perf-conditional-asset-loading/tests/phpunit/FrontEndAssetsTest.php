<?php
/**
 * Front end (block theme): the kit's assets load only where components are used.
 */

use function WPSB\UI\kit_scripts;

class FrontEndAssetsTest extends WPSB\UI\AssetsTestCase {

	public function test_pages_without_components_load_nothing(): void {
		foreach ( array( '/', '/about/', '/trail-report-2/', '/category/uncategorized/', '/2026/' ) as $path ) {
			$this->assertNoKitAssets( $path );
		}
	}

	public function test_tabs_in_post_content(): void {
		list( $html ) = $this->assertComponentAssets( '/gear-guide/', array( 'tabs' ) );
		$this->assertStringContainsString( 'data-acme-component="tabs"', $html );
	}

	public function test_accordion_in_post_content(): void {
		$this->assertComponentAssets( '/tent-faq/', array( 'accordion' ) );
	}

	public function test_carousel_loads_the_motion_library_first(): void {
		list( , $a ) = $this->assertComponentAssets( '/gallery/', array( 'carousel' ) );
		foreach ( kit_scripts( $a ) as $handle => $s ) {
			if ( ! in_array( $handle, array( 'acme-ui-core', 'acme-ui-motion' ), true ) ) {
				$this->assertGreaterThan( $a['scripts']['acme-ui-motion']['pos'], $s['pos'], "$handle must come after acme-ui-motion" );
			}
		}
	}

	public function test_all_components_on_one_page(): void {
		$this->assertComponentAssets( '/everything/', array( 'tabs', 'accordion', 'carousel' ) );
	}

	public function test_component_inside_a_synced_pattern(): void {
		$this->assertComponentAssets( '/shipping/', array( 'accordion' ) );
	}

	public function test_component_inside_a_template_part(): void {
		list( $html ) = $this->assertComponentAssets( '/summer/', array( 'tabs' ) );
		$this->assertStringContainsString( 'is-promo', $html, 'The page must use the custom template with the promo template part' );
	}

	public function test_legacy_shortcode(): void {
		list( $html ) = $this->assertComponentAssets( '/packing-list/', array( 'tabs' ) );
		$this->assertStringContainsString( 'acme-tabs--shortcode', $html );
	}

	public function test_other_plugins_requesting_components(): void {
		// Requested early (wp_enqueue_scripts), with an inline script that needs AcmeUI right away.
		list( $html, $a ) = $this->assertComponentAssets( '/newsletter/', array( 'tabs' ), false );
		$this->assertArrayHasKey( 'acme-ui-core-after', $a['inline'], 'The newsletter plugin\'s inline script must still be printed' );
		$this->assertGreaterThan( $a['scripts']['acme-ui-core']['pos'], $a['inline']['acme-ui-core-after'] );
		foreach ( kit_scripts( $a ) as $handle => $s ) {
			if ( 'acme-ui-core' !== $handle ) {
				$this->assertTrue( $s['defer'] || $s['async'], "/newsletter/: $handle must not block rendering" );
			}
		}
		// Requested while the content is rendered.
		$this->assertComponentAssets( '/help/', array( 'accordion' ) );
	}

	public function test_enqueue_api(): void {
		$resolve = static function ( array $components = null ): array {
			$ws = wp_scripts();
			$st = wp_styles();
			foreach ( array( $ws, $st ) as $deps ) {
				$deps->queue = array();
				$deps->to_do = array();
				$deps->done  = array();
			}
			null === $components ? acme_ui_enqueue() : acme_ui_enqueue( $components );
			$ws->all_deps( $ws->queue );
			$st->all_deps( $st->queue );
			$out = array( $ws->to_do, $st->to_do );
			foreach ( array( $ws, $st ) as $deps ) {
				$deps->queue = array();
				$deps->to_do = array();
			}
			return $out;
		};

		list( $scripts, $styles ) = $resolve( array( 'accordion' ) );
		$this->assertContains( 'acme-ui-core', $scripts );
		$this->assertNotContains( 'acme-ui-motion', $scripts, 'An accordion does not need the motion library' );
		$this->assertContains( 'acme-accordion-style', $styles );
		$this->assertContains( 'acme-ui-icons', $styles );
		$this->assertContains( 'acme-ui', $styles );
		$this->assertNotContains( 'acme-tabs-style', $styles );
		$this->assertNotContains( 'acme-carousel-style', $styles );

		list( $scripts, $styles ) = $resolve( array( 'tabs' ) );
		$this->assertNotContains( 'acme-ui-motion', $scripts );
		$this->assertNotContains( 'acme-ui-icons', $styles );
		$this->assertSame( array( 'acme-tabs-style' ), array_values( array_intersect( $styles, array( 'acme-tabs-style', 'acme-accordion-style', 'acme-carousel-style' ) ) ) );

		list( $scripts, $styles ) = $resolve( array( 'carousel' ) );
		$this->assertContains( 'acme-ui-motion', $scripts );
		$this->assertContains( 'acme-carousel-style', $styles );

		list( $scripts, $styles ) = $resolve();
		$this->assertContains( 'acme-ui-core', $scripts );
		$this->assertContains( 'acme-ui-motion', $scripts );
		foreach ( array( 'acme-ui', 'acme-ui-icons', 'acme-tabs-style', 'acme-accordion-style', 'acme-carousel-style' ) as $handle ) {
			$this->assertContains( $handle, $styles, "acme_ui_enqueue() without arguments must load $handle" );
		}
	}

	public function test_config_filter_still_applies(): void {
		list( $html ) = $this->fetch( '/gear-guide/' );
		$this->assertMatchesRegularExpression( '#window\.AcmeUIConfig\s*=\s*\{[^<]*"accent":"\#1e7a4c"[^<]*"animationSpeed":150#', $html );
	}
}
