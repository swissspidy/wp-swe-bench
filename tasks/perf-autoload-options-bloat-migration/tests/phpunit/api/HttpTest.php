<?php
/**
 * Admin screens over real HTTP (Playground): Tools → Activity Log (pagination,
 * filters, per-user preferences, bulk delete, CSV export) and Settings → Activity Log.
 */

use function WPSB\ActivityLog\autoload_bytes;
use function WPSB\ActivityLog\expected_entries;
use function WPSB\ActivityLog\expected_query;
use function WPSB\ActivityLog\ids;
use function WPSB\ActivityLog\parse_list_table;
use function WPSB\ActivityLog\plugin_option_names;
use function WPSB\ActivityLog\user_id;

class HttpTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private static $settings_backup;
	private static array $state_backup = array();

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		WPSB\ActivityLog\delete_probes();
		self::$settings_backup = get_option( 'acme_activity_settings' );
		self::$state_backup    = acme_activity_get_user_state( 1 );
	}

	public static function tearDownAfterClass(): void {
		update_option( 'acme_activity_settings', self::$settings_backup );
		acme_activity_update_user_state( 1, self::$state_backup );
		WPSB\ActivityLog\delete_probes();
		parent::tearDownAfterClass();
	}

	private function screen( array $login, string $query = '' ): array {
		$res = $this->http( 'GET', '/wp-admin/tools.php?page=acme-activity-log' . ( $query ? '&' . $query : '' ), array( 'login' => $login ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 2000 ) );
		return $res + array( 'table' => parse_list_table( $res['body'] ) );
	}

	public function test_log_screen_pagination_and_filters(): void {
		$admin = $this->http_login( 1 );
		acme_activity_update_user_state( 1, array( 'per_page' => 25 ) );
		$total = count( expected_entries() );

		$page1 = $this->screen( $admin );
		$this->assertSame( ids( expected_query( array( 'per_page' => 25 ) )['entries'] ), $page1['table']['ids'] );
		$this->assertSame( $total, $page1['table']['total'] );
		$this->assertStringContainsString( 'column-ip hidden', $page1['body'], 'Hidden columns are remembered per user' );

		$page3 = $this->screen( $admin, 'paged=3' );
		$this->assertSame( ids( expected_query( array( 'per_page' => 25, 'page' => 3 ) )['entries'] ), $page3['table']['ids'] );

		$last  = (int) ceil( $total / 25 );
		$pagel = $this->screen( $admin, 'paged=' . $last );
		$this->assertSame( ids( expected_query( array( 'per_page' => 25, 'page' => $last ) )['entries'] ), $pagel['table']['ids'] );
		$this->assertCount( $total - ( $last - 1 ) * 25, $pagel['table']['ids'] );

		$trashed = $this->screen( $admin, 'filter_action=post_trashed&paged=2' );
		$exp     = expected_query( array( 'action' => 'post_trashed', 'per_page' => 25, 'page' => 2 ) );
		$this->assertSame( ids( $exp['entries'] ), $trashed['table']['ids'] );
		$this->assertSame( $exp['total'], $trashed['table']['total'] );
		$this->assertMatchesRegularExpression( '/<option value="backup_completed"/', $trashed['body'], 'The action filter lists the actions found in the log' );

		$user = $this->screen( $admin, 'filter_user=7&paged=4' );
		$this->assertSame( ids( expected_query( array( 'user_id' => 7, 'per_page' => 25, 'page' => 4 ) )['entries'] ), $user['table']['ids'] );

		$search = $this->screen( $admin, 's=' . rawurlencode( '100%' ) . '&paged=2' );
		$exp    = expected_query( array( 'search' => '100%', 'per_page' => 25, 'page' => 2 ) );
		$this->assertSame( ids( $exp['entries'] ), $search['table']['ids'] );
		$this->assertSame( $exp['total'], $search['table']['total'] );

		$this->assertStringContainsString( '(deleted user #999)', $this->screen( $admin, 'filter_user=999' )['body'] );
	}

	public function test_new_since_last_visit_highlighting(): void {
		$admin   = $this->http_login( 1 );
		$entries = expected_entries();
		acme_activity_update_user_state( 1, array( 'per_page' => 25, 'last_seen' => $entries[10]['time'] ) );

		$first = $this->screen( $admin );
		$new   = array_keys( array_filter( $first['table']['classes'], static fn( $c ) => (bool) preg_match( '/\bis-new\b/', $c ) ) );
		$exp   = ids( array_filter( array_slice( $entries, 0, 25 ), static fn( $e ) => $e['time'] > $entries[10]['time'] ) );
		$this->assertSame( $exp, $new );

		$second = $this->screen( $admin );
		$this->assertStringNotContainsString( 'is-new', implode( ' ', $second['table']['classes'] ), 'Visiting the screen marks everything as seen' );
		wp_cache_flush();
		$this->assertGreaterThan( $entries[0]['time'], acme_activity_get_user_state( 1 )['last_seen'] );
	}

	public function test_rows_per_page_is_remembered(): void {
		$priya = $this->http_login( user_id( 'priya' ) );
		$prev  = acme_activity_get_user_state( user_id( 'priya' ) );

		$this->assertCount( 100, $this->screen( $priya, 'per_page=100' )['table']['ids'] );
		$this->assertCount( 100, $this->screen( $priya )['table']['ids'] );
		$this->assertCount( 10, $this->screen( $priya, 'per_page=10' )['table']['ids'] );
		wp_cache_flush();
		$this->assertSame( 10, acme_activity_get_user_state( user_id( 'priya' ) )['per_page'] );

		acme_activity_update_user_state( user_id( 'priya' ), $prev );
	}

	public function test_screen_preferences_are_not_stored_in_options(): void {
		$lena = $this->http_login( user_id( 'lena' ) );
		$this->screen( $lena, 'per_page=50&hide=user' );
		$this->screen( $lena );
		wp_cache_flush();
		$this->assertSame( array( 'user' ), acme_activity_get_user_state( user_id( 'lena' ) )['hidden_columns'] );
		$this->assertSame( array(), array_values( preg_grep( '/^acme_activity_ui/', plugin_option_names() ) ) );
		$this->assertLessThan( 2000, autoload_bytes( 'acme_activity' ) );
		acme_activity_update_user_state( user_id( 'lena' ), array( 'per_page' => 100, 'hidden_columns' => array( 'object', 'ip' ) ) );
	}

	public function test_bulk_delete(): void {
		$admin = $this->http_login( 1 );
		$a     = acme_activity_log( WPSB\ActivityLog\PROBE_ACTION, array( 'message' => 'delete me A' ) );
		$b     = acme_activity_log( WPSB\ActivityLog\PROBE_ACTION, array( 'message' => 'delete me B' ) );
		$c     = acme_activity_log( WPSB\ActivityLog\PROBE_ACTION, array( 'message' => 'keep me C' ) );
		$this->assertIsInt( $a );

		$forged = $this->http(
			'POST',
			'/wp-admin/tools.php?page=acme-activity-log',
			array(
				'login' => $admin,
				'body'  => array(
					'action'   => 'delete',
					'entry'    => array( $a ),
					'_wpnonce' => 'nope',
				),
			)
		);
		$this->assertNotSame( 302, $forged['status'] );
		$this->assertSame( 200, $this->entry_status( $admin, $a ), 'Bulk delete needs a valid nonce' );

		$res = $this->http(
			'POST',
			'/wp-admin/tools.php?page=acme-activity-log',
			array(
				'login' => $admin,
				'body'  => array(
					'action'   => '-1',
					'action2'  => 'delete',
					'entry'    => array( $a, $b ),
					'_wpnonce' => $this->nonce_for( 1, 'bulk-activity-entries', $admin['logged_in'] ),
				),
			)
		);
		$this->assertSame( 302, $res['status'], substr( $res['body'], 0, 1000 ) );
		$this->assertStringContainsString( 'deleted=2', $res['headers']['location'] ?? '' );
		$this->assertSame( 404, $this->entry_status( $admin, $a ) );
		$this->assertSame( 404, $this->entry_status( $admin, $b ) );
		$this->assertSame( 200, $this->entry_status( $admin, $c ) );
	}

	private function entry_status( array $login, int $id ): int {
		return $this->http( 'GET', '/wp-json/acme-activity/v1/entries/' . $id, array( 'login' => $login, 'rest_nonce' => true ) )['status'];
	}

	public function test_csv_export(): void {
		$admin = $this->http_login( 1 );
		$nonce = $this->nonce_for( 1, 'acme_activity_export', $admin['logged_in'] );
		$res   = $this->http( 'GET', '/wp-admin/admin-post.php?action=acme_activity_export&filter_action=plugin_activated&_wpnonce=' . $nonce, array( 'login' => $admin ) );
		$this->assertSame( 200, $res['status'] );
		$this->assertStringContainsString( 'text/csv', $res['headers']['content-type'] ?? '' );
		$rows = array_map( 'str_getcsv', array_values( array_filter( explode( "\n", trim( $res['body'] ) ) ) ) );
		$this->assertSame( array( 'id', 'time', 'user_id', 'action', 'object_type', 'object_id', 'message', 'ip' ), $rows[0] );
		$exp = expected_query( array( 'action' => 'plugin_activated', 'per_page' => -1 ) )['entries'];
		$this->assertSame( ids( $exp ), array_map( 'intval', array_column( array_slice( $rows, 1 ), 0 ) ) );
		$this->assertSame( gmdate( 'Y-m-d H:i:s', $exp[0]['time'] ), $rows[1][1] );

		$this->assertSame( 403, $this->http( 'GET', '/wp-admin/admin-post.php?action=acme_activity_export&_wpnonce=' . $nonce, array( 'login' => $this->http_login( user_id( 'olivia' ) ) ) )['status'] );
	}

	public function test_screens_require_administrators(): void {
		$olivia = $this->http_login( user_id( 'olivia' ) );
		$this->assertSame( 403, $this->http( 'GET', '/wp-admin/tools.php?page=acme-activity-log', array( 'login' => $olivia ) )['status'] );
		$this->assertSame( 403, $this->http( 'GET', '/wp-admin/options-general.php?page=acme-activity-settings', array( 'login' => $olivia ) )['status'] );
	}

	private function save_settings( array $login, array $fields ): array {
		return $this->http(
			'POST',
			'/wp-admin/options.php',
			array(
				'login' => $login,
				'body'  => array(
					'option_page'            => 'acme_activity_settings',
					'action'                 => 'update',
					'_wpnonce'               => $this->nonce_for( 1, 'acme_activity_settings-options', $login['logged_in'] ),
					'_wp_http_referer'       => '/wp-admin/options-general.php?page=acme-activity-settings',
					'acme_activity_settings' => $fields,
				),
			)
		);
	}

	public function test_settings_screen_and_retention_setting(): void {
		$admin = $this->http_login( 1 );
		$page  = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-activity-settings', array( 'login' => $admin ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/name="acme_activity_settings\[tracked\]\[\]" value="user_login"\s+checked/', $page['body'] );
		$this->assertStringContainsString( 'name="acme_activity_settings[retention_days]"', $page['body'] );

		$cases = array(
			'45'    => 45,
			'0'     => 0,
			'-5'    => 0,
			'abc'   => 0,
			'99999' => 3650,
			'3650'  => 3650,
			'1'     => 1,
		);
		foreach ( $cases as $input => $stored ) {
			$res = $this->save_settings(
				$admin,
				array(
					'tracked'        => array( 'user_login', 'post_published' ),
					'log_ip'         => '1',
					'retention_days' => (string) $input,
				)
			);
			$this->assertSame( 302, $res['status'], "Saving '$input' failed: " . substr( $res['body'], 0, 500 ) );
			wp_cache_flush();
			$saved = get_option( 'acme_activity_settings' );
			$this->assertSame( $stored, (int) ( $saved['retention_days'] ?? -1 ), "retention_days '$input'" );
			$this->assertSame( array( 'user_login', 'post_published' ), $saved['tracked'] );
			$this->assertTrue( (bool) $saved['log_ip'] );
		}

		$this->assertArrayHasKey( 'acme_activity_settings', WPSB\ActivityLog\autoloaded_options() );
		$page = $this->http( 'GET', '/wp-admin/options-general.php?page=acme-activity-settings', array( 'login' => $admin ) );
		$this->assertMatchesRegularExpression( '/name="acme_activity_settings\[retention_days\]"[^>]*value="1"|value="1"[^>]*name="acme_activity_settings\[retention_days\]"/', $page['body'] );
	}
}
