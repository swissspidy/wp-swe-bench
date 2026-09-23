<?php
/**
 * "Member details" meta box on the member edit screen.
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Edit + save the member fields.
 */
class Meta_Box {

	const NONCE     = 'acme_team_member_nonce';
	const NOTES_KEY = '_acme_notes';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Add the meta box.
	 */
	public function add() {
		add_meta_box( 'acme-team-member', __( 'Member details', 'acme-team' ), array( $this, 'render' ), Post_Type::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Render the fields.
	 *
	 * @param \WP_Post $post Member post.
	 */
	public function render( $post ) {
		$member = Member::get( $post );
		wp_nonce_field( 'acme_team_save_member', self::NONCE );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( acme_team_fields() as $key => $field ) {
			if ( 'name' === $key ) {
				continue;
			}
			$type = array(
				'email' => 'email',
				'url'   => 'url',
				'image' => 'number',
				'phone' => 'tel',
			)[ $field['type'] ] ?? 'text';
			printf(
				'<tr><th scope="row"><label for="acme-team-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="acme-team-%1$s" name="acme_team[%1$s]" value="%4$s" /></td></tr>',
				esc_attr( $key ),
				esc_html( $field['label'] ),
				esc_attr( $type ),
				esc_attr( $member ? $member->get_field( $key ) : '' )
			);
		}
		printf(
			'<tr><th scope="row"><label for="acme-team-notes">%1$s</label></th><td><textarea class="large-text" rows="3" id="acme-team-notes" name="acme_team[__notes]">%2$s</textarea><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Internal notes', 'acme-team' ),
			esc_textarea( $member ? $member->internal_notes() : '' ),
			esc_html__( 'HR only. Never shown on the site.', 'acme-team' )
		);
		echo '</tbody></table>';
	}

	/**
	 * Save the fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), 'acme_team_save_member' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['acme_team'] ) && is_array( $_POST['acme_team'] ) ? wp_unslash( $_POST['acme_team'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field below.

		foreach ( acme_team_fields() as $key => $field ) {
			if ( 'name' === $key || ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = self::sanitize_value( $field['type'], $input[ $key ] );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $field['meta_key'] );
			} else {
				update_post_meta( $post_id, $field['meta_key'], $value );
			}
		}

		if ( array_key_exists( '__notes', $input ) ) {
			update_post_meta( $post_id, self::NOTES_KEY, sanitize_textarea_field( (string) $input['__notes'] ) );
		}
	}

	/**
	 * Sanitize a field value by field type.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public static function sanitize_value( $type, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );
			case 'url':
				return esc_url_raw( $value, array( 'http', 'https' ) );
			case 'image':
				$id = absint( $value );
				return $id ? (string) $id : '';
			default:
				return sanitize_text_field( $value );
		}
	}
}
