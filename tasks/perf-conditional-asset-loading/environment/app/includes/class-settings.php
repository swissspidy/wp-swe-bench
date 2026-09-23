<?php
/**
 * Settings → UI Kit.
 *
 * @package Acme\UI
 */

namespace Acme\UI;

defined( 'ABSPATH' ) || exit;

/**
 * Settings (option `acme_ui_settings`).
 */
class Settings {

	const OPTION = 'acme_ui_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'accent'   => '#3858e9',
			'speed'    => 200,
			'autoplay' => false,
		);
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public function all() {
		$s = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		$all = $this->all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Register the option.
	 */
	public function register_setting() {
		register_setting(
			'acme_ui',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'accent'   => sanitize_hex_color( isset( $input['accent'] ) ? $input['accent'] : '' ) ? sanitize_hex_color( $input['accent'] ) : '#3858e9',
			'speed'    => isset( $input['speed'] ) ? max( 0, min( 2000, absint( $input['speed'] ) ) ) : 200,
			'autoplay' => ! empty( $input['autoplay'] ),
		);
	}

	/**
	 * Menu.
	 */
	public function menu() {
		add_options_page( __( 'UI Kit', 'acme-ui-kit' ), __( 'UI Kit', 'acme-ui-kit' ), 'manage_options', 'acme-ui', array( $this, 'render' ) );
	}

	/**
	 * Settings screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s        = $this->all();
		$renderer = new Renderer();
		?>
		<div class="wrap acme-ui-settings">
			<h1><?php esc_html_e( 'UI Kit', 'acme-ui-kit' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'acme_ui' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acme-ui-accent"><?php esc_html_e( 'Accent colour', 'acme-ui-kit' ); ?></label></th>
						<td><input type="text" id="acme-ui-accent" name="<?php echo esc_attr( self::OPTION ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-ui-speed"><?php esc_html_e( 'Animation speed', 'acme-ui-kit' ); ?></label></th>
						<td><input type="range" min="0" max="2000" step="50" id="acme-ui-speed" name="<?php echo esc_attr( self::OPTION ); ?>[speed]" value="<?php echo esc_attr( $s['speed'] ); ?>" /><span class="acme-ui-speed-value"></span></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Carousel', 'acme-ui-kit' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[autoplay]" value="1" <?php checked( $s['autoplay'] ); ?> /> <?php esc_html_e( 'Advance slides automatically', 'acme-ui-kit' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<div class="acme-ui-preview">
				<h2><?php esc_html_e( 'Preview', 'acme-ui-kit' ); ?></h2>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
				echo $renderer->tabs(
					array(
						array(
							'title'   => __( 'Tabs', 'acme-ui-kit' ),
							'content' => __( 'This is how tabs look with these settings.', 'acme-ui-kit' ),
						),
						array(
							'title'   => __( 'Second tab', 'acme-ui-kit' ),
							'content' => __( 'Another panel.', 'acme-ui-kit' ),
						),
					)
				);
				?>
			</div>
		</div>
		<?php
	}
}
