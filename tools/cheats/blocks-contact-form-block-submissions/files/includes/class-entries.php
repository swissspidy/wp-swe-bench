<?php
/**
 * Stored form entries: {prefix}acme_contact_entries.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Entries repository.
 */
class Entries {

	const DB_VERSION = 1;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_contact_entries';
	}

	/**
	 * Create/upgrade the table when needed (sites update the plugin without re-activating it).
	 */
	public static function maybe_install() {
		if ( (int) get_option( 'acme_contact_db_version', 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				form_id varchar(100) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				fields longtext NULL,
				ip_address varchar(45) NOT NULL DEFAULT '',
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY post_form (post_id, form_id),
				KEY created_at (created_at)
			) {$charset};"
		);
		update_option( 'acme_contact_db_version', self::DB_VERSION );
	}

	/**
	 * Store an entry.
	 *
	 * @param int    $post_id Post.
	 * @param string $form_id Form ID.
	 * @param string $email   Sender email ('' if the form has none).
	 * @param array  $fields  Field name => value, in form order.
	 * @return int Entry ID (0 on failure).
	 */
	public static function insert( $post_id, $form_id, $email, array $fields ) {
		global $wpdb;
		self::maybe_install();
		$ok = $wpdb->insert(
			self::table(),
			array(
				'post_id'    => (int) $post_id,
				'form_id'    => (string) $form_id,
				'email'      => (string) $email,
				'fields'     => wp_json_encode( (object) $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'ip_address' => acme_contact_client_ip(),
				'created_at' => current_time( 'mysql', true ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * WHERE clause for a search term (email or any field value, case-insensitive).
	 *
	 * @param string $search Search term.
	 * @return string Prepared SQL fragment ('1=1' for no search).
	 */
	private static function where( $search ) {
		global $wpdb;
		$search = trim( (string) $search );
		if ( '' === $search ) {
			return '1=1';
		}
		$like = '%' . $wpdb->esc_like( strtolower( $search ) ) . '%';
		return $wpdb->prepare( '( LOWER(email) LIKE %s OR LOWER(fields) LIKE %s )', $like, $like );
	}

	/**
	 * Query entries, newest first.
	 *
	 * @param string $search   Search term.
	 * @param int    $per_page Page size (0 = all).
	 * @param int    $page     Page (1-based).
	 * @return array[] Rows with `fields` decoded.
	 */
	public static function query( $search = '', $per_page = 20, $page = 1 ) {
		global $wpdb;
		$table = self::table();
		$where = self::where( $search );
		$sql   = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC, id DESC";
		if ( $per_page > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, ( max( 1, (int) $page ) - 1 ) * $per_page );
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fragments prepared above.
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$decoded       = json_decode( (string) $row['fields'], true );
			$row['fields'] = is_array( $decoded ) ? $decoded : array();
			$out[]         = $row;
		}
		return $out;
	}

	/**
	 * Number of entries.
	 *
	 * @param string $search Search term.
	 * @return int
	 */
	public static function count( $search = '' ) {
		global $wpdb;
		$table = self::table();
		$where = self::where( $search );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- prepared above.
	}
}
