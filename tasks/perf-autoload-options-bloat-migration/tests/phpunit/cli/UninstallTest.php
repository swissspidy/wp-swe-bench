<?php
/**
 * Uninstalling removes everything the plugin ever stored (run on an un-migrated
 * and on a migrated site).
 */

class UninstallTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** Cron hooks of the pristine site plus core jobs WordPress may add lazily. */
	private const KNOWN_HOOKS = array(
		'recovery_mode_clean_expired_keys',
		'wp_privacy_delete_old_export_files',
		'wp_privacy_personal_data_cleanup_requests',
		'wp_site_health_scheduled_check',
		'wp_update_plugins',
		'wp_update_themes',
		'wp_version_check',
		'wp_scheduled_delete',
		'delete_expired_transients',
		'wp_scheduled_auto_draft_delete',
		'wp_https_detection',
		'wp_update_user_counts',
		'wp_delete_temp_updater_backups',
	);

	public function test_uninstall_removes_all_plugin_data(): void {
		global $wpdb;
		$res = $this->wp_cli( 'plugin uninstall acme-activity-log --deactivate --skip-delete' );
		$this->assertSame( 0, $res['exit'], $res['stdout'] . $res['stderr'] );
		wp_cache_flush();

		$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '%' . $wpdb->esc_like( 'acme_activity' ) . '%' ) );
		$this->assertSame( array(), $options, 'Options left behind' );

		$meta = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", '%' . $wpdb->esc_like( 'acme_activity' ) . '%' ) );
		$this->assertSame( array(), $meta, 'User meta left behind' );

		$this->assertEmpty( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'acme_activity_log' ) ), 'The log table must be dropped' );

		$hooks = array();
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				$hooks[ $hook ] = true;
			}
		}
		$this->assertSame( array(), array_values( array_diff( array_keys( $hooks ), self::KNOWN_HOOKS ) ), 'Scheduled jobs left behind' );

		$this->assertFileExists( WP_PLUGIN_DIR . '/acme-activity-log/acme-activity-log.php' );
	}
}
