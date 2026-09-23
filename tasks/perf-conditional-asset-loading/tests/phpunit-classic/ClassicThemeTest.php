<?php
/**
 * Classic theme: components in sidebar widgets (rendered after the head was printed).
 */

class ClassicThemeTest extends WPSB\UI\AssetsTestCase {

	public function test_component_in_a_widget(): void {
		$this->assertSame( 'acme-classic', get_stylesheet() );
		list( $html ) = $this->assertComponentAssets( '/about/', array( 'accordion' ) );
		$this->assertStringContainsString( 'Store hours', $html, 'The widget must be rendered' );
		$this->assertComponentAssets( '/trail-report-3/', array( 'accordion' ) );
	}

	public function test_content_and_widget_components(): void {
		$this->assertComponentAssets( '/gallery/', array( 'carousel', 'accordion' ) );
		$this->assertComponentAssets( '/packing-list/', array( 'tabs', 'accordion' ) );
	}

	public function test_admin_screens_still_clean(): void {
		$this->assertNoKitAssets( '/wp-admin/themes.php', 1 );
	}
}
