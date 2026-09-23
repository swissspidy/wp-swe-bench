<?php
/**
 * REST: /acme-newsroom/v1/stories/{id}/approval
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Approve (POST) / withdraw approval (DELETE) / read (GET) a story's approval.
 */
class REST_Approval_Controller extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'acme-newsroom/v1';
		$this->rest_base = 'stories';
	}

	/**
	 * Routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/approval',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Story ID.', 'acme-newsroom' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unapprove' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * The story, or an error.
	 *
	 * @param int $id Story ID.
	 * @return \WP_Post|\WP_Error
	 */
	protected function get_story( $id ) {
		$story = get_post( (int) $id );
		if ( ! $story || Story_Post_Type::POST_TYPE !== $story->post_type ) {
			return new \WP_Error( 'rest_story_invalid_id', __( 'Invalid story ID.', 'acme-newsroom' ), array( 'status' => 404 ) );
		}
		return $story;
	}

	/**
	 * Anyone who can edit the story can see its approval.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$story = $this->get_story( $request['id'] );
		if ( is_wp_error( $story ) ) {
			return $story;
		}
		if ( ! current_user_can( 'edit_post', $story->ID ) && ! Approval::current_user_can_approve( $story->ID ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to see the approval of this story.', 'acme-newsroom' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Approving is for the desk.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		$story = $this->get_story( $request['id'] );
		if ( is_wp_error( $story ) ) {
			return $story;
		}
		if ( ! Approval::current_user_can_approve( $story->ID ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to approve this story.', 'acme-newsroom' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * GET.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->respond( (int) $request['id'] );
	}

	/**
	 * POST: approve.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function approve( $request ) {
		Approval::approve( (int) $request['id'], get_current_user_id() );
		return $this->respond( (int) $request['id'] );
	}

	/**
	 * DELETE: withdraw.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function unapprove( $request ) {
		Approval::unapprove( (int) $request['id'], get_current_user_id() );
		return $this->respond( (int) $request['id'] );
	}

	/**
	 * Response.
	 *
	 * @param int $id Story ID.
	 * @return \WP_REST_Response
	 */
	protected function respond( $id ) {
		return rest_ensure_response( array_merge( array( 'id' => $id ), get_approval( $id ) ) );
	}

	/**
	 * Schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return $this->add_additional_fields_schema(
			array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'acme-story-approval',
				'type'       => 'object',
				'properties' => array(
					'id'          => array( 'type' => 'integer' ),
					'approved'    => array( 'type' => 'boolean' ),
					'approved_by' => array( 'type' => 'integer' ),
					'approved_at' => array( 'type' => array( 'string', 'null' ) ),
				),
			)
		);
	}
}
