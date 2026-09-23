<?php
/**
 * Tools → Acme Migrate over HTTP (real admin requests against the Playground server).
 * Tests run in declaration order: permission checks, dry run, then the real run.
 */

use function WPSB\Migrate\db_checksum;
use function WPSB\Migrate\expected_report;
use function WPSB\Migrate\fixture;
use function WPSB\Migrate\refresh;
use function WPSB\Migrate\stored_value;
use function WPSB\Migrate\totals;
use function WPSB\Migrate\wakeup_log;
use const WPSB\Migrate\NEW_URL;
use const WPSB\Migrate\OLD_URL;

class AdminMigrationTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private static $admin = null;

	private function admin_login(): array {
		if ( null === self::$admin ) {
			self::$admin = $this->http_login( 1 );
		}
		return self::$admin;
	}

	private function form_page( array $login ): array {
		$page = $this->http( 'GET', '/wp-admin/tools.php?page=acme-migrate', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		return $page;
	}

	private function nonce_from( string $html ): string {
		$this->assertMatchesRegularExpression( '/name="_wpnonce" value="([a-f0-9]+)"/', $html );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $html, $m );
		return $m[1];
	}

	private function submit( array $login, array $fields, ?string $nonce ): array {
		$body = array(
			'action'       => 'acme_migrate_run',
			'acme_migrate' => $fields,
		);
		if ( null !== $nonce ) {
			$body['_wpnonce']         = $nonce;
			$body['_wp_http_referer'] = '/wp-admin/tools.php?page=acme-migrate';
		}
		return $this->http( 'POST', '/wp-admin/admin-post.php', array( 'login' => $login, 'body' => $body ) );
	}

	private static function summary( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ) );
		return preg_replace( '/\s+/', ' ', $text );
	}

	public function test_editors_cannot_run_a_migration(): void {
		$editor = $this->create_user( 'editor' );
		$login  = $this->http_login( $editor );
		refresh();
		$before = db_checksum();
		$res    = $this->submit(
			$login,
			array( 'search' => OLD_URL, 'replace' => NEW_URL ),
			$this->nonce_for( $editor, 'acme_migrate_run', $login['logged_in'] )
		);
		$this->assertContains( $res['status'], array( 403, 500 ), 'Editors must not be able to run a migration' );
		refresh();
		$this->assertSame( $before, db_checksum() );
	}

	public function test_requests_without_a_valid_nonce_are_rejected(): void {
		$login = $this->admin_login();
		refresh();
		$before = db_checksum();
		$res    = $this->submit( $login, array( 'search' => OLD_URL, 'replace' => NEW_URL ), null );
		$this->assertNotSame( 302, $res['status'] );
		$res = $this->submit( $login, array( 'search' => OLD_URL, 'replace' => NEW_URL ), 'deadbeef00' );
		$this->assertNotSame( 302, $res['status'] );
		refresh();
		$this->assertSame( $before, db_checksum() );
	}

	public function test_form_offers_the_guid_option(): void {
		$page = $this->form_page( $this->admin_login() );
		$this->assertMatchesRegularExpression( '/<input[^>]+name="acme_migrate\[include_guids\]"/', $page['body'] );
		$this->assertMatchesRegularExpression( '/<input[^>]+name="acme_migrate\[dry_run\]"/', $page['body'] );
	}

	public function test_dry_run_from_the_admin_changes_nothing_and_reports_the_counts(): void {
		$login = $this->admin_login();
		$page  = $this->form_page( $login );
		@unlink( wakeup_log() );
		refresh();
		$before = db_checksum();

		$res = $this->submit( $login, array( 'search' => OLD_URL, 'replace' => NEW_URL, 'dry_run' => '1', 'include_guids' => '1' ), $this->nonce_from( $page['body'] ) );
		$this->assertContains( $res['status'], array( 302, 303 ), substr( $res['body'], 0, 500 ) );

		refresh();
		$this->assertSame( $before, db_checksum(), 'The admin dry run changed the database' );
		$this->assertFileDoesNotExist( wakeup_log() );

		list( $rows, $replacements ) = totals( expected_report( true ) );
		$result = $this->form_page( $login );
		$this->assertStringContainsString( "$replacements replacements in $rows rows would be made (dry run).", self::summary( $result['body'] ) );
	}

	public function test_real_run_from_the_admin_with_guids(): void {
		$login = $this->admin_login();
		$page  = $this->form_page( $login );
		@unlink( wakeup_log() );

		$res = $this->submit( $login, array( 'search' => OLD_URL, 'replace' => NEW_URL, 'include_guids' => '1' ), $this->nonce_from( $page['body'] ) );
		$this->assertContains( $res['status'], array( 302, 303 ), substr( $res['body'], 0, 500 ) );
		refresh();

		list( $rows, $replacements ) = totals( expected_report( true ) );
		$result = $this->form_page( $login );
		$this->assertStringContainsString( "Made $replacements replacements in $rows rows.", self::summary( $result['body'] ) );

		$this->assertSame( NEW_URL . '/?p=4711', stored_value( fixture( 'post-guid' ) ) );
		foreach ( array( 'post-blocks', 'template-index', 'meta-double-serialized', 'meta-unknown-class', 'meta-cache-object', 'option-cache-object', 'redirect-encoded', 'meta-no-match' ) as $key ) {
			$f = fixture( $key );
			$this->assertSame( $f['expected'], stored_value( $f ), "Stored value of fixture $key after the admin run" );
		}
		$this->assertFileDoesNotExist( wakeup_log(), 'A stored object was instantiated during the admin run' );
	}
}
