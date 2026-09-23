<?php
/**
 * Acme Social → Open Graph screen.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Open Graph / Twitter card settings.
 */
class Acme_Social_Open_Graph_Page {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_post_acme_social_save_og', array( $this, 'save' ) );
	}

	/**
	 * Handles admin-post.php?action=acme_social_save_og.
	 */
	public function save() {
		check_admin_referer( 'acme_social_save_og' );
		if ( ! current_user_can( Acme_Social_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change these settings.', 'acme-social' ), 403 );
		}

		update_option( 'acme_og_enabled', empty( $_POST['og_enabled'] ) ? '' : '1' );

		$image = isset( $_POST['og_default_image'] ) ? absint( $_POST['og_default_image'] ) : 0;
		update_option( 'acme_og_default_image', $image );

		if ( isset( $_POST['fb_app_id'] ) ) {
			update_option( 'acme_social_fb_app_id', sanitize_text_field( wp_unslash( $_POST['fb_app_id'] ) ) );
		}
		if ( isset( $_POST['twitter_card'] ) ) {
			update_option( 'acme_social_twitter_card', sanitize_key( wp_unslash( $_POST['twitter_card'] ) ) );
		}

		acme_social_flush_og_cache();

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=acme-social-og' ) ) );
		exit;
	}

	/**
	 * Renders the screen.
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Open Graph', 'acme-social' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'acme-social' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="acme_social_save_og" />
				<?php wp_nonce_field( 'acme_social_save_og' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Open Graph tags', 'acme-social' ); ?></th>
						<td><label><input type="checkbox" name="og_enabled" value="1" <?php checked( acme_social_og_enabled() ); ?> />
						<?php esc_html_e( 'Output Open Graph and Twitter card tags', 'acme-social' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-social-og-image"><?php esc_html_e( 'Default share image (attachment ID)', 'acme-social' ); ?></label></th>
						<td>
							<input type="number" min="0" class="small-text" id="acme-social-og-image" name="og_default_image" value="<?php echo esc_attr( (string) acme_social_og_default_image_id() ); ?>" />
							<p class="description"><?php esc_html_e( 'Used when a post has no featured image.', 'acme-social' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-social-fb-app-id"><?php esc_html_e( 'Facebook App ID', 'acme-social' ); ?></label></th>
						<td><input type="text" class="regular-text" id="acme-social-fb-app-id" name="fb_app_id" value="<?php echo esc_attr( acme_social_fb_app_id() ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-social-twitter-card"><?php esc_html_e( 'Twitter card type', 'acme-social' ); ?></label></th>
						<td>
							<select id="acme-social-twitter-card" name="twitter_card">
								<?php foreach ( acme_social_twitter_card_types() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( acme_social_twitter_card(), $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Changes', 'acme-social' ) ); ?>
			</form>
		</div>
		<?php
	}
}
