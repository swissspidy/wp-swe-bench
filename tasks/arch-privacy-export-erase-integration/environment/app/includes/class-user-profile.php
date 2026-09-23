<?php
/**
 * "Loyalty" section on the wp-admin user profile screen (staff only).
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Profile screen fields.
 */
class User_Profile {

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
		$profile = Members::get_profile( $user->ID );
		?>
		<h2><?php esc_html_e( 'Loyalty', 'acme-loyalty' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Balance', 'acme-loyalty' ); ?></th>
				<td><?php echo esc_html( acme_loyalty_format_points( Ledger::balance( $user->ID ) ) ); ?></td>
			</tr>
			<tr>
				<th><label for="acme_loyalty_tier"><?php esc_html_e( 'Tier', 'acme-loyalty' ); ?></label></th>
				<td>
					<?php if ( current_user_can( 'edit_users' ) ) : ?>
						<select id="acme_loyalty_tier" name="acme_loyalty_tier">
							<option value=""><?php esc_html_e( 'Automatic', 'acme-loyalty' ); ?></option>
							<?php foreach ( acme_loyalty_tiers() as $slug => $tier ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $profile['tier_override'], $slug ); ?>><?php echo esc_html( $tier['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<?php echo esc_html( acme_loyalty_tier_label( $profile['tier'] ) ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="acme_loyalty_birthday"><?php esc_html_e( 'Birthday', 'acme-loyalty' ); ?></label></th>
				<td><input type="date" id="acme_loyalty_birthday" name="acme_loyalty_birthday" value="<?php echo esc_attr( $profile['birthday'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="acme_loyalty_phone"><?php esc_html_e( 'Phone', 'acme-loyalty' ); ?></label></th>
				<td><input type="tel" id="acme_loyalty_phone" name="acme_loyalty_phone" value="<?php echo esc_attr( $profile['phone'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Referral code', 'acme-loyalty' ); ?></th>
				<td><code><?php echo esc_html( $profile['referral_code'] ); ?></code></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save the section (the profile form's own nonce was verified by core).
	 *
	 * @param int $user_id User being saved.
	 */
	public function save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! Members::is_member( $user_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by user-edit.php.
		$data = array();
		if ( isset( $_POST['acme_loyalty_birthday'] ) ) {
			$data['birthday'] = sanitize_text_field( wp_unslash( $_POST['acme_loyalty_birthday'] ) );
		}
		if ( isset( $_POST['acme_loyalty_phone'] ) ) {
			$data['phone'] = sanitize_text_field( wp_unslash( $_POST['acme_loyalty_phone'] ) );
		}
		Members::save_profile( $user_id, $data );

		if ( isset( $_POST['acme_loyalty_tier'] ) && current_user_can( 'edit_users' ) ) {
			$tier = sanitize_key( wp_unslash( $_POST['acme_loyalty_tier'] ) );
			if ( '' === $tier || array_key_exists( $tier, acme_loyalty_tiers() ) ) {
				update_user_meta( $user_id, 'acme_loyalty_tier', $tier );
			}
		}
		// phpcs:enable
	}
}
