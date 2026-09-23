<?php
/**
 * Office screen (Bookings) and settings.
 *
 * The office screen is rendered by assets/admin.js; this class prints the
 * static markup (QA automation relies on the ids/classes) and the settings.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Admin.
 */
class Admin {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( 'Acme\\Bookings\\Installer', 'maybe_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Menu entries.
	 */
	public function menu() {
		add_menu_page(
			__( 'Bookings', 'acme-bookings' ),
			__( 'Bookings', 'acme-bookings' ),
			acme_bookings_manager_capability(),
			'acme-bookings',
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			26
		);
		add_options_page(
			__( 'Bookings', 'acme-bookings' ),
			__( 'Bookings', 'acme-bookings' ),
			'manage_options',
			'acme-bookings-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Settings: currency and office e-mail.
	 */
	public function register_settings() {
		register_setting(
			'acme-bookings-settings',
			'acme_bookings_currency',
			array(
				'type'              => 'string',
				'default'           => 'EUR',
				'sanitize_callback' => static function ( $value ) {
					$value = strtoupper( sanitize_text_field( (string) $value ) );
					return preg_match( '/^[A-Z]{3}$/', $value ) ? $value : 'EUR';
				},
			)
		);
		register_setting(
			'acme-bookings-settings',
			'acme_bookings_office_email',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_email',
			)
		);
	}

	/**
	 * Scripts for the office screen.
	 *
	 * @param string $hook_suffix Screen hook.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'toplevel_page_acme-bookings' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'acme-bookings-admin', ACME_BOOKINGS_URL . 'assets/admin.css', array(), ACME_BOOKINGS_VERSION );
		wp_enqueue_script( 'acme-bookings-admin', ACME_BOOKINGS_URL . 'assets/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), ACME_BOOKINGS_VERSION, true );
		wp_set_script_translations( 'acme-bookings-admin', 'acme-bookings', ACME_BOOKINGS_DIR . 'languages' );

		$rooms = array();
		foreach ( Rooms::all() as $room ) {
			$rooms[] = array(
				'id'       => $room->ID,
				'title'    => get_the_title( $room ),
				'capacity' => Rooms::capacity( $room->ID ),
			);
		}
		$customers = array();
		foreach ( get_users( array( 'number' => 200, 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
			$customers[] = array(
				'id'   => (int) $user->ID,
				'name' => $user->display_name,
			);
		}
		wp_localize_script(
			'acme-bookings-admin',
			'acmeBookingsAdmin',
			array(
				'rooms'     => $rooms,
				'customers' => $customers,
				'perPage'   => 20,
				'currency'  => Pricing::currency(),
				'statuses'  => array(
					'pending'   => __( 'Pending', 'acme-bookings' ),
					'confirmed' => __( 'Confirmed', 'acme-bookings' ),
					'cancelled' => __( 'Cancelled', 'acme-bookings' ),
				),
			)
		);
	}

	/**
	 * Office screen markup. Rows are rendered by assets/admin.js.
	 */
	public function render_page() {
		?>
		<div class="wrap" id="acme-bookings-app">
			<h1><?php esc_html_e( 'Bookings', 'acme-bookings' ); ?></h1>

			<div class="acme-bookings-filters">
				<label for="acme-bookings-filter-room" class="screen-reader-text"><?php esc_html_e( 'Filter by room', 'acme-bookings' ); ?></label>
				<select id="acme-bookings-filter-room">
					<option value=""><?php esc_html_e( 'All rooms', 'acme-bookings' ); ?></option>
					<?php foreach ( Rooms::all() as $room ) : ?>
						<option value="<?php echo esc_attr( $room->ID ); ?>"><?php echo esc_html( get_the_title( $room ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="acme-bookings-filter-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'acme-bookings' ); ?></label>
				<select id="acme-bookings-filter-status">
					<option value=""><?php esc_html_e( 'All statuses', 'acme-bookings' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'acme-bookings' ); ?></option>
					<option value="confirmed"><?php esc_html_e( 'Confirmed', 'acme-bookings' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'acme-bookings' ); ?></option>
				</select>
			</div>

			<table class="widefat striped" id="acme-bookings-table">
				<thead>
					<tr>
						<th scope="col" class="column-id"><?php esc_html_e( 'ID', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-room"><?php esc_html_e( 'Room', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-customer"><?php esc_html_e( 'Customer', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-start"><?php esc_html_e( 'Check-in', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-end"><?php esc_html_e( 'Check-out', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-guests"><?php esc_html_e( 'Guests', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-status"><?php esc_html_e( 'Status', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-total"><?php esc_html_e( 'Total', 'acme-bookings' ); ?></th>
						<th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'acme-bookings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr class="acme-bookings-loading"><td colspan="9"><?php esc_html_e( 'Loading…', 'acme-bookings' ); ?></td></tr>
				</tbody>
			</table>

			<div class="acme-bookings-pager">
				<button type="button" class="button" id="acme-bookings-prev" aria-label="<?php esc_attr_e( 'Previous page', 'acme-bookings' ); ?>">&lsaquo;</button>
				<span id="acme-bookings-page-info"></span>
				<button type="button" class="button" id="acme-bookings-next" aria-label="<?php esc_attr_e( 'Next page', 'acme-bookings' ); ?>">&rsaquo;</button>
			</div>

			<h2><?php esc_html_e( 'New booking', 'acme-bookings' ); ?></h2>
			<form id="acme-bookings-new" class="acme-bookings-form">
				<p>
					<label for="acme-new-room"><?php esc_html_e( 'Room', 'acme-bookings' ); ?></label>
					<select id="acme-new-room" name="room" required>
						<?php foreach ( Rooms::all() as $room ) : ?>
							<option value="<?php echo esc_attr( $room->ID ); ?>"><?php echo esc_html( get_the_title( $room ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="acme-new-customer"><?php esc_html_e( 'Customer', 'acme-bookings' ); ?></label>
					<select id="acme-new-customer" name="customer"></select>
				</p>
				<p>
					<label for="acme-new-start"><?php esc_html_e( 'Check-in', 'acme-bookings' ); ?></label>
					<input type="date" id="acme-new-start" name="start" required />
					<label for="acme-new-end"><?php esc_html_e( 'Check-out', 'acme-bookings' ); ?></label>
					<input type="date" id="acme-new-end" name="end" required />
				</p>
				<p>
					<label for="acme-new-guests"><?php esc_html_e( 'Guests', 'acme-bookings' ); ?></label>
					<input type="number" id="acme-new-guests" name="guests" min="1" value="1" />
					<label for="acme-new-status"><?php esc_html_e( 'Status', 'acme-bookings' ); ?></label>
					<select id="acme-new-status" name="status">
						<option value="pending"><?php esc_html_e( 'Pending', 'acme-bookings' ); ?></option>
						<option value="confirmed"><?php esc_html_e( 'Confirmed', 'acme-bookings' ); ?></option>
					</select>
				</p>
				<p>
					<label for="acme-new-notes"><?php esc_html_e( 'Notes', 'acme-bookings' ); ?></label>
					<textarea id="acme-new-notes" name="notes" rows="2"></textarea>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Add booking', 'acme-bookings' ); ?></button></p>
				<p class="acme-bookings-message" role="status"></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Settings screen.
	 */
	public function render_settings() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Booking settings', 'acme-bookings' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'acme-bookings-settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acme_bookings_currency"><?php esc_html_e( 'Currency (ISO 4217)', 'acme-bookings' ); ?></label></th>
						<td><input type="text" id="acme_bookings_currency" name="acme_bookings_currency" maxlength="3" value="<?php echo esc_attr( Pricing::currency() ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme_bookings_office_email"><?php esc_html_e( 'Office e-mail', 'acme-bookings' ); ?></label></th>
						<td><input type="email" class="regular-text" id="acme_bookings_office_email" name="acme_bookings_office_email" value="<?php echo esc_attr( get_option( 'acme_bookings_office_email' ) ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
