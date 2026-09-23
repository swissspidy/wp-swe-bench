<?php
/**
 * "Member directory" section on the wp-admin user screens.
 *
 * Staff maintain profiles for members who don't want to do it themselves. This is currently the
 * only place where profiles can be edited.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * wp-admin profile fields.
 */
class Admin {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
	}

	/**
	 * Render the section.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function render( $user ) {
		if ( ! Members::is_member( $user->ID ) ) {
			return;
		}
		$values  = Fields::get_all( $user->ID );
		$levels  = Visibility::field_levels( $user->ID );
		$profile = Visibility::profile_level( $user->ID );
		?>
		<h2><?php esc_html_e( 'Member directory', 'acme-members' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="acme_member_visibility"><?php esc_html_e( 'Profile visible to', 'acme-members' ); ?></label></th>
				<td><?php $this->level_select( 'acme_member_visibility', 'acme_member_visibility', $profile ); ?></td>
			</tr>
			<?php foreach ( Fields::all() as $key => $field ) : ?>
				<tr>
					<th><label for="acme_member_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<?php if ( 'textarea' === $field['type'] ) : ?>
							<textarea id="acme_member_<?php echo esc_attr( $key ); ?>" name="acme_member[<?php echo esc_attr( $key ); ?>]" rows="4" cols="40"><?php echo esc_textarea( $values[ $key ] ); ?></textarea>
						<?php else : ?>
							<input type="<?php echo esc_attr( $field['type'] ); ?>" id="acme_member_<?php echo esc_attr( $key ); ?>" name="acme_member[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $values[ $key ] ); ?>" class="regular-text" />
						<?php endif; ?>
						<?php $this->level_select( 'acme_member_field_visibility_' . $key, 'acme_member_field_visibility[' . $key . ']', $levels[ $key ] ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Visibility dropdown.
	 *
	 * @param string $id      Element ID.
	 * @param string $name    Field name.
	 * @param string $current Current level.
	 */
	private function level_select( $id, $name, $current ) {
		printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
		foreach ( Visibility::levels() as $level => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $level ), selected( $current, $level, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Save the section (the profile form's nonce was verified by core).
	 *
	 * @param int $user_id User being saved.
	 */
	public function save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! Members::is_member( $user_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by user-edit.php.
		Fields::migrate_legacy( $user_id );
		$input = isset( $_POST['acme_member'] ) && is_array( $_POST['acme_member'] ) ? wp_unslash( $_POST['acme_member'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field.
		foreach ( Fields::keys() as $key ) {
			if ( isset( $input[ $key ] ) ) {
				Fields::set( $user_id, $key, Fields::sanitize( $key, $input[ $key ] ) );
			}
		}
		if ( isset( $_POST['acme_member_visibility'] ) ) {
			$level = sanitize_key( wp_unslash( $_POST['acme_member_visibility'] ) );
			if ( Visibility::is_level( $level ) ) {
				update_user_meta( $user_id, 'acme_member_visibility', $level );
				delete_user_meta( $user_id, 'acme_member_hide_profile' );
			}
		}
		if ( isset( $_POST['acme_member_field_visibility'] ) && is_array( $_POST['acme_member_field_visibility'] ) ) {
			$levels = array();
			foreach ( wp_unslash( $_POST['acme_member_field_visibility'] ) as $key => $level ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$key   = sanitize_key( $key );
				$level = sanitize_key( $level );
				if ( in_array( $key, Fields::keys(), true ) && Visibility::is_level( $level ) ) {
					$levels[ $key ] = $level;
				}
			}
			update_user_meta( $user_id, 'acme_member_field_visibility', $levels );
			delete_user_meta( $user_id, 'acme_member_hide_phone' );
		}
		// phpcs:enable

		/**
		 * Fires after a member profile was saved.
		 *
		 * @param int $user_id Member.
		 */
		do_action( 'acme_members_profile_saved', $user_id );
	}
}
