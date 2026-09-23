<?php
/**
 * Storage of redirect rules ({prefix}acme_redirects).
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Rule repository.
 *
 * All writes go through this class: it keeps the cached rule set used by the
 * front end in sync and fires the hooks other code relies on (our CDN purge
 * integration listens to `acme_redirects_rules_changed`).
 */
class Rule_Repository {

	/**
	 * Transient holding the enabled rules for the front end.
	 */
	const CACHE_KEY = 'acme_redirects_rules';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'acme_redirects';
	}

	/**
	 * Finds a rule.
	 *
	 * @param int $id Rule ID.
	 * @return Rule|null
	 */
	public function find( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ? Rule::from_row( $row ) : null;
	}

	/**
	 * Finds the rule using a source (sources are unique per match type).
	 *
	 * Handles rows stored by 1.x (no leading slash, empty match type).
	 *
	 * @param string $source     Source.
	 * @param string $match_type Match type.
	 * @return Rule|null
	 */
	public function find_by_source( $source, $match_type = 'exact' ) {
		global $wpdb;
		$table   = self::table();
		$sources = array( $source );
		if ( 'regex' !== $match_type ) {
			$sources[] = ltrim( $source, '/' );
		}
		$types = 'exact' === $match_type ? array( 'exact', '' ) : array( $match_type );

		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
			"SELECT * FROM {$table} WHERE source IN (" . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ') AND ( match_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')' . ( 'exact' === $match_type ? ' OR match_type IS NULL' : '' ) . ') ORDER BY id ASC LIMIT 1',
			array_merge( $sources, $types )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $sql );
		return $row ? Rule::from_row( $row ) : null;
	}

	/**
	 * Queries rules for the admin list.
	 *
	 * @param array $args {
	 *     @type string $search     Substring of source or target.
	 *     @type string $match_type Match type.
	 *     @type int    $status     Status code.
	 *     @type string $orderby    priority|source|hits|last_hit|id.
	 *     @type string $order      ASC|DESC.
	 *     @type int    $per_page   Page size (0 = all).
	 *     @type int    $page       Page (1-based).
	 * }
	 * @return array{items: Rule[], total: int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;
		$args  = wp_parse_args(
			$args,
			array(
				'search'     => '',
				'match_type' => '',
				'status'     => 0,
				'orderby'    => 'priority',
				'order'      => 'ASC',
				'per_page'   => 0,
				'page'       => 1,
			)
		);
		$table = self::table();
		$where = array( '1=1' );
		$vals  = array();

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '( source LIKE %s OR target LIKE %s )';
			$vals[]  = $like;
			$vals[]  = $like;
		}
		if ( 'exact' === $args['match_type'] ) {
			$where[] = "( match_type = 'exact' OR match_type = '' OR match_type IS NULL )";
		} elseif ( '' !== $args['match_type'] ) {
			$where[] = 'match_type = %s';
			$vals[]  = $args['match_type'];
		}
		if ( $args['status'] ) {
			if ( 301 === (int) $args['status'] ) {
				$where[] = '( status_code = 301 OR status_code = 0 OR status_code IS NULL )';
			} else {
				$where[] = 'status_code = %d';
				$vals[]  = (int) $args['status'];
			}
		}

		$orderby_map = array(
			'priority' => 'COALESCE(priority, 10) %s, id ASC',
			'source'   => 'source %s, id ASC',
			'hits'     => 'hits %s, id ASC',
			'last_hit' => 'last_hit %s, id ASC',
			'id'       => 'id %s',
		);
		$order       = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$orderby     = sprintf( $orderby_map[ $args['orderby'] ] ?? $orderby_map['priority'], $order );

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby}";
		if ( $args['per_page'] > 0 ) {
			$list_sql .= sprintf( ' LIMIT %d OFFSET %d', (int) $args['per_page'], ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'] );
		}
		if ( $vals ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, $vals );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$list_sql = $wpdb->prepare( $list_sql, $vals );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $count_sql );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $list_sql );

		return array(
			'items' => array_map( array( Rule::class, 'from_row' ), $rows ),
			'total' => $total,
		);
	}

	/**
	 * Enabled rules in matching order (priority, then ID), cached for the front end.
	 *
	 * @return Rule[]
	 */
	public function get_enabled_rules() {
		$rows = get_transient( self::CACHE_KEY );
		if ( ! is_array( $rows ) ) {
			global $wpdb;
			$table = self::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( "SELECT id, source, target, match_type, status_code, priority, enabled FROM {$table} WHERE enabled = 1 OR enabled IS NULL ORDER BY COALESCE(priority, 10) ASC, id ASC", ARRAY_A );
			set_transient( self::CACHE_KEY, $rows, DAY_IN_SECONDS );
		}
		return array_map( array( Rule::class, 'from_row' ), $rows );
	}

	/**
	 * Inserts a rule.
	 *
	 * @param Rule $rule Rule (validated).
	 * @return int|WP_Error New ID.
	 */
	public function insert( Rule $rule ) {
		global $wpdb;
		$now          = current_time( 'mysql', true );
		$row          = $rule->to_row();
		$row['hits']  = 0;
		$row['created_at'] = $now;
		$row['updated_at'] = $now;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $wpdb->insert( self::table(), $row ) ) {
			return new WP_Error( 'acme_redirects_db_error', __( 'Could not save the redirect.', 'acme-redirects' ) );
		}
		$rule->id = (int) $wpdb->insert_id;
		$this->changed( 'insert', $rule );
		return $rule->id;
	}

	/**
	 * Updates a rule (hits are kept).
	 *
	 * @param Rule $rule Rule (validated, with ID).
	 * @return true|WP_Error
	 */
	public function update( Rule $rule ) {
		global $wpdb;
		$row               = $rule->to_row();
		$row['updated_at'] = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( false === $wpdb->update( self::table(), $row, array( 'id' => $rule->id ) ) ) {
			return new WP_Error( 'acme_redirects_db_error', __( 'Could not save the redirect.', 'acme-redirects' ) );
		}
		$this->changed( 'update', $rule );
		return true;
	}

	/**
	 * Deletes a rule.
	 *
	 * @param int $id Rule ID.
	 * @return bool Whether a rule was deleted.
	 */
	public function delete( $id ) {
		global $wpdb;
		$rule = $this->find( $id );
		if ( ! $rule ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
		$this->changed( 'delete', $rule );
		return true;
	}

	/**
	 * Counts a hit. Does not invalidate the rule cache (hits aren't cached).
	 *
	 * @param int $id Rule ID.
	 */
	public function record_hit( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1, last_hit = %s WHERE id = %d", current_time( 'mysql', true ), $id ) );
	}

	/**
	 * Resets the hit counter of a rule.
	 *
	 * @param int $id Rule ID.
	 */
	public function reset_hits( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			self::table(),
			array(
				'hits'     => 0,
				'last_hit' => null,
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Drops the cached rule set.
	 */
	public function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Called after every write.
	 *
	 * @param string $action insert|update|delete.
	 * @param Rule   $rule   Rule.
	 */
	private function changed( $action, Rule $rule ) {
		$this->flush_cache();

		/**
		 * Fires after a redirect rule was created, updated or deleted.
		 *
		 * @param string $action insert|update|delete.
		 * @param Rule   $rule   The rule.
		 */
		do_action( 'acme_redirects_rules_changed', $action, $rule );
	}
}
