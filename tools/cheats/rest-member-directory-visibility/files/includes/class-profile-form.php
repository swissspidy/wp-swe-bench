<?php
/**
 * [acme_member_edit_profile]: members edit their own directory profile on the front end.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end profile form.
 */
class Profile_Form {

	const ACTION = 'acme_members_save_profile';

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'acme_member_edit_profile', array( $this, 'shortcode' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_logged_out' ) );
	}

	/**
	 * Transient key holding the last failed submission of a user.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private static function state_key( $user_id ) {
		return 'acme_members_form_' . (int) $user_id;
	}

	/**
	 * Render the form.
	 *
	 * @return string
	 */
	public function shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p class="acme-profile-form__login">' . sprintf(
				/* translators: %s: login URL */
				wp_kses_post( __( 'Please <a href="%s">log in</a> to edit your profile.', 'acme-members' ) ),
				esc_url( wp_login_url( (string) get_permalink() ) )
			) . '</p>';
		}
		$user_id = get_current_user_id();
		if ( ! Members::is_member( $user_id ) ) {
			return '<p class="acme-profile-form__not-member">' . esc_html__( 'Only members have a directory profile.', 'acme-members' ) . '</p>';
		}

		$state  = get_transient( self::state_key( $user_id ) );
		$errors = array();
		$input  = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['acme_profile'] ) ? sanitize_key( $_GET['acme_profile'] ) : '';
		if ( 'error' === $status && is_array( $state ) ) {
			$errors = $state['errors'];
			$input  = $state['input'];
		}
		delete_transient( self::state_key( $user_id ) );

		$fields        = Fields::all();
		$values        = Fields::get_all( $user_id );
		$profile_level = Visibility::profile_level( $user_id );
		$field_levels  = Visibility::field_levels( $user_id );
		$levels        = Visibility::levels();

		ob_start();
		?>
		<form class="acme-profile-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::ACTION, 'acme_profile_nonce' ); ?>
			<?php if ( 'saved' === $status ) : ?>
				<p class="acme-profile-form__notice" role="status"><?php esc_html_e( 'Your profile was saved.', 'acme-members' ); ?></p>
			<?php endif; ?>
			<?php if ( $errors ) : ?>
				<div class="acme-profile-form__errors" role="alert">
					<p><?php esc_html_e( 'Your profile was not saved. Please correct the following:', 'acme-members' ); ?></p>
					<ul>
						<?php foreach ( $errors as $key => $message ) : ?>
							<li><a href="#acme-profile-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $message ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<p>
				<label for="acme-profile-visibility"><?php esc_html_e( 'Who can see my profile', 'acme-members' ); ?></label>
				<select id="acme-profile-visibility" name="visibility">
					<?php foreach ( $levels as $level => $label ) : ?>
						<option value="<?php echo esc_attr( $level ); ?>" <?php selected( isset( $input['visibility'] ) ? $input['visibility'] : $profile_level, $level ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php foreach ( $fields as $key => $field ) : ?>
				<?php
				$value     = array_key_exists( $key, $input ) ? (string) $input[ $key ] : $values[ $key ];
				$has_error = isset( $errors[ $key ] );
				$level     = isset( $input['field_visibility'][ $key ] ) ? $input['field_visibility'][ $key ] : $field_levels[ $key ];
				$aria      = $has_error ? ' aria-invalid="true" aria-describedby="acme-profile-' . esc_attr( $key ) . '-error"' : '';
				?>
				<div class="acme-profile-form__field acme-profile-form__field--<?php echo esc_attr( $key ); ?>">
					<label for="acme-profile-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
					<?php if ( 'textarea' === $field['type'] ) : ?>
						<textarea id="acme-profile-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="5"<?php echo $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>><?php echo esc_textarea( $value ); ?></textarea>
					<?php else : ?>
						<input type="<?php echo esc_attr( $field['type'] ); ?>" id="acme-profile-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) $field['max'] ); ?>"<?php echo $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> />
					<?php endif; ?>
					<?php if ( $has_error ) : ?>
						<p class="acme-profile-form__error" id="acme-profile-<?php echo esc_attr( $key ); ?>-error"><?php echo esc_html( $errors[ $key ] ); ?></p>
					<?php endif; ?>
					<label class="screen-reader-text" for="acme-profile-<?php echo esc_attr( $key ); ?>-visibility">
						<?php
						/* translators: %s: field label */
						echo esc_html( sprintf( __( 'Who can see "%s"', 'acme-members' ), $field['label'] ) );
						?>
					</label>
					<select id="acme-profile-<?php echo esc_attr( $key ); ?>-visibility" name="field_visibility[<?php echo esc_attr( $key ); ?>]">
						<?php foreach ( $levels as $option => $label ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $level, $option ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endforeach; ?>

			<p><button type="submit"><?php esc_html_e( 'Save profile', 'acme-members' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Logged-out submissions go to the login screen.
	 */
	public function handle_logged_out() {
		wp_safe_redirect( wp_login_url( home_url( '/' ) ) );
		exit;
	}

	/**
	 * Save the form (always the logged-in user's own profile).
	 */
	public function handle() {
		if ( ! isset( $_POST['acme_profile_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['acme_profile_nonce'] ) ), self::ACTION ) ) {
			wp_die( esc_html__( 'The link you followed has expired. Please reload the form and try again.', 'acme-members' ), '', array( 'response' => 403 ) );
		}
		$user_id = get_current_user_id();
		if ( ! Members::is_member( $user_id ) ) {
			wp_die( esc_html__( 'Only members have a directory profile.', 'acme-members' ), '', array( 'response' => 403 ) );
		}

		$input = array();
		foreach ( Fields::keys() as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$input[ $key ] = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by Profile_Service.
			}
		}
		if ( isset( $_POST['visibility'] ) ) {
			$input['visibility'] = sanitize_key( wp_unslash( $_POST['visibility'] ) );
		}
		if ( isset( $_POST['field_visibility'] ) ) {
			$input['field_visibility'] = is_array( $_POST['field_visibility'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['field_visibility'] ) ) : 'invalid';
		}

		$result = Profile_Service::validate( $input );
		$back   = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back   = remove_query_arg( 'acme_profile', $back );

		if ( $result['errors'] ) {
			set_transient(
				self::state_key( $user_id ),
				array(
					'errors' => $result['errors'],
					'input'  => $input,
				),
				10 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( add_query_arg( 'acme_profile', 'error', $back ) );
			exit;
		}

		Profile_Service::save( $user_id, $result['changes'] );
		wp_safe_redirect( add_query_arg( 'acme_profile', 'saved', $back ) );
		exit;
	}
}
