<?php
/**
 * Audit log of admin actions.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Records who deleted submissions, exported them or changed the settings, in
 * `{prefix}acme_forms_audit_log`:
 *
 * - id, created_at (UTC, Y-m-d H:i:s), user_id, action, object_id, details (JSON object)
 * - `submission_deleted`   object_id = submission ID, details { form_id }
 * - `submissions_exported` object_id = form ID,       details { from, to, rows } (from/to null when not set)
 * - `settings_updated`     object_id = 0,             details { changed: [ setting keys ] }
 *
 * Entries are never changed or deleted by the plugin.
 */
class Audit_Log {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_forms_audit_log';
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'acme_forms_submission_deleted', array( $this, 'on_deleted' ), 10, 2 );
		add_action( 'acme_forms_submissions_exported', array( $this, 'on_exported' ), 10, 4 );
		add_action( 'acme_forms_settings_updated', array( $this, 'on_settings' ), 10, 2 );
	}

	/**
	 * Schema (used by the installer).
	 *
	 * @return string
	 */
	public static function schema() {
		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY action (action)
		) {$collate};";
	}

	/**
	 * Add an entry.
	 *
	 * @param string $action    Action key.
	 * @param int    $object_id Object ID.
	 * @param array  $details   Details (stored as JSON).
	 * @return int Entry ID.
	 */
	public static function record( $action, $object_id, array $details = array() ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'user_id'    => get_current_user_id(),
				'action'     => (string) $action,
				'object_id'  => (int) $object_id,
				'details'    => wp_json_encode( (object) $details ),
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Entries, newest first.
	 *
	 * @param int $per_page Page size.
	 * @param int $page     1-based page.
	 * @return object[]
	 */
	public static function entries( $per_page = 50, $page = 1 ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $per_page,
				max( 0, ( (int) $page - 1 ) * (int) $per_page )
			)
		);
	}

	/**
	 * Number of entries.
	 *
	 * @return int
	 */
	public static function total() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * A submission was deleted.
	 *
	 * @param int    $id         Submission ID.
	 * @param object $submission Deleted submission.
	 */
	public function on_deleted( $id, $submission ) {
		self::record( 'submission_deleted', $id, array( 'form_id' => (int) $submission->form_id ) );
	}

	/**
	 * Submissions were exported.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $from    Start day or ''.
	 * @param string $to      End day or ''.
	 * @param int    $rows    Exported submissions.
	 */
	public function on_exported( $form_id, $from, $to, $rows ) {
		self::record(
			'submissions_exported',
			$form_id,
			array(
				'from' => '' === $from ? null : $from,
				'to'   => '' === $to ? null : $to,
				'rows' => (int) $rows,
			)
		);
	}

	/**
	 * Settings were saved.
	 *
	 * @param array $old Previous settings.
	 * @param array $new New settings.
	 */
	public function on_settings( $old, $new ) {
		$changed = array();
		foreach ( $new as $key => $value ) {
			if ( ! array_key_exists( $key, $old ) || (string) $old[ $key ] !== (string) $value ) {
				$changed[] = $key;
			}
		}
		sort( $changed );
		self::record( 'settings_updated', 0, array( 'changed' => $changed ) );
	}
}
