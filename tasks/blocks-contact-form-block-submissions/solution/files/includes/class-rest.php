<?php
/**
 * POST /acme-contact/v1/submissions: submissions from the Contact form block without a page reload.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint.
 */
class Rest {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register the route.
	 */
	public function routes() {
		register_rest_route(
			'acme-contact/v1',
			'/submissions',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true', // Public contact form; spam is handled below.
				'args'                => array(
					'post_id'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'form_id'      => array(
						'type'     => 'string',
						'required' => true,
					),
					'fields'       => array(
						'type'    => 'object',
						'default' => array(),
					),
					'acme_website' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Handle a submission.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( \WP_REST_Request $request ) {
		$result = Block_Submissions::process( (int) $request['post_id'], (string) $request['form_id'], (array) $request['fields'], (string) $request['acme_website'] );

		switch ( $result['status'] ) {
			case Block_Submissions::STATUS_SENT:
			case Block_Submissions::STATUS_SPAM:
				return new \WP_REST_Response(
					array(
						'status'  => 'sent',
						'message' => $result['message'],
					),
					201
				);
			case Block_Submissions::STATUS_INVALID:
				return new \WP_Error(
					'acme_contact_invalid',
					$result['message'],
					array(
						'status' => 400,
						'errors' => (object) $result['errors'],
					)
				);
			case Block_Submissions::STATUS_RATE_LIMITED:
				return new \WP_Error( 'acme_contact_rate_limited', $result['message'], array( 'status' => 429 ) );
			default:
				return new \WP_Error( 'acme_contact_form_not_found', $result['message'], array( 'status' => 404 ) );
		}
	}
}
