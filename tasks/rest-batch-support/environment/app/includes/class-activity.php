<?php
/**
 * Per-list activity feed ("Bob completed 'Buy milk'").
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

/**
 * Records task changes and exposes them at GET /acme-tasks/v1/lists/{id}/activity.
 */
class Activity {

	/**
	 * Hook into the task lifecycle actions.
	 */
	public static function init() {
		add_action( 'acme_tasks_task_created', array( __CLASS__, 'on_created' ), 10, 1 );
		add_action( 'acme_tasks_task_updated', array( __CLASS__, 'on_updated' ), 10, 2 );
		add_action( 'acme_tasks_task_deleted', array( __CLASS__, 'on_deleted' ), 10, 1 );
	}

	/**
	 * Task created.
	 *
	 * @param array $task Task row.
	 */
	public static function on_created( $task ) {
		self::record( $task, 'task_created' );
	}

	/**
	 * Task updated.
	 *
	 * @param array $task Task row after the update.
	 * @param array $old  Task row before the update.
	 */
	public static function on_updated( $task, $old ) {
		if ( 'done' === $task['status'] && 'done' !== $old['status'] ) {
			self::record( $task, 'task_completed' );
		} elseif ( 'open' === $task['status'] && 'done' === $old['status'] ) {
			self::record( $task, 'task_reopened' );
		} else {
			self::record( $task, 'task_updated' );
		}
	}

	/**
	 * Task deleted.
	 *
	 * @param array $task Task row before deletion.
	 */
	public static function on_deleted( $task ) {
		self::record( $task, 'task_deleted' );
	}

	/**
	 * Insert an activity row.
	 *
	 * @param array  $task   Task row.
	 * @param string $action Action key.
	 */
	public static function record( array $task, $action ) {
		global $wpdb;
		$wpdb->insert(
			Installer::activity_table(),
			array(
				'list_id'    => (int) $task['list_id'],
				'task_id'    => (int) $task['id'],
				'user_id'    => get_current_user_id(),
				'action'     => $action,
				'summary'    => wp_html_excerpt( $task['title'], 250 ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Register GET /lists/{id}/activity.
	 */
	public static function register_routes() {
		register_rest_route(
			REST_NAMESPACE,
			'/lists/(?P<list_id>\d+)/activity',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_items' ),
				'permission_callback' => static function ( $request ) {
					if ( ! Post_Type::get_list( $request['list_id'] ) ) {
						return new \WP_Error( 'acme_tasks_list_not_found', __( 'Task list not found.', 'acme-tasks' ), array( 'status' => 404 ) );
					}
					return Access::can_view_list( $request['list_id'] ) ? true : Access::forbidden();
				},
				'args'                => array(
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 200,
					),
				),
			)
		);
	}

	/**
	 * Latest activity of a list, newest first.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_items( $request ) {
		global $wpdb;
		$table = Installer::activity_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE list_id = %d ORDER BY id DESC LIMIT %d", (int) $request['list_id'], (int) $request['per_page'] ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'      => (int) $row['id'],
				'task_id' => (int) $row['task_id'],
				'user_id' => (int) $row['user_id'],
				'action'  => $row['action'],
				'summary' => $row['summary'],
				'date'    => to_rfc3339( $row['created_at'] ),
			);
		}
		return rest_ensure_response( $out );
	}
}
