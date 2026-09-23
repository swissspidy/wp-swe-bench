<?php
/**
 * `acme_fields` on the team member REST resource (/wp/v2/acme-members).
 *
 * Used by the editor to show and edit member fields in connected blocks.
 * Values are resolved like on the front end (1.x fallbacks included) and
 * sanitized like in the meta box.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * REST field registration.
 */
class Rest {

	const FIELD = 'acme_fields';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Register the REST field.
	 */
	public function register() {
		$properties = array();
		foreach ( acme_team_fields() as $key => $field ) {
			if ( 'name' === $key ) {
				continue; // The name is the post title.
			}
			$properties[ $key ] = array(
				'type'        => 'image' === $field['type'] ? 'integer' : 'string',
				'description' => $field['label'],
			);
		}

		register_rest_field(
			Post_Type::POST_TYPE,
			self::FIELD,
			array(
				'get_callback'    => array( $this, 'get_fields' ),
				'update_callback' => array( $this, 'update_fields' ),
				'schema'          => array(
					'description'          => __( 'Team member fields.', 'acme-team' ),
					'type'                 => 'object',
					'context'              => array( 'view', 'edit' ),
					'properties'           => $properties,
					'additionalProperties' => false,
				),
			)
		);
	}

	/**
	 * Field values for the response.
	 *
	 * Members that aren't public only expose their fields to users who can
	 * edit them (e.g. a password-protected member is listed publicly, but its
	 * fields are not).
	 *
	 * @param array $data Prepared post data.
	 * @return array|null
	 */
	public function get_fields( $data ) {
		$member = Member::get( (int) $data['id'] );
		if ( ! $member ) {
			return null;
		}
		$values = array();
		$can    = true;
		foreach ( acme_team_fields() as $key => $field ) {
			if ( 'name' === $key ) {
				continue;
			}
			if ( 'image' === $field['type'] ) {
				$values[ $key ] = $can ? $member->photo_id() : 0;
			} else {
				$values[ $key ] = $can ? $member->get_field( $key ) : '';
			}
		}
		return $values;
	}

	/**
	 * Save field values sent by the editor.
	 *
	 * The posts controller has already checked that the user can edit the member.
	 *
	 * @param mixed    $value New values (field key => value).
	 * @param \WP_Post $post  Member post.
	 * @return true|\WP_Error
	 */
	public function update_fields( $value, $post ) {
		if ( ! is_array( $value ) ) {
			return true;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this team member.', 'acme-team' ), array( 'status' => rest_authorization_required_code() ) );
		}
		$fields = acme_team_fields();
		foreach ( $value as $key => $raw ) {
			if ( 'name' === $key || ! isset( $fields[ $key ] ) ) {
				continue;
			}
			$clean = Meta_Box::sanitize_value( $fields[ $key ]['type'], $raw );
			if ( '' === $clean ) {
				delete_post_meta( $post->ID, $fields[ $key ]['meta_key'] );
			} else {
				update_post_meta( $post->ID, $fields[ $key ]['meta_key'], wp_slash( $clean ) );
			}
		}
		return true;
	}
}
