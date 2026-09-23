<?php
/**
 * View counter, stored in a custom table ({prefix}acme_related_views).
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Views storage.
 */
class Views {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_related_views';
	}

	/**
	 * Create/upgrade the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				post_id bigint(20) unsigned NOT NULL,
				views bigint(20) unsigned NOT NULL DEFAULT 0,
				last_viewed datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (post_id)
			) {$charset};"
		);
		update_option( 'acme_related_db_version', ACME_RELATED_DB_VERSION );
	}

	/**
	 * Install on the fly when the plugin was updated without re-activation.
	 */
	public function maybe_upgrade() {
		if ( (int) get_option( 'acme_related_db_version' ) < ACME_RELATED_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'manage_post_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_post_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'forget' ) );
	}

	/**
	 * View count of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get( $post_id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$views = $wpdb->get_var( $wpdb->prepare( "SELECT views FROM {$table} WHERE post_id = %d", $post_id ) );
		return (int) $views;
	}

	/**
	 * Count one view.
	 *
	 * @param int $post_id Post ID.
	 * @return int New count.
	 */
	public function increment( $post_id ) {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET views = views + 1, last_viewed = %s WHERE post_id = %d", $now, $post_id ) );
		if ( ! $updated ) {
			$wpdb->insert(
				$table,
				array(
					'post_id'     => $post_id,
					'views'       => 1,
					'last_viewed' => $now,
				),
				array( '%d', '%d', '%s' )
			);
		}
		// phpcs:enable
		/**
		 * Fires after a view was counted.
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'acme_related_view_counted', $post_id );
		return $this->get( $post_id );
	}

	/**
	 * Remove the row of a deleted post.
	 *
	 * @param int $post_id Post ID.
	 */
	public function forget( $post_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'post_id' => $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Posts list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['acme_views'] = __( 'Views', 'acme-related' );
		return $columns;
	}

	/**
	 * Posts list column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'acme_views' === $column ) {
			echo esc_html( number_format_i18n( $this->get( $post_id ) ) );
		}
	}
}
