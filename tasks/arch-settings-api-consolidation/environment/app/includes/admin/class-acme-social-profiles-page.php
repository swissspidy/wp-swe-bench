<?php
/**
 * Acme Social → Profiles screen.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Social profile settings.
 *
 * @todo The save handler was moved to admin_init in 1.5 so the notice survives
 *       the menu refactor; clean this up.
 */
class Acme_Social_Profiles_Page {

	/**
	 * Option names of the fields on this screen.
	 *
	 * @var string[]
	 */
	private $fields = array(
		'acme_social_twitter',
		'acme_social_facebook',
		'acme_social_instagram_url',
		'acme_social_linkedin',
		'acme_social_youtube_channel',
	);

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
	}

	/**
	 * Saves the profiles.
	 */
	public function maybe_save() {
		if ( ! isset( $_POST['acme_social_save_profiles'] ) ) {
			return;
		}
		foreach ( $this->fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, trim( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Profiles saved.', 'acme-social' ) . '</p></div>';
			}
		);
	}

	/**
	 * Renders the screen.
	 */
	public function render() {
		$urls   = acme_social_profile_urls();
		$fields = array(
			'acme_social_twitter'         => array( __( 'Twitter/X username', 'acme-social' ), acme_social_twitter_handle() ? '@' . acme_social_twitter_handle() : '', 'text', '@acme' ),
			'acme_social_facebook'        => array( __( 'Facebook page URL', 'acme-social' ), $urls['facebook'], 'url', 'https://www.facebook.com/…' ),
			'acme_social_instagram_url'   => array( __( 'Instagram URL', 'acme-social' ), $urls['instagram'], 'url', 'https://www.instagram.com/…' ),
			'acme_social_linkedin'        => array( __( 'LinkedIn URL', 'acme-social' ), $urls['linkedin'], 'url', 'https://www.linkedin.com/company/…' ),
			'acme_social_youtube_channel' => array( __( 'YouTube channel URL', 'acme-social' ), $urls['youtube'], 'url', 'https://www.youtube.com/@…' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Social profiles', 'acme-social' ); ?></h1>
			<p><?php esc_html_e( 'Shown by the [acme_social_profiles] shortcode and used for the Twitter card tags.', 'acme-social' ); ?></p>
			<form method="post" action="">
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $name => $field ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
							<td><input type="<?php echo esc_attr( $field[2] ); ?>" class="regular-text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $field[1] ); ?>" placeholder="<?php echo esc_attr( $field[3] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Save Changes', 'acme-social' ), 'primary', 'acme_social_save_profiles' ); ?>
			</form>
		</div>
		<?php
	}
}
