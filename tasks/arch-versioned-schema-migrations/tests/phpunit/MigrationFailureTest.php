<?php
/**
 * Failures are logged, stop the run, don't retry by themselves, and are retried by an
 * administrator from the notice.
 */

use WPSB\CRM\CrmTestCase;
use function WPSB\CRM\db_version;
use function WPSB\CRM\install_test_migrations;
use function WPSB\CRM\latest;
use function WPSB\CRM\log_entries;
use function WPSB\CRM\migration_log;
use function WPSB\CRM\raw_option;
use function WPSB\CRM\set_raw_option;
use function WPSB\CRM\test_log;

class MigrationFailureTest extends CrmTestCase {

	public static function failure_modes(): array {
		return array(
			'exception' => array( 'throw', 'Disk quota exceeded on 90' ),
			'WP_Error'  => array( 'wp_error', 'Remote schema service unavailable' ),
			'false'     => array( 'false', '' ),
			'db error'  => array( 'dberror', '' ),
		);
	}

	/**
	 * @dataProvider failure_modes
	 */
	public function test_failure_is_logged_and_stops_the_run( string $mode, string $message ): void {
		$this->migrate_fully();
		$spec = array( 'mode' => $mode );
		if ( '' !== $message ) {
			$spec['message'] = $message;
		}
		install_test_migrations(
			array(
				90 => $spec,
				91 => array( 'mode' => 'ok' ),
			)
		);

		$r = $this->visit( '/' );
		$this->assertSame( 200, $r['status'], 'The site must keep working after a failed migration' );
		$this->assertSame( array( 90 ), test_log( 'up' ), 'Migration 91 must not run after 90 failed' );
		$this->assertSame( latest(), db_version(), 'The version stays at the last successful migration' );
		$this->assertNull( raw_option( 'wpsb_crm_test_marker_91' ) );

		$failed = log_entries( 90, 'failed' );
		$this->assertCount( 1, $failed, 'The failure must be logged: ' . wp_json_encode( migration_log() ) );
		$this->assertIsInt( $failed[0]['time'] );
		$this->assertIsString( $failed[0]['message'] );
		if ( '' !== $message ) {
			$this->assertStringContainsString( $message, $failed[0]['message'] );
		}
		$this->assertSame( array(), log_entries( 90, 'applied' ) );
		$this->assertNull( raw_option( 'acme_crm_migration_lock' ), 'The lock must be released after a failure' );

		// No automatic retry on every request.
		$this->visit( '/' );
		$this->visit( '/wp-json/' );
		$this->assertSame( array( 90 ), test_log( 'up' ), 'A failed migration must not be retried automatically on every request' );
		$this->assertCount( 1, log_entries( 90, 'failed' ) );
	}

