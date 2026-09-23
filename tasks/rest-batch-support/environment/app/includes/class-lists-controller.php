<?php
/**
 * REST controller for task lists: /acme-tasks/v1/lists.
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
 * Task lists endpoints.
 */
class Lists_Controller extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = REST_NAMESPACE;
		$this->rest_base = 'lists';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'archived' => array(
							'description' => __( 'Include archived lists.', 'acme-tasks' ),
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Any user who can use task lists may list the lists they can see.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return Access::can_use() ? true : Access::forbidden();
	}

	/**
	 * Lists visible to the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::NAME,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$data = array();
		foreach ( $posts as $post ) {
			if ( ! Access::can_view_list( $post->ID ) ) {
				continue;
			}
			if ( ! $request['archived'] && get_post_meta( $post->ID, Post_Type::META_ARCHIVE, true ) ) {
				continue;
			}
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		return rest_ensure_response( $data );
	}

	/**
	 * Creating lists requires the edit_posts capability.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return Access::can_use() ? true : Access::forbidden( __( 'Sorry, you are not allowed to create task lists.', 'acme-tasks' ) );
	}

	/**
	 * Create a list owned by the current user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$error = $this->validate_list_input( (array) $params, true );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$list_id = wp_insert_post(
			array(
				'post_type'   => Post_Type::NAME,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $params['title'] ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $list_id ) ) {
			return $list_id;
		}

		update_post_meta( $list_id, Post_Type::META_COLOR, sanitize_color( $params['color'] ?? DEFAULT_COLOR ) );
		Post_Type::set_members( $list_id, (array) ( $params['members'] ?? array() ) );

		$post = get_post( $list_id );

		/**
		 * Fires after a task list was created through the API.
		 *
		 * @since 1.1.0
		 *
		 * @param \WP_Post        $post    List post.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'acme_tasks_list_created', $post, $request );

		$response = $this->prepare_item_for_response( $post, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $list_id ) ) );
		return $response;
	}

	/**
	 * Viewing a list requires access to it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! Post_Type::get_list( $request['id'] ) ) {
			return $this->not_found();
		}
		return Access::can_view_list( $request['id'] ) ? true : Access::forbidden();
	}

	/**
	 * One list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->prepare_item_for_response( Post_Type::get_list( $request['id'] ), $request );
	}

	/**
	 * Changing/deleting a list requires being its owner (or an editor).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! Post_Type::get_list( $request['id'] ) ) {
			return $this->not_found();
		}
		return Access::can_manage_list( $request['id'] ) ? true : Access::forbidden( __( 'Only the owner can change this list.', 'acme-tasks' ) );
	}

	/**
	 * Update title, color, members or the archived flag.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$list_id = (int) $request['id'];
		$params  = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}
		$params = (array) $params;

		$error = $this->validate_list_input( $params, false );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		if ( isset( $params['title'] ) ) {
			wp_update_post(
				array(
					'ID'         => $list_id,
					'post_title' => sanitize_text_field( $params['title'] ),
				)
			);
		}
		if ( isset( $params['color'] ) ) {
			update_post_meta( $list_id, Post_Type::META_COLOR, sanitize_color( $params['color'] ) );
		}
		if ( isset( $params['members'] ) ) {
			Post_Type::set_members( $list_id, (array) $params['members'] );
		}
		if ( isset( $params['archived'] ) ) {
			update_post_meta( $list_id, Post_Type::META_ARCHIVE, parse_bool( $params['archived'] ) ? 1 : 0 );
		}

		$post = get_post( $list_id );

		/**
		 * Fires after a task list was updated through the API.
		 *
		 * @since 1.1.0
		 *
		 * @param \WP_Post        $post    List post.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'acme_tasks_list_updated', $post, $request );

		return $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Delete a list. Without `force` the list is moved to the trash (tasks are kept so
	 * it can be restored from wp-admin); with `?force=true` the list and all its tasks
	 * are deleted permanently.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$list_id = (int) $request['id'];
		$post    = get_post( $list_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- REST request, cookie auth is nonce-checked by core.
		$force = isset( $_GET['force'] ) && parse_bool( wp_unslash( $_GET['force'] ) );

		if ( ! $force ) {
			wp_trash_post( $list_id );
			return $this->prepare_item_for_response( get_post( $list_id ), $request );
		}

		$previous = $this->prepare_item_for_response( $post, $request )->get_data();
		$tasks    = Task_Repository::for_list( $list_id );
		Task_Repository::delete_for_list( $list_id );
		wp_delete_post( $list_id, true );

		/**
		 * Fires after a task list and its tasks were deleted permanently.
		 *
		 * @since 1.1.0
		 *
		 * @param array           $previous List data before deletion.
		 * @param array[]         $tasks    The deleted task rows.
		 * @param WP_REST_Request $request  Request.
		 */
		do_action( 'acme_tasks_list_deleted', $previous, $tasks, $request );

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Validate list input. Returns a WP_Error (status 400) for the first problem found.
	 *
	 * @param array $params   Input.
	 * @param bool  $creating Whether this is a create request (title required).
	 * @return true|WP_Error
	 */
	protected function validate_list_input( array $params, $creating ) {
		if ( $creating || array_key_exists( 'title', $params ) ) {
			$title = isset( $params['title'] ) && is_scalar( $params['title'] ) ? trim( sanitize_text_field( (string) $params['title'] ) ) : '';
			if ( '' === $title ) {
				return new WP_Error( 'acme_tasks_invalid_title', __( 'A list needs a title.', 'acme-tasks' ), array( 'status' => 400 ) );
			}
			if ( mb_strlen( $title ) > MAX_TITLE_LEN ) {
				return new WP_Error( 'acme_tasks_invalid_title', __( 'The title is too long.', 'acme-tasks' ), array( 'status' => 400 ) );
			}
		}
		if ( array_key_exists( 'color', $params ) && ! is_valid_color( $params['color'] ) ) {
			return new WP_Error( 'acme_tasks_invalid_color', __( 'Colors must be given as #rrggbb.', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		if ( array_key_exists( 'members', $params ) ) {
			if ( ! is_array( $params['members'] ) ) {
				return new WP_Error( 'acme_tasks_invalid_member', __( 'Members must be a list of user IDs.', 'acme-tasks' ), array( 'status' => 400 ) );
			}
			foreach ( $params['members'] as $member ) {
				$user = is_non_negative_int( $member ) ? get_userdata( (int) $member ) : false;
				if ( ! $user || ! user_can( $user, 'edit_posts' ) ) {
					return new WP_Error( 'acme_tasks_invalid_member', __( 'Members must be users who can use task lists.', 'acme-tasks' ), array( 'status' => 400 ) );
				}
			}
		}
		if ( array_key_exists( 'archived', $params ) && null === parse_bool( $params['archived'] ) ) {
			return new WP_Error( 'acme_tasks_invalid_archived', __( 'Archived must be a boolean.', 'acme-tasks' ), array( 'status' => 400 ) );
		}
		return true;
	}

	/**
	 * Shape a list for the API.
	 *
	 * @param \WP_Post        $post    List post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $post, $request ) {
		$counts = Task_Repository::counts( $post->ID );
		$data   = array(
			'id'          => $post->ID,
			'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'color'       => sanitize_color( get_post_meta( $post->ID, Post_Type::META_COLOR, true ) ),
			'owner'       => (int) $post->post_author,
			'members'     => Post_Type::members( $post->ID ),
			'archived'    => (bool) get_post_meta( $post->ID, Post_Type::META_ARCHIVE, true ),
			'status'      => 'trash' === $post->post_status ? 'trash' : 'publish',
			'task_count'  => $counts['total'],
			'open_count'  => $counts['open'],
			'created_at'  => to_rfc3339( $post->post_date_gmt ),
			'modified_at' => to_rfc3339( $post->post_modified_gmt ),
		);

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'  => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) ),
				'tasks' => array(
					'href'       => rest_url( sprintf( '%s/%s/%d/tasks', $this->namespace, $this->rest_base, $post->ID ) ),
					'embeddable' => false,
				),
			)
		);

		/**
		 * Filters a task list as returned by the API.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_REST_Response $response Response.
		 * @param \WP_Post         $post     List post.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'acme_tasks_rest_prepare_list', $response, $post, $request );
	}

	/**
	 * JSON schema of a list.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'acme-task-list',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'title'       => array(
					'type'      => 'string',
					'maxLength' => MAX_TITLE_LEN,
				),
				'color'       => array(
					'type'    => 'string',
					'pattern' => '^#[0-9a-fA-F]{6}$',
				),
				'owner'       => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'members'     => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'archived'    => array( 'type' => 'boolean' ),
				'status'      => array(
					'type'     => 'string',
					'enum'     => array( 'publish', 'trash' ),
					'readonly' => true,
				),
				'task_count'  => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'open_count'  => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'created_at'  => array(
					'type'     => 'string',
					'format'   => 'date-time',
					'readonly' => true,
				),
				'modified_at' => array(
					'type'     => 'string',
					'format'   => 'date-time',
					'readonly' => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * 404 error for unknown lists.
	 *
	 * @return WP_Error
	 */
	protected function not_found() {
		return new WP_Error( 'acme_tasks_list_not_found', __( 'Task list not found.', 'acme-tasks' ), array( 'status' => 404 ) );
	}
}
