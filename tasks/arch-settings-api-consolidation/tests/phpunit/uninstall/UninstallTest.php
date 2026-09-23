<?php
/**
 * Uninstalling removes everything the plugin stored (current and 1.x data).
 * Runs on a pristine site after the update was deployed.
 */

class UninstallTest extends AcmeSocialCase {

	protected function tearDown(): void {
		// No snapshot restore: the site is reset after this suite.
		WPSB\TestCase::tearDown();
	}

	private function assertNothingLeft(): void {
		global $wpdb;
		wp_cache_flush();
		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE ( option_name LIKE 'acme\\_social%' OR option_name LIKE 'acme\\_og\\_%' OR option_name LIKE 'acme\\_share\\_%' OR option_name LIKE 'acmesocial\\_%' OR option_name LIKE '\\_transient\\_acme\\_social%' OR option_name LIKE '\\_transient\\_timeout\\_acme\\_social%' )" );
		$this->assertSame( array(), $rows, 'Options/transients left behind' );
		$meta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_acme\\_social%'" );
		$this->assertSame( 0, $meta, 'Post meta left behind' );
		$this->assertFileExists( WP_PLUGIN_DIR . '/acme-social/acme-social.php' );
		// Other data is untouched.
		$this->assertSame( 'Acme Widgets', $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'blogname'" ) );
		$this->assertNotNull( get_post( $this->post_id( 'internal-memo' ) ) );
	}

	public function test_uninstall_after_the_update(): void {
		$this->assertNotNull( $this->raw_option( self::OPTION ), 'site was migrated' );
		set_transient( 'acme_social_og_' . $this->post_id( 'launch-week' ), '<!-- cached -->', HOUR_IN_SECONDS );

		$res = $this->wp_cli( 'plugin uninstall acme-social --deactivate --skip-delete' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		$this->assertNothingLeft();
	}

	public function test_uninstall_of_a_site_that_was_deactivated_before_the_update(): void {
		// The plugin is inactive (as required for deleting it) and its 1.x data is still there.
		$res = $this->wp_cli( 'plugin is-active acme-social' );
		$this->assertNotSame( 0, $res['exit'] );
		$this->seed_legacy_site( $this->pristine_legacy_rows() );
		set_transient( 'acme_social_og_' . $this->post_id( 'launch-week' ), '<!-- cached -->', HOUR_IN_SECONDS );
		update_post_meta( $this->post_id( 'internal-memo' ), '_acme_social_hide_buttons', '1' );

		$res = $this->wp_cli( 'plugin uninstall acme-social --skip-delete' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		$this->assertNothingLeft();
	}
}
