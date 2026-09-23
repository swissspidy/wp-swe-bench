<?php
/**
 * Front-end availability widget: [acme_availability room="123"].
 *
 * Shows the booked dates of a room and lets logged-in customers request a
 * booking (assets/widget.js).
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Widget.
 */
class Widget {

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'acme_availability', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (not enqueue) the assets; the shortcode enqueues them.
	 */
	public function register_assets() {
		wp_register_style( 'acme-bookings-widget', ACME_BOOKINGS_URL . 'assets/widget.css', array(), ACME_BOOKINGS_VERSION );
		wp_register_script( 'acme-bookings-widget', ACME_BOOKINGS_URL . 'assets/widget.js', array(), ACME_BOOKINGS_VERSION, true );
		wp_localize_script(
			'acme-bookings-widget',
			'acmeBookingsWidget',
			array(
				'root'     => esc_url_raw( rest_url( 'acme-bookings/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'loggedIn' => is_user_logged_in(),
				'checkin'  => Pricing::CHECKIN_TIME,
				'checkout' => Pricing::CHECKOUT_TIME,
				'i18n'     => array(
					'booked'      => __( 'Booked', 'acme-bookings' ),
					'nothing'     => __( 'No bookings in the next weeks – all dates are free.', 'acme-bookings' ),
					/* translators: %d: booking ID. */
					'thanks'      => __( 'Thanks! Your booking request #%d was received.', 'acme-bookings' ),
					'unavailable' => __( 'Sorry, the room is not available for these dates.', 'acme-bookings' ),
					'error'       => __( 'Something went wrong. Please try again.', 'acme-bookings' ),
				),
			)
		);
	}

	/**
	 * Shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts    = shortcode_atts( array( 'room' => 0 ), $atts, 'acme_availability' );
		$room_id = (int) $atts['room'];
		if ( ! Rooms::is_bookable( $room_id ) ) {
			return '';
		}
		wp_enqueue_style( 'acme-bookings-widget' );
		wp_enqueue_script( 'acme-bookings-widget' );

		ob_start();
		?>
		<div class="acme-availability" data-room="<?php echo esc_attr( $room_id ); ?>">
			<h3 class="acme-availability__title">
				<?php
				/* translators: %s: room name. */
				echo esc_html( sprintf( __( 'Availability: %s', 'acme-bookings' ), get_the_title( $room_id ) ) );
				?>
			</h3>
			<ul class="acme-availability__booked" aria-live="polite"></ul>
			<?php if ( is_user_logged_in() ) : ?>
				<form class="acme-availability__form">
					<label><?php esc_html_e( 'Check-in', 'acme-bookings' ); ?> <input type="date" name="start" required /></label>
					<label><?php esc_html_e( 'Check-out', 'acme-bookings' ); ?> <input type="date" name="end" required /></label>
					<label><?php esc_html_e( 'Guests', 'acme-bookings' ); ?> <input type="number" name="guests" min="1" max="<?php echo esc_attr( Rooms::capacity( $room_id ) ); ?>" value="1" /></label>
					<button type="submit" class="acme-availability__request"><?php esc_html_e( 'Request booking', 'acme-bookings' ); ?></button>
				</form>
			<?php else : ?>
				<p class="acme-availability__login"><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Log in to book', 'acme-bookings' ); ?></a></p>
			<?php endif; ?>
			<p class="acme-availability__message" role="status"></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
