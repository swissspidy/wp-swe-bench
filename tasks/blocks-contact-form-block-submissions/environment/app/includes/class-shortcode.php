<?php
/**
 * [acme_contact subjects="Sales|Support" button="Send" title="Contact us"]
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * The classic contact form.
 */
class Shortcode {

	const TAG = 'acme_contact';

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Default attributes.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'subjects' => '',
			'button'   => __( 'Send message', 'acme-contact' ),
			'title'    => '',
		);
	}

	/**
	 * Attributes of the first [acme_contact] shortcode in a post (used by the handler to know
	 * which subjects are allowed, never trust the browser for that).
	 *
	 * @param int $post_id Post.
	 * @return array|null Null when the post has no contact form.
	 */
	public static function find_in_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! has_shortcode( $post->post_content, self::TAG ) ) {
			return null;
		}
		preg_match_all( '/' . get_shortcode_regex( array( self::TAG ) ) . '/', $post->post_content, $matches, PREG_SET_ORDER );
		foreach ( $matches as $m ) {
			if ( self::TAG === $m[2] ) {
				return shortcode_atts( self::defaults(), shortcode_parse_atts( $m[3] ), self::TAG );
			}
		}
		return null;
	}

	/**
	 * Front-end assets.
	 */
	public function assets() {
		wp_register_style( 'acme-contact', ACME_CONTACT_URL . 'assets/form.css', array(), ACME_CONTACT_VERSION );
		$asset = ACME_CONTACT_DIR . 'build/shortcode/counter.asset.php';
		if ( is_readable( $asset ) ) {
			$deps = include $asset;
			wp_register_script( 'acme-contact-counter', ACME_CONTACT_URL . 'build/shortcode/counter.js', $deps['dependencies'], $deps['version'], true );
		}
	}

	/**
	 * Render the form.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts     = shortcode_atts( self::defaults(), $atts, self::TAG );
		$subjects = acme_contact_parse_subjects( $atts['subjects'] );
		$post_id  = get_the_ID();

		wp_enqueue_style( 'acme-contact' );
		wp_enqueue_script( 'acme-contact-counter' );

		// Result of a previous submission (no JavaScript involved: the handler redirects back).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['acme_contact'] ) ? sanitize_key( $_GET['acme_contact'] ) : '';
		$state  = array(
			'errors' => array(),
			'values' => array(),
		);
		if ( 'error' === $status && isset( $_GET['acme_state'] ) ) {
			$saved = get_transient( 'acme_contact_state_' . sanitize_key( $_GET['acme_state'] ) );
			if ( is_array( $saved ) ) {
				$state = array_merge( $state, $saved );
			}
		}
		// phpcs:enable

		$value = static function ( $key ) use ( $state ) {
			return isset( $state['values'][ $key ] ) ? (string) $state['values'][ $key ] : '';
		};

		ob_start();
		?>
		<form id="acme-contact" class="acme-contact" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php if ( '' !== $atts['title'] ) : ?>
				<h3 class="acme-contact__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( 'sent' === $status ) : ?>
				<p class="acme-contact__notice acme-contact__notice--success"><?php echo esc_html( acme_contact_settings()['success_message'] ); ?></p>
			<?php elseif ( $state['errors'] ) : ?>
				<ul class="acme-contact__notice acme-contact__notice--error">
					<?php foreach ( $state['errors'] as $field => $code ) : ?>
						<li><?php echo esc_html( ucfirst( $field ) . ': ' . Validator::message( $code, 'message' === $field ? Validator::MAX_TEXTAREA : Validator::MAX_TEXT ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<input type="hidden" name="action" value="acme_contact_submit" />
			<input type="hidden" name="acme_source" value="shortcode" />
			<input type="hidden" name="acme_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
			<?php wp_nonce_field( 'acme_contact_submit', '_acme_contact_nonce' ); ?>

			<p class="acme-contact__row">
				<label for="acme-name"><?php esc_html_e( 'Your name', 'acme-contact' ); ?> *</label>
				<input id="acme-name" type="text" name="acme_name" value="<?php echo esc_attr( $value( 'name' ) ); ?>" required />
			</p>
			<p class="acme-contact__row">
				<label for="acme-email"><?php esc_html_e( 'Your email', 'acme-contact' ); ?> *</label>
				<input id="acme-email" type="email" name="acme_email" value="<?php echo esc_attr( $value( 'email' ) ); ?>" required />
			</p>
			<?php if ( $subjects ) : ?>
				<p class="acme-contact__row">
					<label for="acme-subject"><?php esc_html_e( 'Subject', 'acme-contact' ); ?> *</label>
					<select id="acme-subject" name="acme_subject" required>
						<option value=""><?php esc_html_e( '— Please choose —', 'acme-contact' ); ?></option>
						<?php foreach ( $subjects as $subject ) : ?>
							<option value="<?php echo esc_attr( $subject ); ?>" <?php selected( $value( 'subject' ), $subject ); ?>><?php echo esc_html( $subject ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p class="acme-contact__row">
				<label for="acme-message"><?php esc_html_e( 'Message', 'acme-contact' ); ?> *</label>
				<textarea id="acme-message" name="acme_message" rows="6" maxlength="<?php echo esc_attr( (string) Validator::MAX_TEXTAREA ); ?>" data-acme-counter required><?php echo esc_textarea( $value( 'message' ) ); ?></textarea>
			</p>
			<p class="acme-contact__hp" aria-hidden="true">
				<label for="acme-website"><?php esc_html_e( 'Leave this field empty', 'acme-contact' ); ?></label>
				<input id="acme-website" type="text" name="acme_website" value="" tabindex="-1" autocomplete="off" />
			</p>
			<p><button type="submit"><?php echo esc_html( $atts['button'] ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
