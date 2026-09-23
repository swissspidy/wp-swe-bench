<?php
/**
 * Settings screen (Settings → Charts).
 *
 * @package Acme\Charts
 */

namespace Acme\Charts;

defined( 'ABSPATH' ) || exit;

/**
 * Settings API integration for the `acme_charts_options` option.
 */
class Settings {

	const OPTION = 'acme_charts_options';
	const PAGE   = 'acme-charts';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the options page.
	 */
	public function add_page() {
		add_options_page(
			__( 'Charts', 'acme-charts' ),
			__( 'Charts', 'acme-charts' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register setting, section and fields.
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => acme_charts_default_options(),
			)
		);

		add_settings_section( 'acme_charts_main', __( 'Chart defaults', 'acme-charts' ), '__return_false', self::PAGE );

		add_settings_field(
			'palette',
			__( 'Colour palette', 'acme-charts' ),
			array( $this, 'field_palette' ),
			self::PAGE,
			'acme_charts_main',
			array( 'label_for' => 'acme-charts-palette' )
		);

		add_settings_field(
			'default_height',
			__( 'Default height', 'acme-charts' ),
			array( $this, 'field_default_height' ),
			self::PAGE,
			'acme_charts_main',
			array( 'label_for' => 'acme-charts-default-height' )
		);

		add_settings_field(
			'show_values',
			__( 'Values', 'acme-charts' ),
			array( $this, 'field_show_values' ),
			self::PAGE,
			'acme_charts_main',
			array( 'label_for' => 'acme-charts-show-values' )
		);
	}

	/**
	 * Sanitize the option array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = acme_charts_default_options();

		$palette = isset( $input['palette'] ) ? $input['palette'] : $defaults['palette'];
		if ( is_string( $palette ) ) {
			$palette = preg_split( '/[\s,]+/', $palette );
		}
		$palette = array_values( array_filter( array_map( 'sanitize_hex_color', (array) $palette ) ) );

		$height = isset( $input['default_height'] ) ? absint( $input['default_height'] ) : $defaults['default_height'];

		return array(
			'palette'        => $palette ? $palette : $defaults['palette'],
			'default_height' => min( 800, max( 80, $height ) ),
			'show_values'    => ! empty( $input['show_values'] ),
		);
	}

	/**
	 * Palette textarea.
	 */
	public function field_palette() {
		printf(
			'<textarea id="acme-charts-palette" name="%s[palette]" rows="3" cols="40" class="code">%s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( implode( ', ', (array) acme_charts_get_option( 'palette' ) ) )
		);
		echo '<p class="description">' . esc_html__( 'Comma-separated hex colours, used in order for bars that have no colour of their own.', 'acme-charts' ) . '</p>';
	}

	/**
	 * Default height input.
	 */
	public function field_default_height() {
		printf(
			'<input type="number" id="acme-charts-default-height" name="%s[default_height]" value="%d" min="80" max="800" step="10" class="small-text" /> px',
			esc_attr( self::OPTION ),
			(int) acme_charts_get_option( 'default_height' )
		);
	}

	/**
	 * Show values checkbox.
	 */
	public function field_show_values() {
		printf(
			'<label><input type="checkbox" id="acme-charts-show-values" name="%s[show_values]" value="1"%s /> %s</label>',
			esc_attr( self::OPTION ),
			checked( acme_charts_get_option( 'show_values' ), true, false ),
			esc_html__( 'Print values above the bars of new charts', 'acme-charts' )
		);
	}

	/**
	 * Render the page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Charts', 'acme-charts' ); ?></h1>
			<form action="options.php" method="post">
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
