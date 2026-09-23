<?php
/**
 * Store newsletter: sign-up form, double opt-in, unsubscribe links.
 *
 * Subscribers don't need an account; they are identified by email address. Rows imported from
 * the 1.x mailing list (and everything signed up before 2.1) kept the email exactly as typed,
 * e.g. "Jane.Doe@Example.com". Since 2.1 new addresses are stored lower-cased.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Newsletter subscriptions.
 */
class Newsletter {

	const STATUS_PENDING      = 'pending';
	const STATUS_CONFIRMED    = 'confirmed';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'acme_newsletter', array( $this, 'shortcode' ) );
		add_action( 'admin_post_nopriv_acme_loyalty_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'admin_post_acme_loyalty_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'template_redirect', array( $this, 'handle_links' ) );
	}

	/**
	 * Status labels.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return array(
			self::STATUS_PENDING      => __( 'Waiting for confirmation', 'acme-loyalty' ),
			self::STATUS_CONFIRMED    => __( 'Subscribed', 'acme-loyalty' ),
			self::STATUS_UNSUBSCRIBED => __( 'Unsubscribed', 'acme-loyalty' ),
		);
	}

	/**
	 * Sign-up sources.
	 *
	 * @return array<string, string>
	 */
	public static function sources() {
		return array(
			'footer'   => __( 'Website footer form', 'acme-loyalty' ),
			'checkout' => __( 'Checkout', 'acme-loyalty' ),
			'store'    => __( 'In-store sign-up', 'acme-loyalty' ),
			'import'   => __( 'Imported from the old mailing list', 'acme-loyalty' ),
		);
	}

	/**
	 * Subscribe an address (or re-subscribe an unsubscribed one).
	 *
	 * @param string $email      Email.
	 * @param string $first_name First name.
	 * @param string $source     Source slug.
	 * @param int    $user_id    Linked account, if any.
	 * @return array|\WP_Error The subscriber row (as array).
	 */
	public static function subscribe( $email, $first_name = '', $source = 'footer', $user_id = 0 ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'acme_loyalty_invalid_email', __( 'Please enter a valid email address.', 'acme-loyalty' ) );
		}
		$table    = Installer::subscribers_table();
		$existing = self::find_by_email( $email );
		$settings = acme_loyalty_settings();
		$status   = $settings['double_optin'] ? self::STATUS_PENDING : self::STATUS_CONFIRMED;

		if ( $existing ) {
			if ( self::STATUS_CONFIRMED === $existing['status'] ) {
				return $existing;
			}
			$wpdb->update(
				$table,
				array(
					'status'          => $status,
					'token'           => wp_generate_password( 32, false ),
					'unsubscribed_at' => null,
				),
				array( 'id' => $existing['id'] )
			);
			$row = self::get( (int) $existing['id'] );
		} else {
			$wpdb->insert(
				$table,
				array(
					'email'         => $email,
					'first_name'    => sanitize_text_field( $first_name ),
					'user_id'       => (int) $user_id,
					'status'        => $status,
					'source'        => array_key_exists( $source, self::sources() ) ? $source : 'footer',
					'token'         => wp_generate_password( 32, false ),
					'ip_address'    => acme_loyalty_client_ip(),
					'subscribed_at' => current_time( 'mysql', true ),
					'confirmed_at'  => self::STATUS_CONFIRMED === $status ? current_time( 'mysql', true ) : null,
				)
			);
			$row = self::get( (int) $wpdb->insert_id );
		}

		if ( $row && self::STATUS_PENDING === $row['status'] ) {
			self::send_confirmation( $row );
		}
		return $row;
	}

	/**
	 * A subscriber row.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Installer::subscribers_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Find a subscriber by email.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	public static function find_by_email( $email ) {
		global $wpdb;
		$table = Installer::subscribers_table();
		// TODO: 1.x rows are mixed-case, this misses them (ACME-311).
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Confirmation email with the opt-in link.
	 *
	 * @param array $row Subscriber row.
	 */
	private static function send_confirmation( array $row ) {
		$link = add_query_arg( 'acme_confirm', rawurlencode( $row['token'] ), home_url( '/' ) );
		wp_mail(
			$row['email'],
			/* translators: %s: site name */
			sprintf( __( 'Please confirm your subscription to %s', 'acme-loyalty' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			/* translators: %s: confirmation link */
			sprintf( __( "Click here to confirm your subscription:\n%s", 'acme-loyalty' ), $link )
		);
	}

	/**
	 * [acme_newsletter] sign-up form.
	 *
	 * @return string
	 */
	public function shortcode() {
		$notice = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['acme_newsletter'] ) ? sanitize_key( $_GET['acme_newsletter'] ) : '';
		if ( 'subscribed' === $state ) {
			$notice = '<p class="acme-newsletter__notice">' . esc_html__( 'Thanks! Please check your inbox to confirm your subscription.', 'acme-loyalty' ) . '</p>';
		} elseif ( 'invalid' === $state ) {
			$notice = '<p class="acme-newsletter__notice acme-newsletter__notice--error">' . esc_html__( 'Please enter a valid email address.', 'acme-loyalty' ) . '</p>';
		}

		ob_start();
		?>
		<form class="acme-newsletter" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>
			<input type="hidden" name="action" value="acme_loyalty_subscribe" />
			<?php wp_nonce_field( 'acme_loyalty_subscribe', 'acme_newsletter_nonce' ); ?>
			<p>
				<label for="acme-newsletter-name"><?php esc_html_e( 'First name', 'acme-loyalty' ); ?></label>
				<input id="acme-newsletter-name" type="text" name="first_name" autocomplete="given-name" />
			</p>
			<p>
				<label for="acme-newsletter-email"><?php esc_html_e( 'Email', 'acme-loyalty' ); ?></label>
				<input id="acme-newsletter-email" type="email" name="email" required autocomplete="email" />
			</p>
			<p><button type="submit"><?php esc_html_e( 'Subscribe', 'acme-loyalty' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle the sign-up form.
	 */
	public function handle_subscribe() {
		check_admin_referer( 'acme_loyalty_subscribe', 'acme_newsletter_nonce' );
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$name  = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';

		$result = self::subscribe( $email, $name, 'footer', get_current_user_id() );
		$back   = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		wp_safe_redirect( add_query_arg( 'acme_newsletter', is_wp_error( $result ) ? 'invalid' : 'subscribed', $back ) );
		exit;
	}

	/**
	 * Confirmation and unsubscribe links.
	 */
	public function handle_links() {
		global $wpdb;
		$table = Installer::subscribers_table();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- token links from emails.
		if ( isset( $_GET['acme_confirm'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['acme_confirm'] ) );
			if ( '' !== $token ) {
				$wpdb->update(
					$table,
					array(
						'status'       => self::STATUS_CONFIRMED,
						'confirmed_at' => current_time( 'mysql', true ),
					),
					array(
						'token'  => $token,
						'status' => self::STATUS_PENDING,
					)
				);
			}
			wp_safe_redirect( add_query_arg( 'acme_newsletter', 'confirmed', home_url( '/' ) ) );
			exit;
		}
		if ( isset( $_GET['acme_unsubscribe'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['acme_unsubscribe'] ) );
			if ( '' !== $token ) {
				$wpdb->update(
					$table,
					array(
						'status'          => self::STATUS_UNSUBSCRIBED,
						'unsubscribed_at' => current_time( 'mysql', true ),
					),
					array( 'token' => $token )
				);
			}
			wp_safe_redirect( add_query_arg( 'acme_newsletter', 'unsubscribed', home_url( '/' ) ) );
			exit;
		}
		// phpcs:enable
	}
}
