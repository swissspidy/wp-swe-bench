<?php
/**
 * Settings → Newsletter.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class Settings {

	const PAGE = 'acme-newsletter';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the menu entry.
	 */
	public function menu() {
		add_options_page(
			__( 'Newsletter', 'acme-newsletter' ),
			__( 'Newsletter', 'acme-newsletter' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting and its fields.
	 */
	public function register_settings() {
		register_setting(
			self::PAGE,
			OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => default_settings(),
			)
		);

		add_settings_section( 'acme_newsletter_form', __( 'Signup form', 'acme-newsletter' ), '__return_false', self::PAGE );
		add_settings_section( 'acme_newsletter_placement', __( 'Placement', 'acme-newsletter' ), array( $this, 'placement_intro' ), self::PAGE );

		$text_fields = array(
			'heading'         => __( 'Heading', 'acme-newsletter' ),
			'description'     => __( 'Description', 'acme-newsletter' ),
			'button_label'    => __( 'Button label', 'acme-newsletter' ),
			'consent_text'    => __( 'Consent checkbox text', 'acme-newsletter' ),
			'success_message' => __( 'Success message', 'acme-newsletter' ),
		);
		foreach ( $text_fields as $key => $label ) {
			add_settings_field(
				'acme_newsletter_' . $key,
				$label,
				array( $this, 'text_field' ),
				self::PAGE,
				'acme_newsletter_form',
				array(
					'key'       => $key,
					'label_for' => 'acme_newsletter_' . $key,
				)
			);
		}
		add_settings_field(
			'acme_newsletter_show_name',
			__( 'Name field', 'acme-newsletter' ),
			array( $this, 'checkbox_field' ),
			self::PAGE,
			'acme_newsletter_form',
			array(
				'key'   => 'show_name',
				'label' => __( 'Ask for the subscriber\'s name', 'acme-newsletter' ),
			)
		);
		add_settings_field(
			'acme_newsletter_placements',
			__( 'Add the form automatically', 'acme-newsletter' ),
			array( $this, 'placements_field' ),
			self::PAGE,
			'acme_newsletter_placement'
		);
	}

	/**
	 * Placement section intro.
	 */
	public function placement_intro() {
		echo '<p>' . esc_html__( 'Where the signup form is added automatically. You can always add it manually with the Newsletter signup block, the [acme_newsletter] shortcode or the widget.', 'acme-newsletter' ) . '</p>';
		if ( wp_is_block_theme() ) {
			echo '<p>' . esc_html__( 'Your theme is a block theme: the automatic forms appear in your templates in the Site Editor (Appearance → Editor), where you can move or remove them.', 'acme-newsletter' ) . '</p>';
		}
	}

	/**
	 * Placement checkboxes.
	 */
	public function placements_field() {
		$enabled = (array) get_setting( 'placements' );
		echo '<fieldset>';
		foreach ( get_placement_choices() as $slug => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[placements][]" value="%2$s" %3$s /> %4$s</label><br />',
				esc_attr( OPTION ),
				esc_attr( $slug ),
				checked( in_array( $slug, $enabled, true ), true, false ),
				esc_html( $label )
			);
		}
		// Makes sure "placements" is submitted even when every box is unchecked.
		printf( '<input type="hidden" name="%s[placements][]" value="" />', esc_attr( OPTION ) );
		echo '</fieldset>';
	}

	/**
	 * Text input.
	 *
	 * @param array $args Field args.
	 */
	public function text_field( $args ) {
		$value = get_setting( $args['key'] );
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%2$s[%3$s]" value="%4$s" />',
			esc_attr( 'acme_newsletter_' . $args['key'] ),
			esc_attr( OPTION ),
			esc_attr( $args['key'] ),
			esc_attr( (string) $value )
		);
	}

	/**
	 * Checkbox input.
	 *
	 * @param array $args Field args.
	 */
	public function checkbox_field( $args ) {
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( OPTION ),
			esc_attr( $args['key'] ),
			checked( (bool) get_setting( $args['key'] ), true, false ),
			esc_html( $args['label'] )
		);
	}

	/**
	 * Sanitize the settings array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = default_settings();
		$out      = array();
		foreach ( array( 'heading', 'description', 'button_label', 'consent_text', 'success_message' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $defaults[ $key ];
		}
		if ( '' === $out['button_label'] ) {
			$out['button_label'] = $defaults['button_label'];
		}
		$out['show_name']  = ! empty( $input['show_name'] );
		$out['placements'] = sanitize_placements( $input['placements'] ?? array() );
		return $out;
	}

	/**
	 * Settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Newsletter', 'acme-newsletter' ); ?></h1>
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
