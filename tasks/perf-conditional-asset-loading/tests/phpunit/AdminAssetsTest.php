<?php
/**
 * wp-admin: only the screens that need the kit load it.
 */

class AdminAssetsTest extends WPSB\UI\AssetsTestCase {

	public function test_unrelated_admin_screens_load_nothing(): void {
		foreach ( array( '/wp-admin/', '/wp-admin/edit.php', '/wp-admin/edit.php?post_type=page', '/wp-admin/users.php', '/wp-admin/options-general.php', '/wp-admin/plugins.php' ) as $path ) {
			$this->assertNoKitAssets( $path, 1 );
		}
	}

	public function test_settings_screen(): void {
		list( $html, $a ) = $this->fetch( '/wp-admin/options-general.php?page=acme-ui', 1 );
		$this->assertStringContainsString( 'acme-ui-settings', $html );
		$this->assertArrayHasKey( 'acme-ui-admin', $a['scripts'], 'Settings screen script missing' );
		$this->assertArrayHasKey( 'acme-ui-admin', $a['styles'], 'Settings screen styles missing' );
		$this->assertArrayHasKey( 'acme-ui-core', $a['scripts'], 'The preview needs the runtime' );
		$this->assertArrayHasKey( 'acme-tabs-style', $a['styles'], 'The preview shows tabs' );
		$this->assertArrayNotHasKey( 'acme-ui-motion', $a['scripts'] );
		$this->assertArrayNotHasKey( 'acme-carousel-style', $a['styles'] );
	}
}
