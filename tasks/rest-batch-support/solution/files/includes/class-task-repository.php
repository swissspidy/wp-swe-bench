<?php
/**
 * Storage for tasks (custom table).
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Task storage.
 *
 * Rows are arrays with the table columns (ints cast). The order of a list is defined by
 * `position` (ascending), ties broken by `id`.
 */
class Task_Repository {

	/**
	 * Per-request cache of the tasks of a list (list ID => rows).
	 *
	 * The admin screen and the app used to fetch the same list several times while
	 * rendering, so 1.2 added this. Every write flushes the affected list: several
	 * changes can be made in one PHP request (batch requests, WP-CLI, imports).
	 *
	 * @var array<int, array>
	 */
	private static $by_list = array();

	/**
	 * Tasks of a list in display order.
	 *
	 * @param int         $list_id List ID.
	 * @param string|null $status  'open', 'done' or null for all.
	 * @return array[]
	 */
	public static function for_list( $list_id, $status = null ) {
		global $wpdb;
		$list_id = (int) $list_id;

		if ( ! isset( self::$by_list[ $list_id ] ) ) {
			$table = Installer::tasks_table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows                      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE list_id = %d ORDER BY position ASC, id ASC", $list_id ), ARRAY_A );
			self::$by_list[ $list_id ] = array_map( array( __CLASS__, 'cast' ), (array) $rows );
		}

		$rows = self::$by_list[ $list_id ];
		if ( null !== $status ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $status ) {
						return $row['status'] === $status;
					}
				)
			);
		}
		return $rows;
	}

	/**
	 * Fetch one task.
	 *
	 * @param int $id Task ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Installer::tasks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * Count tasks per status for a list.
	 *
	 * @param int $list_id List ID.
	 * @return array{total:int, open:int, done:int}
	 */
	public static function counts( $list_id ) {
		global $wpdb;
		$table = Installer::tasks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$table} WHERE list_id = %d GROUP BY status", (int) $list_id ), ARRAY_A );
		$counts = array(
			'total' => 0,
			'open'  => 0,
			'done'  => 0,
		);
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['n'];
			}
			$counts['total'] += (int) $row['n'];
		}
		return $counts;
	}

	/**
	 * Position for a task appended to the end of a list.
	 *
	 * @param int $list_id List ID.
	 * @return int
	 */
	public static function next_position( $list_id ) {
		return count( self::ordered_ids( $list_id ) );
	}

	/**
	 * Insert a task.
	 *
	 * @param array $data Column values.
	 * @return int New task ID (0 on failure).
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$data = array_merge(
			array(
				'list_id'      => 0,
				'title'        => '',
				'notes'        => '',
				'status'       => 'open',
				'position'     => 0,
				'due_date'     => null,
				'assignee_id'  => 0,
				'created_by'   => get_current_user_id(),
				'created_at'   => $now,
				'updated_at'   => $now,
				'completed_at' => null,
			),
			$data
		);
		$ok = $wpdb->insert( Installer::tasks_table(), $data );
		self::flush_cache( (int) $data['list_id'] );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update columns of a task.
	 *
	 * @param int   $id   Task ID.
	 * @param array $data Column values.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data['updated_at'] = current_time( 'mysql', true );
		$ok                 = false !== $wpdb->update( Installer::tasks_table(), $data, array( 'id' => (int) $id ) );
		self::flush_cache();
		return $ok;
	}

	/**
	 * Delete a task.
	 *
	 * @param int $id Task ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$task = self::get( $id );
		$ok   = (bool) $wpdb->delete( Installer::tasks_table(), array( 'id' => (int) $id ) );
		if ( $task ) {
			// Close the gap the task leaves behind.
			self::normalize_positions( $task['list_id'] );
		}
		return $ok;
	}

	/**
	 * Delete all tasks of a list.
	 *
	 * @param int $list_id List ID.
	 * @return int Number of deleted tasks.
	 */
	public static function delete_for_list( $list_id ) {
		global $wpdb;
		unset( self::$by_list[ (int) $list_id ] );
		return (int) $wpdb->delete( Installer::tasks_table(), array( 'list_id' => (int) $list_id ) );
	}

	/**
	 * Task IDs of a list in display order, straight from the database.
	 *
	 * @param int $list_id List ID.
	 * @return int[]
	 */
	public static function ordered_ids( $list_id ) {
		global $wpdb;
		$table = Installer::tasks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE list_id = %d ORDER BY position ASC, id ASC", (int) $list_id ) ) );
	}

	/**
	 * Put a task at a position within its list and renumber the list to 0..n-1.
	 *
	 * Positions past the end put the task last. Lists migrated from 1.2 can have gaps and
	 * duplicate positions; they are normalized on the first change.
	 *
	 * @param int      $list_id  List ID.
	 * @param int      $task_id  Task ID (must belong to the list).
	 * @param int|null $position Target position, null to keep the task where it is.
	 * @return int The final position.
	 */
	public static function place( $list_id, $task_id, $position = null ) {
		$ids     = self::ordered_ids( $list_id );
		$task_id = (int) $task_id;
		$current = array_search( $task_id, $ids, true );
		if ( null === $position ) {
			$position = false === $current ? count( $ids ) : $current;
		}
		$ids      = array_values( array_diff( $ids, array( $task_id ) ) );
		$position = max( 0, min( (int) $position, count( $ids ) ) );
		array_splice( $ids, $position, 0, array( $task_id ) );
		self::write_positions( $list_id, $ids );
		return $position;
	}

	/**
	 * Store positions 0..n-1 for the given order, touching only rows that change.
	 *
	 * @param int   $list_id List ID.
	 * @param int[] $ids     Task IDs in order.
	 */
	private static function write_positions( $list_id, array $ids ) {
		global $wpdb;
		$table = Installer::tasks_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT id, position FROM {$table} WHERE list_id = %d", (int) $list_id ), ARRAY_A );
		$current = array();
		foreach ( (array) $rows as $row ) {
			$current[ (int) $row['id'] ] = (int) $row['position'];
		}
		foreach ( array_values( $ids ) as $position => $id ) {
			if ( isset( $current[ $id ] ) && $current[ $id ] !== $position ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET position = %d WHERE id = %d", $position, $id ) );
			}
		}
		self::flush_cache( $list_id );
	}

	/**
	 * Apply an explicit order. Tasks missing from $ids keep their relative order after them.
	 *
	 * @param int   $list_id List ID.
	 * @param int[] $ids     Task IDs in the desired order.
	 */
	public static function reorder( $list_id, array $ids ) {
		$current = self::ordered_ids( $list_id );
		$ids     = array_values( array_intersect( array_unique( array_map( 'intval', $ids ) ), $current ) );
		self::write_positions( $list_id, array_merge( $ids, array_values( array_diff( $current, $ids ) ) ) );
	}

	/**
	 * Rewrite the positions of a list to 0..n-1 (keeping the current order).
	 *
	 * @param int $list_id List ID.
	 */
	public static function normalize_positions( $list_id ) {
		self::reorder( $list_id, array() );
	}

	/**
	 * Forget cached lists.
	 *
	 * @param int|null $list_id List ID or null for all.
	 */
	public static function flush_cache( $list_id = null ) {
		if ( null === $list_id ) {
			self::$by_list = array();
		} else {
			unset( self::$by_list[ (int) $list_id ] );
		}
	}

	/**
	 * Cast DB strings to proper types.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function cast( array $row ) {
		foreach ( array( 'id', 'list_id', 'position', 'assignee_id', 'created_by' ) as $key ) {
			$row[ $key ] = (int) $row[ $key ];
		}
		if ( empty( $row['due_date'] ) || '0000-00-00' === $row['due_date'] ) {
			$row['due_date'] = null;
		}
		return $row;
	}
}
