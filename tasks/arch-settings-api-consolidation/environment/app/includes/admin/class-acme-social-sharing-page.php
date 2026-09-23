<?php
/**
 * Acme Social → Sharing screen.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Share button settings.
 */
class Acme_Social_Sharing_Page {

	/**
	 * Notice to show after saving.
	 *
	 * @var string
	 */
	private $notice = '';

	/**
	 * Hooks.
	 */
	public function register() {
		// Nothing to register: the form is handled when the screen loads.
	}

	/**
	 * Handles the form submission (runs on load-{screen}).
	 */
	public function load() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['acme_social_save_sharing'] ) ) {
			return;
		}
		check_admin_referer( 'acme_social_save_sharing', '_acme_social_nonce' );

		update_option( 'acme_social_share_buttons_enabled', empty( $_POST['share_enabled'] ) ? 'no' : 'yes' );

		$networks = isset( $_POST['networks'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['networks'] ) ) : array();
		update_option( 'acme_social_networks', implode( ',', $networks ) );

		if ( isset( $_POST['position'] ) ) {
			update_option( 'acme_share_position', sanitize_key( wp_unslash( $_POST['position'] ) ) );
		}

		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
		update_option( 'acme_social_post_types', $post_types );

		if ( isset( $_POST['button_style'] ) ) {
			update_option( 'acmesocial_button_style', sanitize_key( wp_unslash( $_POST['button_style'] ) ) );
		}

		$this->notice = __( 'Settings saved.', 'acme-social' );
	}

	/**
	 * Renders the screen.
	 */
	public function render() {
		$enabled_networks = acme_social_enabled_networks();
		$post_types       = acme_social_share_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sharing', 'acme-social' ); ?></h1>
			<?php if ( $this->notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $this->notice ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="">
				<?php wp_nonce_field( 'acme_social_save_sharing', '_acme_social_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable share buttons', 'acme-social' ); ?></th>
						<td>
							<label><input type="checkbox" name="share_enabled" value="1" <?php checked( acme_social_share_enabled() ); ?> />
							<?php esc_html_e( 'Show share buttons on the selected post types', 'acme-social' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Networks', 'acme-social' ); ?></th>
						<td>
							<fieldset>
							<?php foreach ( acme_social_available_networks() as $slug => $network ) : ?>
								<label><input type="checkbox" name="networks[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $enabled_networks, true ) ); ?> />
								<?php echo esc_html( $network['label'] ); ?></label><br />
							<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-social-position"><?php esc_html_e( 'Button position', 'acme-social' ); ?></label></th>
						<td>
							<select id="acme-social-position" name="position">
								<?php foreach ( acme_social_positions() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( acme_social_share_position(), $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show on', 'acme-social' ); ?></th>
						<td>
							<fieldset>
							<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) : ?>
								<?php
								if ( 'attachment' === $type->name ) {
									continue;
								}
								?>
								<label><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $post_types, true ) ); ?> />
								<?php echo esc_html( $type->labels->name ); ?></label><br />
							<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-social-style"><?php esc_html_e( 'Button style', 'acme-social' ); ?></label></th>
						<td>
							<select id="acme-social-style" name="button_style">
								<?php foreach ( acme_social_button_styles() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( acme_social_button_style(), $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Changes', 'acme-social' ), 'primary', 'acme_social_save_sharing' ); ?>
			</form>
		</div>
		<?php
	}
}