	/** Find the notice's retry control and return [method, url, fields]. */
	private function retry_control( string $html ): ?array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();
		$xp = new DOMXPath( $dom );
		foreach ( $xp->query( '//*[contains(concat(" ", normalize-space(@class), " "), " notice ")]' ) as $notice ) {
			if ( false === strpos( $notice->textContent, 'Acme CRM database update failed' ) ) {
				continue;
			}
			foreach ( $xp->query( './/a[@href]', $notice ) as $a ) {
				if ( false !== stripos( $a->textContent, 'retry' ) ) {
					return array( 'GET', html_entity_decode( $a->getAttribute( 'href' ) ), array() );
				}
			}
			foreach ( $xp->query( './/form', $notice ) as $form ) {
				if ( false === stripos( $form->textContent . $dom->saveHTML( $form ), 'retry' ) ) {
					continue;
				}
				$fields = array();
				foreach ( $xp->query( './/input[@name]|.//button[@name]', $form ) as $input ) {
					$type = strtolower( $input->getAttribute( 'type' ) );
					if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $input->hasAttribute( 'checked' ) ) {
						continue;
					}
					$fields[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
				}
				$method = strtoupper( $form->getAttribute( 'method' ) ?: 'GET' );
				$action = html_entity_decode( $form->getAttribute( 'action' ) ?: '/wp-admin/' );
				return array( $method, $action, $fields );
			}
		}
		return null;
	}

	private function send( array $control, array $login, bool $strip_nonce = false ): array {
		list( $method, $url, $fields ) = $control;
		if ( $strip_nonce ) {
			$url = remove_query_arg( array( '_wpnonce', 'nonce', '_ajax_nonce' ), $url );
			foreach ( array_keys( $fields ) as $k ) {
				if ( false !== stripos( $k, 'nonce' ) ) {
					unset( $fields[ $k ] );
				}
			}
		}
		if ( 'GET' === $method ) {
			$r = $this->http( 'GET', $fields ? add_query_arg( $fields, $url ) : $url, array( 'login' => $login ) );
		} else {
			$r = $this->http( 'POST', $url, array( 'login' => $login, 'body' => $fields, 'headers' => array( 'Referer' => 'http://127.0.0.1:9400/wp-admin/' ) ) );
		}
		wp_cache_flush();
		return $r;
	}

	public function test_admin_notice_and_retry(): void {
		$this->migrate_fully();
		install_test_migrations(
			array(
				90 => array(
					'mode'    => 'throw',
					'message' => 'Column quota exceeded',
				),
				91 => array( 'mode' => 'ok' ),
			)
		);
		$this->visit( '/' );
		$this->assertCount( 1, log_entries( 90, 'failed' ) );

		$admin  = $this->http_login( 1 );
		$editor = $this->http_login( $this->user_id( 'sally' ) );

		$page = $this->http( 'GET', '/wp-admin/', array( 'login' => $admin ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertStringContainsString( 'Acme CRM database update failed', $page['body'], 'Administrators must see the failure notice' );
		$this->assertStringContainsString( 'Column quota exceeded', $page['body'] );
		$this->assertMatchesRegularExpression( '/\b90\b/', wp_strip_all_tags( $page['body'] ) );
		$control = $this->retry_control( $page['body'] );
		$this->assertNotNull( $control, 'The notice must have a Retry button or link' );

		$ed_page = $this->http( 'GET', '/wp-admin/', array( 'login' => $editor ) );
		$this->assertSame( 200, $ed_page['status'] );
		$this->assertStringNotContainsString( 'Acme CRM database update failed', $ed_page['body'], 'Only administrators see the notice' );

		// The problem is fixed (e.g. a hotfix was deployed) …
		set_raw_option(
			'wpsb_crm_test_migrations',
			array(
				90 => array( 'mode' => 'ok' ),
				91 => array( 'mode' => 'ok' ),
			)
		);

		// … but neither an editor nor a forged request may trigger the retry.
		$this->send( $control, $editor );
		$this->send( $control, $admin, true );
		$this->visit( '/' );
		$this->assertSame( array( 90 ), test_log( 'up' ), 'Retry must require an administrator and a valid nonce' );
		$this->assertSame( latest(), db_version() );

		// The administrator retries.
		$r = $this->send( $control, $admin );
		$this->assertContains( $r['status'], array( 200, 302, 303 ), substr( $r['body'], 0, 500 ) );
		$this->assertSame( array( 90, 90, 91 ), test_log( 'up' ), 'Retry must run the failed migration and the ones after it' );
		$this->assertSame( 91, db_version() );
		$this->assertCount( 1, log_entries( 90, 'applied' ) );
		$this->assertCount( 1, log_entries( 91, 'applied' ) );

		$page = $this->http( 'GET', '/wp-admin/', array( 'login' => $admin ) );
		$this->assertStringNotContainsString( 'Acme CRM database update failed', $page['body'], 'The notice disappears once the migrations succeeded' );
	}

	public function test_later_deploys_still_migrate_after_success(): void {
		$this->migrate_fully();
		install_test_migrations( array( 90 => array( 'mode' => 'ok' ) ) );
		$this->visit( '/wp-json/' );
		$this->assertSame( 90, db_version() );
		// A new add-on release ships another migration.
		set_raw_option(
			'wpsb_crm_test_migrations',
			array(
				90 => array( 'mode' => 'ok' ),
				95 => array( 'mode' => 'ok' ),
			)
		);
		$this->visit( '/' );
		$this->assertSame( 95, db_version() );
		$this->assertSame( array( 90, 95 ), test_log( 'up' ) );
	}
}
