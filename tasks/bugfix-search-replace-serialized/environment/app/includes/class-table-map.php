<?php
/**
 * Which tables and columns a run walks.
 *
 * We don't introspect the schema (hosts with SQLite or restricted database users don't allow
 * it); core tables are mapped here and plugins add their own tables with the
 * `acme_migrate_tables` filter.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Table map.
 */
class Table_Map {

	/**
	 * Options that belong to this plugin and are never rewritten.
	 */
	const OWN_OPTIONS = array( 'acme_migrate_history', 'acme_migrate_settings' );

	/**
	 * Tables => primary key + text columns.
	 *
	 * @return array<string, array{primary: string, columns: string[]}>
	 */
	public static function get() {
		global $wpdb;

		$tables = array(
			$wpdb->posts              => array(
				'primary' => 'ID',
				'columns' => array( 'post_title', 'post_content', 'post_excerpt', 'post_content_filtered', 'guid' ),
			),
			$wpdb->postmeta           => array(
				'primary' => 'meta_id',
				'columns' => array( 'meta_value' ),
			),
			$wpdb->options            => array(
				'primary' => 'option_id',
				'columns' => array( 'option_value' ),
			),
			$wpdb->comments           => array(
				'primary' => 'comment_ID',
				'columns' => array( 'comment_author_url', 'comment_content' ),
			),
			$wpdb->commentmeta        => array(
				'primary' => 'meta_id',
				'columns' => array( 'meta_value' ),
			),
			$wpdb->term_taxonomy      => array(
				'primary' => 'term_taxonomy_id',
				'columns' => array( 'description' ),
			),
			$wpdb->termmeta           => array(
				'primary' => 'meta_id',
				'columns' => array( 'meta_value' ),
			),
			$wpdb->users              => array(
				'primary' => 'ID',
				'columns' => array( 'user_url' ),
			),
			$wpdb->usermeta           => array(
				'primary' => 'umeta_id',
				'columns' => array( 'meta_value' ),
			),
			$wpdb->links              => array(
				'primary' => 'link_id',
				'columns' => array( 'link_url', 'link_image', 'link_rss' ),
			),
		);

		/**
		 * Filters the tables a search & replace run walks.
		 *
		 * @since 1.0.0
		 *
		 * @param array $tables Table name (with prefix) => array( 'primary' => primary key column, 'columns' => text columns ).
		 */
		$tables = apply_filters( 'acme_migrate_tables', $tables );

		foreach ( $tables as $name => $table ) {
			if ( empty( $table['primary'] ) || empty( $table['columns'] ) || ! is_array( $table['columns'] ) ) {
				unset( $tables[ $name ] );
			}
		}

		return $tables;
	}

	/**
	 * Extra WHERE clause that excludes rows we never touch.
	 *
	 * @param string $table Table name.
	 * @return string SQL (already escaped) starting with " AND", or an empty string.
	 */
	public static function exclusions_sql( $table ) {
		global $wpdb;

		if ( $table === $wpdb->options ) {
			$names = array_map( 'esc_sql', self::OWN_OPTIONS );
			return " AND option_name NOT IN ('" . implode( "','", $names ) . "')";
		}

		return '';
	}
}
