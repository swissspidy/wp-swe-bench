<?php
/**
 * Feature: audit log of deletions, exports and settings changes + the read-only screen.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\audit_since;
use function WPSB\Forms\audit_table_exists;
use function WPSB\Forms\count_rows;
use function WPSB\Forms\forge_tokens;
use function WPSB\Forms\forge_url_tokens;
use function WPSB\Forms\form_id;
use function WPSB\Forms\sub_id;
use function WPSB\Forms\user;
use function WPSB\Forms\xpath;

class AuditLogTest extends HttpCase {

	private function assertEntry( object $entry, string $action, int $object_id, string $login ): void {
		$this->assertSame( $action, $entry->action );
		$this->assertEquals( $object_id, $entry->object_id, "$action object_id" );
		$this->assertEquals( user( $login ), $entry->user_id, "$action user_id" );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $entry->created_at );
		$this->assertLessThan( 600, abs( time() - strtotime( $entry->created_at . ' UTC' ) ), 'created_at is now, in UTC' );
		$this->assertIsArray( $entry->details, 'details is a JSON object' );
	}

	private function settings_form(): array {
		$res = $this->get( 'admin', '/wp-admin/admin.php?page=acme-forms-settings' );
		$this->assertSame( 200, $res['status'] );
		return $this->find_form( $res['body'], ".//input[@name='acme_forms_settings[notify_email]' or @name='notify_email']" );
	}

	public function test_deletions_are_logged(): void {
		$this->assertTrue( audit_table_exists(), 'the audit log table exists without re-activating the plugin' );
		$contact = form_id( 'contact-us' );

		$id   = sub_id( 'visitor03@example.org' );
		$link = $this->row_delete_link( $this->inbox( 'admin', array( 's' => 'visitor03@example.org' ) )['body'], $id );
		$this->http( 'GET', $link, array( 'login' => $this->session( 'admin' ) ) );
		$entries = audit_since( $this->audit_before );
		$this->assertCount( 1, $entries );
		$this->assertEntry( $entries[0], 'submission_deleted', $id, 'admin' );
		$this->assertEquals( array( 'form_id' => $contact ), $entries[0]->details );

		$ids  = array( sub_id( 'visitor01@example.org' ), sub_id( 'visitor02@example.org' ) );
		$form = $this->list_form( $this->inbox( 'erin', array( 's' => 'visitor0' ) )['body'] );
		$form['values']['submission[]'] = array_map( 'strval', $ids );
		$this->submit( 'erin', $form['method'], $form['action'], $form['values'] );
		$this->assertSame( 42, count_rows() );
		$entries = array_slice( audit_since( $this->audit_before ), 1 );
		$this->assertCount( 2, $entries, 'one entry per deleted submission' );
		foreach ( $entries as $entry ) {
			$this->assertEntry( $entry, 'submission_deleted', (int) $entry->object_id, 'erin' );
			$this->assertEquals( array( 'form_id' => $contact ), $entry->details );
		}
		$this->assertEqualsCanonicalizing( $ids, array_map( 'intval', array_column( $entries, 'object_id' ) ) );
	}

	public function test_refused_deletions_are_not_logged(): void {
		$id   = sub_id( 'visitor04@example.org' );
		$link = $this->row_delete_link( $this->inbox( 'admin', array( 's' => 'visitor04@example.org' ) )['body'], $id );
		$this->http( 'GET', forge_url_tokens( $link ), array( 'login' => $this->session( 'admin' ) ) );
		$this->get( 'admin', '/wp-admin/admin.php?page=acme-forms-submissions&action=delete&submission=' . $id );
		$this->get( 'sally', '/wp-admin/admin.php?page=acme-forms-submissions&action=delete&submission=' . $id );
		$this->assertSame( 45, count_rows() );
		$this->assertSame( array(), audit_since( $this->audit_before ) );
	}

	public function test_exports_are_logged(): void {
		$contact = form_id( 'contact-us' );
		$form    = $this->export_form( 'erin' );

		$res = $this->export( 'erin', array( 'form_id' => $contact, 'from' => '2026-04-01', 'to' => '2026-04-30' ), $form );
		$this->assertSame( 200, $res['status'] );
		$res = $this->export( 'erin', array( 'form_id' => $contact, 'from' => '', 'to' => '' ), $form );
		$this->assertSame( 200, $res['status'] );
		$res = $this->export( 'erin', array( 'form_id' => $contact, 'from' => '2026-06-20' ), $form );
		$this->assertSame( 200, $res['status'] );
		$res = $this->export( 'erin', array( 'form_id' => $contact, 'from' => "2026-04-01' OR '1'='1" ), $form );
		$this->assertSame( 400, $res['status'] );

		$entries = audit_since( $this->audit_before );
		$this->assertCount( 3, $entries, 'rejected exports are not logged' );
		foreach ( $entries as $entry ) {
			$this->assertEntry( $entry, 'submissions_exported', $contact, 'erin' );
		}
		$this->assertSame( array( 'from' => '2026-04-01', 'to' => '2026-04-30', 'rows' => 12 ), $this->sorted( $entries[0]->details ) );
		$this->assertSame( array( 'from' => null, 'to' => null, 'rows' => 40 ), $this->sorted( $entries[1]->details ) );
		global $wpdb;
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}acme_form_submissions WHERE form_id = %d AND created_at >= '2026-06-20 00:00:00'", $contact ) );
		$this->assertSame( array( 'from' => '2026-06-20', 'to' => null, 'rows' => $since ), $this->sorted( $entries[2]->details ) );
	}

	private function sorted( array $details ): array {
		$out = array();
		foreach ( array( 'from', 'to', 'rows' ) as $key ) {
			$this->assertArrayHasKey( $key, $details );
			$out[ $key ] = 'rows' === $key ? (int) $details[ $key ] : $details[ $key ];
		}
		return $out;
	}

	public function test_settings_changes_are_logged(): void {
		// Forged first: not logged.
		$form   = $this->settings_form();
		$values = forge_tokens( $form['values'] );
		$this->submit( 'admin', $form['method'], $form['action'], $values );
		$this->assertSame( array(), audit_since( $this->audit_before ), 'refused change is not logged' );

		$values = $form['values'];
		foreach ( array_keys( $values ) as $name ) {
			if ( false !== strpos( $name, 'notify_email' ) ) {
				$values[ $name ] = 'people@acme-recruiting.example';
			}
			if ( false !== strpos( $name, 'store_ip' ) ) {
				unset( $values[ $name ] );
			}
		}
		$res = $this->submit( 'admin', $form['method'], $form['action'], $values );
		$this->assertContains( $res['status'], array( 200, 302, 303 ) );
		$entries = audit_since( $this->audit_before );
		$this->assertCount( 1, $entries );
		$this->assertEntry( $entries[0], 'settings_updated', 0, 'admin' );
		$this->assertEqualsCanonicalizing( array( 'notify_email', 'store_ip' ), $entries[0]->details['changed'] ?? null );
	}

	public function test_audit_screen_is_read_only_and_for_administrators(): void {
		$contact = form_id( 'contact-us' );
		$id      = sub_id( 'visitor09@example.org' );
		$link    = $this->row_delete_link( $this->inbox( 'erin', array( 's' => 'visitor09@example.org' ) )['body'], $id );
		$this->http( 'GET', $link, array( 'login' => $this->session( 'erin' ) ) );
		sleep( 1 );
		$this->export( 'admin', array( 'form_id' => $contact ) );
		$this->assertCount( 2, audit_since( $this->audit_before ) );

		$res = $this->get( 'admin', '/wp-admin/admin.php?page=acme-forms-audit-log' );
		$this->assertSame( 200, $res['status'] );
		$body = $res['body'];
		foreach ( array( 'submission_deleted', 'submissions_exported', 'Erin Editor', 'Ada Admin' ) as $needle ) {
			$this->assertPresent( $needle, $body, 'audit screen' );
		}
		$first = xpath( $body )->query( "//tr[contains(., 'submission_deleted') or contains(., 'submissions_exported')]" )->item( 0 );
		$this->assertNotNull( $first );
		$this->assertStringContainsString( 'submissions_exported', $first->textContent, 'newest first' );

		foreach ( array( 'erin', 'sally' ) as $login ) {
			$res = $this->get( $login, '/wp-admin/admin.php?page=acme-forms-audit-log' );
			$this->assertSame( 403, $res['status'], $login );
			$this->assertAbsent( 'submission_deleted', $res['body'], $login );
		}

		// Nothing on the screen deletes entries.
		$entry = audit_since( $this->audit_before )[0];
		foreach ( array( 'delete', 'clear', 'purge', 'bulk-delete' ) as $action ) {
			$this->get( 'admin', '/wp-admin/admin.php?page=acme-forms-audit-log&action=' . $action . '&id=' . $entry->id . '&entry=' . $entry->id . '&log[]=' . $entry->id );
		}
		$this->assertCount( 2, audit_since( $this->audit_before ) );
	}
}
