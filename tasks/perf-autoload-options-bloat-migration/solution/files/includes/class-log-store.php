<?php
/**
 * Log storage: the `{prefix}acme_activity_log` table.
 *
 * Until 2.x the log lived in autoloaded options (see Migration). The legacy
 * formats are still understood by Log_Store::normalize(), which the migration
 * uses to convert them:
 *
 * - 2.x (compact keys): array( 'id', 't' (unix), 'u', 'a', 'ot', 'oid', 'm', 'ip', 'c' (array) )
 * - 1.x (before 2.0):   array( 'id', 'timestamp' ('Y-m-d H:i:s', UTC), 'user', 'event',
 *                              'post_id', 'message', 'details' (JSON string) )
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Table-backed log store.
 */
class Log_Store {

	/**
	 * Legacy (2.x) current chunk option.
	 */
	const OPTION = 'acme_activity_log';

	/**
	 * Legacy archive chunks: prefix + 1..n.
	 */
	const ARCHIVE_PREFIX = 'acme_activity_log_archive_';

	/**
	 * Legacy number of archive chunks.
	 */
	const ARCHIVE_COUNT_OPTION = 'acme_activity_archive_count';

	/**
	 * Legacy last ID handed out.
	 */
	const LAST_ID_OPTION = 'acme_activity_last_id';

	/**
	 * 1.x event names => 2.x actions.
	 *
	 * @var array<string, string>
	 */
	const LEGACY_EVENTS = array(
		'login'      => 'user_login',
		'publish'    => 'post_published',
		'trash'      => 'post_trashed',
		'register'   => 'user_registered',
		'activate'   => 'plugin_activated',
		'deactivate' => 'plugin_deactivated',
	);

	/**
	 * Columns in insert order.
	 *
	 * @var string[]
	 */
	const COLUMNS = array( 'id', 'logged_at', 'user_id', 'action', 'object_type', 'object_id', 'message', 'ip', 'context' );

	/**
	 * Add an entry. Returns the new entry ID.
	 *
	 * @param array $entry Normalized entry (without id).
	 * @return int|false
	 */
	public function insert( array $entry ) {
		global $wpdb;
		$row = self::to_row( $entry );
		unset( $row['id'] );

		$ok = $wpdb->insert( Schema::table(), $row, array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ) );
		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Insert entries that keep their existing IDs (migration). Entries whose ID
	 * already exists in the table are skipped, so this can safely be repeated.
	 *
	 * @param array[] $entries Normalized entries with IDs.
	 * @return int Number of rows inserted.
	 */
	public function import( array $entries ) {
		global $wpdb;
		$table   = Schema::table();
		$entries = array_filter(
			$entries,
			static function ( $e ) {
				return ! empty( $e['id'] );
			}
		);
		if ( ! $entries ) {
			return 0;
		}

		// Deduplicate within the batch (2.1.0 wrote some entries twice).
		$by_id = array();
		foreach ( $entries as $entry ) {
			if ( ! isset( $by_id[ (int) $entry['id'] ] ) ) {
				$by_id[ (int) $entry['id'] ] = $entry;
			}
		}

		$ids      = array_keys( $by_id );
		$in       = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$existing = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		foreach ( $existing as $id ) {
			unset( $by_id[ (int) $id ] );
		}
		if ( ! $by_id ) {
			return 0;
		}

		$placeholders = array();
		$values       = array();
		foreach ( $by_id as $entry ) {
			$row            = self::to_row( $entry );
			$placeholders[] = '(%d, %s, %d, %s, %s, %d, %s, %s, %s)';
			foreach ( self::COLUMNS as $column ) {
				$values[] = $row[ $column ];
			}
		}

		$sql = "INSERT INTO {$table} (" . implode( ', ', self::COLUMNS ) . ') VALUES ' . implode( ', ', $placeholders );
		$wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery

		return count( $by_id );
	}

	/**
	 * Find one entry.
	 *
	 * @param int $id Entry ID.
	 * @return array|null
	 */
	public function get( $id ) {
		global $wpdb;
		$table = Schema::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		return $row ? self::from_row( $row ) : null;
	}

