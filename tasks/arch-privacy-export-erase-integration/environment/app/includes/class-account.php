<?php
/**
 * "My loyalty" account page: [acme_loyalty_account].
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Member account page.
 */
class Account {

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'acme_loyalty_account', array( $this, 'shortcode' ) );
		add_action( 'admin_post_acme_loyalty_save_preferences', array( $this, 'save_preferences' ) );
	}

	/**
	 * Render the account page.
	 *
	 * @return string
	 */
	public function shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p class="acme-loyalty-account acme-loyalty-account--guest">' . esc_html__( 'Please log in to see your loyalty points.', 'acme-loyalty' ) . '</p>';
		}
		$user_id = get_current_user_id();
		if ( ! Members::is_member( $user_id ) ) {
			return '<p class="acme-loyalty-account">' . esc_html__( 'You are not a loyalty member yet. Ask in store or sign up at checkout.', 'acme-loyalty' ) . '</p>';
		}

		$profile  = Members::get_profile( $user_id );
		$history  = Ledger::history( $user_id, 20 );
		$settings = acme_loyalty_settings();

		ob_start();
		include ACME_LOYALTY_DIR . 'templates/account.php';
		return (string) ob_get_clean();
	}

	/**
	 * Save the preferences form.
	 */
	public function save_preferences() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'acme_loyalty_preferences', 'acme_loyalty_nonce' );
		$user_id = get_current_user_id();

		Members::save_profile(
			$user_id,
			array(
				'birthday' => isset( $_POST['birthday'] ) ? sanitize_text_field( wp_unslash( $_POST['birthday'] ) ) : '',
				'phone'    => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
				'channels' => isset( $_POST['channels'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['channels'] ) ) : array(),
				'store'    => isset( $_POST['store'] ) ? sanitize_text_field( wp_unslash( $_POST['store'] ) ) : '',
			)
		);

		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'acme_loyalty', 'saved', $back ) );
		exit;
	}
}
