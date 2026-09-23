<?php
/**
 * Loyalty → Settings screen (option `acme_loyalty_settings`).
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu + settings page.
 */
class Settings {

	const OPTION = 'acme_loyalty_settings';
	const PAGE   = 'acme-loyalty';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Top-level "Loyalty" menu; Orders hang below it.
	 */
	public function menu() {
		add_menu_page( __( 'Loyalty', 'acme-loyalty' ), __( 'Loyalty', 'acme-loyalty' ), 'manage_options', self::PAGE, array( $this, 'render' ), 'dashicons-awards', 56 );
		add_submenu_page( self::PAGE, __( 'Loyalty settings', 'acme-loyalty' ), __( 'Settings', 'acme-loyalty' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/**
	 * Register the option and its fields.
	 */
	public function register_setting() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => acme_loyalty_default_settings(),
			)
		);

		add_settings_section( 'acme_loyalty_points', __( 'Points', 'acme-loyalty' ), '__return_false', self::PAGE );
		add_settings_field( 'points_per_currency', __( 'Points per $1 spent', 'acme-loyalty' ), array( $this, 'field_number' ), self::PAGE, 'acme_loyalty_points', array( 'key' => 'points_per_currency' ) );
		add_settings_field( 'welcome_bonus', __( 'Welcome bonus', 'acme-loyalty' ), array( $this, 'field_number' ), self::PAGE, 'acme_loyalty_points', array( 'key' => 'welcome_bonus' ) );

		add_settings_section( 'acme_loyalty_newsletter', __( 'Newsletter', 'acme-loyalty' ), '__return_false', self::PAGE );
		add_settings_field( 'double_optin', __( 'Double opt-in', 'acme-loyalty' ), array( $this, 'field_double_optin' ), self::PAGE, 'acme_loyalty_newsletter' );

		add_settings_section( 'acme_loyalty_stores', __( 'Stores', 'acme-loyalty' ), '__return_false', self::PAGE );
		add_settings_field( 'store_names', __( 'Store names', 'acme-loyalty' ), array( $this, 'field_stores' ), self::PAGE, 'acme_loyalty_stores' );

		add_settings_section( 'acme_loyalty_privacy', __( 'Privacy', 'acme-loyalty' ), array( $this, 'privacy_section' ), self::PAGE );
		add_settings_field( 'retention_months', __( 'Keep personal data for', 'acme-loyalty' ), array( $this, 'field_retention' ), self::PAGE, 'acme_loyalty_privacy', array( 'label_for' => 'retention_months' ) );
	}

	/**
	 * Sanitize the whole option.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = acme_loyalty_default_settings();

		$stores = isset( $input['store_names'] ) ? $input['store_names'] : $defaults['store_names'];
		if ( is_string( $stores ) ) {
			$stores = preg_split( '/\r\n|\r|\n/', $stores );
		}
		$stores = array_values( array_filter( array_map( 'sanitize_text_field', (array) $stores ) ) );

		return array(
			'points_per_currency' => isset( $input['points_per_currency'] ) ? max( 0, absint( $input['points_per_currency'] ) ) : $defaults['points_per_currency'],
			'welcome_bonus'       => isset( $input['welcome_bonus'] ) ? max( 0, absint( $input['welcome_bonus'] ) ) : $defaults['welcome_bonus'],
			'double_optin'        => ! empty( $input['double_optin'] ),
			'store_names'         => $stores,
			'retention_months'    => $this->sanitize_retention( $input ),
		);
	}

	/**
	 * Retention period: whole number of months, 0 (keep forever) to 120. Anything else is
	 * rejected and the current value is kept.
	 *
	 * @param array $input Raw input.
	 * @return int
	 */
	private function sanitize_retention( array $input ) {
		$current = (int) acme_loyalty_settings()['retention_months'];
		if ( ! array_key_exists( 'retention_months', $input ) ) {
			return $current;
		}
		$raw = is_scalar( $input['retention_months'] ) ? trim( (string) $input['retention_months'] ) : '';
		if ( ! preg_match( '/^\d+$/', $raw ) || (int) $raw > 120 ) {
			add_settings_error(
				self::OPTION,
				'acme_loyalty_retention_months',
				__( 'The retention period must be a whole number of months between 0 and 120.', 'acme-loyalty' )
			);
			return $current;
		}
		return (int) $raw;
	}

	/**
	 * Privacy section intro.
	 */
	public function privacy_section() {
		echo '<p>' . esc_html__( 'Unconfirmed and cancelled newsletter sign-ups are deleted, and IP addresses are removed from the points history, once they are older than this. Use 0 to keep everything.', 'acme-loyalty' ) . '</p>';
	}

	/**
	 * Retention field.
	 */
	public function field_retention() {
		$settings = acme_loyalty_settings();
		printf(
			'<input type="number" min="0" max="120" step="1" class="small-text" id="retention_months" name="%1$s[retention_months]" value="%2$s" /> %3$s',
			esc_attr( self::OPTION ),
			esc_attr( (string) (int) $settings['retention_months'] ),
			esc_html__( 'months', 'acme-loyalty' )
		);
	}

	/**
	 * Numeric field.
	 *
	 * @param array $args Field args.
	 */
	public function field_number( $args ) {
		$settings = acme_loyalty_settings();
		printf(
			'<input type="number" min="0" class="small-text" id="%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $args['key'] ),
			esc_attr( self::OPTION ),
			esc_attr( (string) $settings[ $args['key'] ] )
		);
	}

	/**
	 * Double opt-in checkbox.
	 */
	public function field_double_optin() {
		$settings = acme_loyalty_settings();
		printf(
			'<label><input type="checkbox" id="double_optin" name="%1$s[double_optin]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( $settings['double_optin'] ), true, false ),
			esc_html__( 'Send a confirmation email before subscribing someone', 'acme-loyalty' )
		);
	}

	/**
	 * Store names textarea.
	 */
	public function field_stores() {
		$settings = acme_loyalty_settings();
		printf(
			'<textarea id="store_names" name="%1$s[store_names]" rows="4" cols="40">%2$s</textarea><p class="description">%3$s</p>',
			esc_attr( self::OPTION ),
			esc_textarea( implode( "\n", (array) $settings['store_names'] ) ),
			esc_html__( 'One store per line.', 'acme-loyalty' )
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Loyalty settings', 'acme-loyalty' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
