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
					'args'                => Validation::task_args( true ),
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
					'args'                => Validation::task_args( false ),
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
	 * position it is inserted there (past the end = last) and the following tasks move down.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		// All input was validated and sanitized through the route arguments. Read it from
		// the request object only: it works for JSON bodies, form posts (1.x admin screen,
		// Zapier) and requests dispatched internally (batch requests).
		$list_id = (int) $request['list_id'];
		$status  = $this->resolve_status( $request, 'open' );

		$id = Task_Repository::insert(
			array(
				'list_id'      => $list_id,
				'title'        => $request['title'],
				'notes'        => $request->has_param( 'notes' ) ? (string) $request['notes'] : '',
				'status'       => $status,
				// Appended for now; place() below puts it where it was asked for.
				'position'     => Task_Repository::next_position( $list_id ),
				'due_date'     => Validation::is_blank( $request['due_date'] ) ? null : $request['due_date'],
				'assignee_id'  => empty( $request['assignee'] ) ? 0 : (int) $request['assignee'],
				'completed_at' => 'done' === $status ? current_time( 'mysql', true ) : null,
			)
		);
		if ( ! $id ) {
			return new WP_Error( 'acme_tasks_db_error', __( 'Could not save the task.', 'acme-tasks' ), array( 'status' => 500 ) );
		}

		// Without a position the task is appended to the end of the list (positions past
		// the end are clamped by place()).
		$position = Validation::is_blank( $request['position'] ) ? PHP_INT_MAX : (int) $request['position'];
		Task_Repository::place( $list_id, $id, $position );

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
		$task = Task_Repository::get( $request['id'] );

		$data = array();
		if ( $request->has_param( 'title' ) && null !== $request['title'] ) {
			$data['title'] = $request['title'];
		}
		if ( $request->has_param( 'notes' ) && null !== $request['notes'] ) {
			$data['notes'] = (string) $request['notes'];
		}
		if ( $request->has_param( 'due_date' ) ) {
			$data['due_date'] = Validation::is_blank( $request['due_date'] ) ? null : $request['due_date'];
		}
		if ( $request->has_param( 'assignee' ) ) {
			$data['assignee_id'] = empty( $request['assignee'] ) ? 0 : (int) $request['assignee'];
		}
		$status = $this->resolve_status( $request, $task['status'] );
		if ( $status !== $task['status'] ) {
			$data['status']       = $status;
			$data['completed_at'] = 'done' === $status ? current_time( 'mysql', true ) : null;
		}

		Task_Repository::update( $task['id'], $data );
		// Moves the task when a position was sent (and normalizes 1.2-era lists either way).
		Task_Repository::place( $task['list_id'], $task['id'], Validation::is_blank( $request['position'] ) ? null : (int) $request['position'] );

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
	 * @return WP_REST_Response
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

		// Same payload the 1.2 admin-ajax handler returned; the admin screen and the app parse it.
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
	 * Resolve `status` / legacy `completed` into a status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $current Current status.
	 * @return string
	 */
	protected function resolve_status( $request, $current ) {
		if ( ! Validation::is_blank( $request['status'] ) ) {
			return $request['status'];
		}
		if ( ! Validation::is_blank( $request['completed'] ) ) {
			// Deprecated since 1.3, still sent by the admin screen and app < 3.0.
			return parse_bool( $request['completed'] ) ? 'done' : 'open';
		}
		return $current;
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
