<?php
/**
 * REST controller for tasks.
 *
 *   GET  /acme-tasks/v1/lists/{list_id}/tasks
 *   POST /acme-tasks/v1/lists/{list_id}/tasks
 *   POST /acme-tasks/v1/lists/{list_id}/reorder
 *   GET|POST|PUT|PATCH|DELETE /acme-tasks/v1/tasks/{id}
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Tasks endpoints.
 */
class Tasks_Controller extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = REST_NAMESPACE;
		$this->rest_base = 'tasks';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/lists/(?P<list_id>\d+)/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'args'                => array(
						'status' => array(
							'description' => __( 'Limit to open or done tasks.', 'acme-tasks' ),
							'type'        => 'string',
							'enum'        => array( 'open', 'done', 'all' ),
							'default'     => 'all',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'allow_batch'         => array( 'v1' => true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/lists/(?P<list_id>\d+)/reorder',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reorder' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'args'                => array(
						'order' => array(
							'description' => __( 'Task IDs in the new order.', 'acme-tasks' ),
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'task_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'task_permissions_check' ),
					'allow_batch'         => array( 'v1' => true ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'task_permissions_check' ),
					'allow_batch'         => array( 'v1' => true ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * The list must exist and the user must have access to it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function list_permissions_check( $request ) {
		if ( ! Post_Type::get_list( $request['list_id'] ) ) {
			return new WP_Error( 'acme_tasks_list_not_found', __( 'Task list not found.', 'acme-tasks' ), array( 'status' => 404 ) );
		}
		return Access::can_view_list( $request['list_id'] ) ? true : Access::forbidden();
	}

	/**
	 * The task must exist and the user must have access to its list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function task_permissions_check( $request ) {
		$task = Task_Repository::get( $request['id'] );
		if ( ! $task || ! Post_Type::get_list( $task['list_id'] ) ) {
			return new WP_Error( 'acme_tasks_task_not_found', __( 'Task not found.', 'acme-tasks' ), array( 'status' => 404 ) );
		}
		return Access::can_view_list( $task['list_id'] ) ? true : Access::forbidden();
	}

	/**
	 * Tasks of a list in display order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$status = 'all' === $request['status'] ? null : $request['status'];
		$data   = array();
		foreach ( Task_Repository::for_list( $request['list_id'], $status ) as $task ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $task, $request ) );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * One task.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->prepare_item_for_response( Task_Repository::get( $request['id'] ), $request );
	}

	/**
	 * Create a task. Without `position` it is appended to the end of the list; with a
	 * position it is inserted there and the following tasks move down.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$list_id = (int) $request['list_id'];
		$params  = $request->get_params();
		$params = (array) $params;

		$error = $this->validate_task_input( $params, $list_id, true );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$status = $this->resolve_status( $params, 'open' );
		if ( isset( $params['position'] ) && '' !== $params['position'] ) {
			$position = (int) $params['position'];
			Task_Repository::make_room( $list_id, $position );
		} else {
			$position = Task_Repository::next_position( $list_id );
		}

		$id = Task_Repository::insert(
			array(
				'list_id'      => $list_id,
				'title'        => $this->clean_title( $params['title'] ),
				'notes'        => isset( $params['notes'] ) ? sanitize_textarea_field( (string) $params['notes'] ) : '',
				'status'       => $status,
				'position'     => $position,
				'due_date'     => empty( $params['due_date'] ) ? null : $params['due_date'],
				'assignee_id'  => empty( $params['assignee'] ) ? 0 : (int) $params['assignee'],
				'completed_at' => 'done' === $status ? current_time( 'mysql', true ) : null,
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'acme_tasks_db_error', __( 'Could not save the task.', 'acme-tasks' ), array( 'status' => 500 ) );
		}

		$task = Task_Repository::get( $id );

		/**
		 * Fires after a task was created.
		 *
		 * @since 1.0.0
		 *
		 * @param array                $task    Task row.
		 * @param WP_REST_Request|null $request Request (null when created outside the API).
		 */
		do_action( 'acme_tasks_task_created', $task, $request );

		$response = $this->prepare_item_for_response( $task, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) );
		return $response;
	}

	/**
	 * Update a task. `position` moves it within its list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$task   = Task_Repository::get( $request['id'] );
		$params  = $request->get_params();
		$params = (array) $params;

		$error = $this->validate_task_input( $params, $task['list_id'], false );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$data = array();
		if ( array_key_exists( 'title', $params ) ) {
			$data['title'] = $this->clean_title( $params['title'] );
		}
		if ( array_key_exists( 'notes', $params ) ) {
			$data['notes'] = sanitize_textarea_field( (string) $params['notes'] );
		}
		if ( array_key_exists( 'due_date', $params ) ) {
			$data['due_date'] = empty( $params['due_date'] ) ? null : $params['due_date'];
		}
		if ( array_key_exists( 'assignee', $params ) ) {
			$data['assignee_id'] = empty( $params['assignee'] ) ? 0 : (int) $params['assignee'];
		}
		$status = $this->resolve_status( $params, $task['status'] );
		if ( $status !== $task['status'] ) {
			$data['status']       = $status;
			$data['completed_at'] = 'done' === $status ? current_time( 'mysql', true ) : null;
		}

		if ( isset( $params['position'] ) && '' !== $params['position'] && (int) $params['position'] !== $task['position'] ) {
			Task_Repository::move( $task, (int) $params['position'] );
		}
		Task_Repository::update( $task['id'], $data );

		$updated = Task_Repository::get( $task['id'] );

		/**
		 * Fires after a task was updated.
		 *
		 * @since 1.0.0
		 * @since 1.4.0 The previous state is passed.
		 *
		 * @param array                $updated Task row after the update.
		 * @param array                $task    Task row before the update.
		 * @param WP_REST_Request|null $request Request.
		 */
		do_action( 'acme_tasks_task_updated', $updated, $task, $request );

		return $this->prepare_item_for_response( $updated, $request );
	}

	/**
	 * Delete a task.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function delete_item( $request ) {
		$task     = Task_Repository::get( $request['id'] );
		$previous = $this->prepare_item_for_response( $task, $request )->get_data();

		Task_Repository::delete( $task['id'] );

		/**
		 * Fires after a task was deleted.
		 *
		 * @since 1.0.0
		 *
		 * @param array                $task    Task row before deletion.
		 * @param WP_REST_Request|null $request Request.
		 */
		do_action( 'acme_tasks_task_deleted', $task, $request );

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Apply an explicit order to a list (used by "Sort by due date" in wp-admin).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function reorder( $request ) {
		Task_Repository::reorder( $request['list_id'], (array) $request['order'] );
		return $this->get_items( $request );
	}

	/**
	 * Validate task input. Returns a WP_Error (status 400) for the first problem found.
	 *
	 * @param array $params   Input.
	 * @param int   $list_id  List the task belongs to.
	 * @param bool  $creating Whether a title is required.
	 * @return true|WP_Error
	 */
	protected function validate_task_input( array $params, $list_id, $creating ) {
		if ( $creating || array_key_exists( 'title', $params ) ) {
			$title = isset( $params['title'] ) && is_scalar( $params['title'] ) ? $this->clean_title( $params['title'] ) : '';
			if ( '' === $title ) {
				return new WP_Error( 'acme_tasks_invalid_title', __( 'A task needs a title.', 'acme-tasks' ), array( 'status' => 400 ) );
			}
			if ( mb_strlen( $title ) > MAX_TITLE_LEN ) {
				return new WP_Error( 'acme_tasks_invalid_title', __( 'The title is too long.', 'acme-tasks' ), array( 'status' => 400 ) );
			}
		}
		if ( isset( $params['notes'] ) && ! is_scalar( $params['notes'] ) ) {
			return new WP_Error( 'acme_tasks_invalid_notes', __( 'Notes must be text.', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		if ( isset( $params['status'] ) && ! in_array( $params['status'], TASK_STATUSES, true ) ) {
			return new WP_Error( 'acme_tasks_invalid_status', __( 'Status must be "open" or "done".', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		if ( isset( $params['completed'] ) && null === parse_bool( $params['completed'] ) ) {
			return new WP_Error( 'acme_tasks_invalid_status', __( 'Completed must be a boolean.', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $params['due_date'] ) && ! is_valid_date( $params['due_date'] ) ) {
			return new WP_Error( 'acme_tasks_invalid_due_date', __( 'Due dates must be valid dates in YYYY-MM-DD format.', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		if ( ! empty( $params['assignee'] ) ) {
			if ( ! is_non_negative_int( $params['assignee'] ) || ! can_be_assigned( (int) $params['assignee'], $list_id ) ) {
				return new WP_Error( 'acme_tasks_invalid_assignee', __( 'Tasks can only be assigned to people who have access to the list.', 'acme-tasks' ), array( 'status' => 400 ) );
			}
		}
		if ( isset( $params['position'] ) && '' !== $params['position'] && ! is_non_negative_int( $params['position'] ) ) {
			return new WP_Error( 'acme_tasks_invalid_position', __( 'Position must be a non-negative integer.', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Resolve `status` / legacy `completed` into a status.
	 *
	 * @param array  $params  Input.
	 * @param string $current Current status.
	 * @return string
	 */
	protected function resolve_status( array $params, $current ) {
		if ( isset( $params['status'] ) ) {
			return $params['status'];
		}
		if ( isset( $params['completed'] ) ) {
			// Deprecated since 1.3, still sent by the admin screen and app < 3.0.
			return parse_bool( $params['completed'] ) ? 'done' : 'open';
		}
		return $current;
	}

	/**
	 * Clean a title.
	 *
	 * @param mixed $title Raw title.
	 * @return string
	 */
	protected function clean_title( $title ) {
		return trim( sanitize_text_field( (string) $title ) );
	}

	/**
	 * Shape a task for the API.
	 *
	 * @param array           $task    Task row.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $task, $request ) {
		$data = array(
			'id'           => (int) $task['id'],
			'list_id'      => (int) $task['list_id'],
			'title'        => $task['title'],
			'notes'        => $task['notes'],
			'status'       => $task['status'],
			'completed'    => 'done' === $task['status'],
			'position'     => (int) $task['position'],
			'due_date'     => $task['due_date'],
			'assignee'     => (int) $task['assignee_id'],
			'created_by'   => (int) $task['created_by'],
			'created_at'   => to_rfc3339( $task['created_at'] ),
			'updated_at'   => to_rfc3339( $task['updated_at'] ),
			'completed_at' => to_rfc3339( $task['completed_at'] ),
		);

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self' => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $task['id'] ) ) ),
				'list' => array( 'href' => rest_url( sprintf( '%s/lists/%d', $this->namespace, $task['list_id'] ) ) ),
			)
		);

		/**
		 * Filters a task as returned by the API.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_REST_Response $response Response.
		 * @param array            $task     Task row.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'acme_tasks_rest_prepare_task', $response, $task, $request );
	}

	/**
	 * JSON schema of a task.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'acme-task',
			'type'       => 'object',
			'properties' => array(
				'id'           => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'list_id'      => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'title'        => array(
					'type'      => 'string',
					'maxLength' => MAX_TITLE_LEN,
				),
				'notes'        => array( 'type' => 'string' ),
				'status'       => array(
					'type' => 'string',
					'enum' => TASK_STATUSES,
				),
				'completed'    => array(
					'type'        => 'boolean',
					'description' => __( 'Deprecated: use status.', 'acme-tasks' ),
				),
				'position'     => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'due_date'     => array( 'type' => array( 'string', 'null' ) ),
				'assignee'     => array( 'type' => 'integer' ),
				'created_by'   => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'created_at'   => array(
					'type'     => 'string',
					'format'   => 'date-time',
					'readonly' => true,
				),
				'updated_at'   => array(
					'type'     => 'string',
					'format'   => 'date-time',
					'readonly' => true,
				),
				'completed_at' => array(
					'type'     => array( 'string', 'null' ),
					'readonly' => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
