<?php
/**
 * Settings → Activity Log.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen and accessors.
 */
class Settings {

	/**
	 * Option name (also the settings group).
	 */
	const OPTION = 'acme_activity_settings';

	/**
	 * Admin page slug.
	 */
	const PAGE = 'acme-activity-settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'tracked' => array_keys( self::events() ),
			'log_ip'  => true,
		);
	}

	/**
	 * Trackable events.
	 *
	 * @return array<string, string> action => label
	 */
	public static function events() {
		return array(
			'user_login'         => __( 'User logged in', 'acme-activity-log' ),
			'user_registered'    => __( 'User registered', 'acme-activity-log' ),
			'post_published'     => __( 'Content published', 'acme-activity-log' ),
			'post_trashed'       => __( 'Content trashed', 'acme-activity-log' ),
			'plugin_activated'   => __( 'Plugin activated', 'acme-activity-log' ),
			'plugin_deactivated' => __( 'Plugin deactivated', 'acme-activity-log' ),
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$settings = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/**
	 * Is an event tracked?
	 *
	 * @param string $action Action.
	 * @return bool
	 */
	public static function is_tracked( $action ) {
		return in_array( $action, (array) self::get()['tracked'], true );
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Register the setting.
	 */
	public function register_setting() {
		register_setting(
			self::OPTION,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'acme_activity_main', __( 'What to log', 'acme-activity-log' ), '__return_false', self::PAGE );

		add_settings_field( 'acme_activity_tracked', __( 'Events', 'acme-activity-log' ), array( $this, 'field_tracked' ), self::PAGE, 'acme_activity_main' );
		add_settings_field( 'acme_activity_log_ip', __( 'IP addresses', 'acme-activity-log' ), array( $this, 'field_log_ip' ), self::PAGE, 'acme_activity_main' );
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$tracked = isset( $input['tracked'] ) ? array_map( 'sanitize_key', (array) $input['tracked'] ) : array();

		return array(
			'tracked' => array_values( array_intersect( array_keys( self::events() ), $tracked ) ),
			'log_ip'  => ! empty( $input['log_ip'] ),
		);
	}

	/**
	 * Menu entry.
	 */
	public function menu() {
		add_options_page(
			__( 'Activity Log Settings', 'acme-activity-log' ),
			__( 'Activity Log', 'acme-activity-log' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Events checkboxes.
	 */
	public function field_tracked() {
		$tracked = (array) self::get()['tracked'];
		foreach ( self::events() as $action => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[tracked][]" value="%2$s" %3$s /> %4$s</label><br />',
				esc_attr( self::OPTION ),
				esc_attr( $action ),
				checked( in_array( $action, $tracked, true ), true, false ),
				esc_html( $label )
			);
		}
	}

	/**
	 * IP checkbox.
	 */
	public function field_log_ip() {
		printf(
			'<label><input type="checkbox" name="%1$s[log_ip]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( self::get()['log_ip'] ), true, false ),
			esc_html__( 'Store the IP address of the user who triggered the event', 'acme-activity-log' )
		);
	}

	/**
	 * Page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Activity Log Settings', 'acme-activity-log' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