	/**
	 * Query entries.
	 *
	 * @param array $args          See acme_activity_get_entries().
	 * @param bool  $include_total Also count all matches.
	 * @return array{entries: array[], total: int}
	 */
	public function query( array $args, $include_total = true ) {
		global $wpdb;
		$args  = self::parse_query_args( $args );
		$table = Schema::table();
		$where = self::where( $args );
		$order = 'ASC' === $args['order'] ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY logged_at {$order}, id {$order}";
		if ( $args['per_page'] > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['per_page'], ( $args['page'] - 1 ) * $args['per_page'] );
		}

		$rows    = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$entries = array_map( array( __CLASS__, 'from_row' ), (array) $rows );

		$total = 0;
		if ( $include_total ) {
			if ( $args['per_page'] <= 0 ) {
				$total = count( $entries );
			} elseif ( 1 === $args['page'] && count( $entries ) < $args['per_page'] ) {
				$total = count( $entries );
			} else {
				$total = $this->count( $args );
			}
		}

		return array(
			'entries' => $entries,
			'total'   => $total,
		);
	}

	/**
	 * Count matching entries.
	 *
	 * @param array $args Query args.
	 * @return int
	 */
	public function count( array $args ) {
		global $wpdb;
		$args  = self::parse_query_args( $args );
		$table = Schema::table();
		$where = self::where( $args );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * SQL WHERE clause for parsed args (prepared).
	 *
	 * @param array $args Parsed args.
	 * @return string
	 */
	private static function where( array $args ) {
		global $wpdb;
		$clauses = array();
		$params  = array();
		if ( $args['action'] ) {
			$clauses[] = 'action IN (' . implode( ', ', array_fill( 0, count( $args['action'] ), '%s' ) ) . ')';
			$params    = array_merge( $params, $args['action'] );
		}
		if ( $args['user_id'] ) {
			$clauses[] = 'user_id = %d';
			$params[]  = $args['user_id'];
		}
		if ( '' !== $args['object_type'] ) {
			$clauses[] = 'object_type = %s';
			$params[]  = $args['object_type'];
		}
		if ( $args['object_id'] ) {
			$clauses[] = 'object_id = %d';
			$params[]  = $args['object_id'];
		}
		if ( $args['since'] ) {
			$clauses[] = 'logged_at >= %s';
			$params[]  = gmdate( 'Y-m-d H:i:s', $args['since'] );
		}
		if ( $args['until'] ) {
			$clauses[] = 'logged_at <= %s';
			$params[]  = gmdate( 'Y-m-d H:i:s', $args['until'] );
		}
		if ( '' !== $args['search'] ) {
			$clauses[] = 'message LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( ! $clauses ) {
			return '1=1';
		}
		return $wpdb->prepare( implode( ' AND ', $clauses ), $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Distinct actions present in the log (for the admin filter).
	 *
	 * @return string[]
	 */
	public function distinct_actions() {
		global $wpdb;
		$table = Schema::table();
		return array_map( 'strval', $wpdb->get_col( "SELECT DISTINCT action FROM {$table} ORDER BY action ASC" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Delete entries by ID.
	 *
	 * @param int[] $ids Entry IDs.
	 * @return int Number of entries deleted.
	 */
	public function delete( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$table = Schema::table();
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Delete entries logged before a point in time.
	 *
	 * @param int $before Unix timestamp (exclusive).
	 * @return int Number of entries deleted.
	 */
	public function delete_older_than( $before ) {
		global $wpdb;
		$table = Schema::table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE logged_at < %s", gmdate( 'Y-m-d H:i:s', (int) $before ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Normalize query args.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	public static function parse_query_args( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'action'      => '',
				'user_id'     => 0,
				'object_type' => '',
				'object_id'   => 0,
				'since'       => 0,
				'until'       => 0,
				'search'      => '',
				'order'       => 'DESC',
				'per_page'    => 20,
				'page'        => 1,
			)
		);

		$args['action']      = array_values( array_filter( array_map( 'strval', (array) $args['action'] ) ) );
		$args['user_id']     = (int) $args['user_id'];
		$args['object_type'] = (string) $args['object_type'];
		$args['object_id']   = (int) $args['object_id'];
		$args['since']       = (int) $args['since'];
		$args['until']       = (int) $args['until'];
		$args['search']      = trim( (string) $args['search'] );
		$args['order']       = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$args['per_page']    = (int) $args['per_page'];
		$args['page']        = max( 1, (int) $args['page'] );
		return $args;
	}

	/**
	 * Normalized entry => table row.
	 *
	 * @param array $entry Normalized entry.
	 * @return array
	 */
	public static function to_row( array $entry ) {
		return array(
			'id'          => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
			'logged_at'   => gmdate( 'Y-m-d H:i:s', (int) $entry['time'] ),
			'user_id'     => max( 0, (int) $entry['user_id'] ),
			'action'      => (string) $entry['action'],
			'object_type' => (string) $entry['object_type'],
			'object_id'   => max( 0, (int) $entry['object_id'] ),
			'message'     => (string) $entry['message'],
			'ip'          => (string) $entry['ip'],
			'context'     => wp_json_encode( (array) $entry['context'] ),
		);
	}

	/**
	 * Table row => normalized entry.
	 *
	 * @param array $row Row.
	 * @return array{id:int, time:int, user_id:int, action:string, object_type:string, object_id:int, message:string, ip:string, context:array}
	 */
	public static function from_row( array $row ) {
		$context = isset( $row['context'] ) && '' !== $row['context'] ? json_decode( $row['context'], true ) : array();
		$time    = strtotime( $row['logged_at'] . ' UTC' );
		return array(
			'id'          => (int) $row['id'],
			'time'        => $time ? (int) $time : 0,
			'user_id'     => (int) $row['user_id'],
			'action'      => (string) $row['action'],
			'object_type' => (string) $row['object_type'],
			'object_id'   => (int) $row['object_id'],
			'message'     => (string) $row['message'],
			'ip'          => (string) $row['ip'],
			'context'     => is_array( $context ) ? $context : array(),
		);
	}

	/**
	 * Normalize a stored legacy entry (any format) to the public shape.
	 *
	 * @param mixed $raw Stored entry.
	 * @return array{id:int, time:int, user_id:int, action:string, object_type:string, object_id:int, message:string, ip:string, context:array}
	 */
	public static function normalize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		if ( isset( $raw['event'] ) || isset( $raw['timestamp'] ) ) {
			// 1.x format.
			$event   = isset( $raw['event'] ) ? (string) $raw['event'] : '';
			$post_id = isset( $raw['post_id'] ) ? (int) $raw['post_id'] : 0;
			$details = isset( $raw['details'] ) ? json_decode( (string) $raw['details'], true ) : array();
			$time    = isset( $raw['timestamp'] ) ? strtotime( $raw['timestamp'] . ' UTC' ) : 0;

			return array(
				'id'          => isset( $raw['id'] ) ? (int) $raw['id'] : 0,
				'time'        => $time ? (int) $time : 0,
				'user_id'     => isset( $raw['user'] ) ? (int) $raw['user'] : 0,
				'action'      => isset( self::LEGACY_EVENTS[ $event ] ) ? self::LEGACY_EVENTS[ $event ] : $event,
				'object_type' => $post_id ? 'post' : '',
				'object_id'   => $post_id,
				'message'     => isset( $raw['message'] ) ? (string) $raw['message'] : '',
				'ip'          => '',
				'context'     => is_array( $details ) ? $details : array(),
			);
		}

		return array(
			'id'          => isset( $raw['id'] ) ? (int) $raw['id'] : 0,
			'time'        => isset( $raw['t'] ) ? (int) $raw['t'] : 0,
			'user_id'     => isset( $raw['u'] ) ? (int) $raw['u'] : 0,
			'action'      => isset( $raw['a'] ) ? (string) $raw['a'] : '',
			'object_type' => isset( $raw['ot'] ) ? (string) $raw['ot'] : '',
			'object_id'   => isset( $raw['oid'] ) ? (int) $raw['oid'] : 0,
			'message'     => isset( $raw['m'] ) ? (string) $raw['m'] : '',
			'ip'          => isset( $raw['ip'] ) ? (string) $raw['ip'] : '',
			'context'     => isset( $raw['c'] ) && is_array( $raw['c'] ) ? $raw['c'] : array(),
		);
	}
}
