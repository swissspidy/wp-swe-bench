<?php
/**
 * Settings screen (Settings → Callouts).
 *
 * @package Acme\Callouts
 */

namespace Acme\Callouts;

defined( 'ABSPATH' ) || exit;

/**
 * Settings API integration for the `acme_callouts_options` option.
 */
class Settings {

	const OPTION = 'acme_callouts_options';
	const PAGE   = 'acme-callouts';

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
			__( 'Callouts', 'acme-callouts' ),
			__( 'Callouts', 'acme-callouts' ),
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
				'default'           => array(),
			)
		);

		add_settings_section( 'acme_callouts_main', __( 'Defaults', 'acme-callouts' ), '__return_false', self::PAGE );

		add_settings_field(
			'default_type',
			__( 'Default type', 'acme-callouts' ),
			array( $this, 'field_default_type' ),
			self::PAGE,
			'acme_callouts_main',
			array( 'label_for' => 'acme-callouts-default-type' )
		);

		add_settings_field(
			'enable_shortcode',
			__( 'Legacy shortcode', 'acme-callouts' ),
			array( $this, 'field_enable_shortcode' ),
			self::PAGE,
			'acme_callouts_main',
			array( 'label_for' => 'acme-callouts-enable-shortcode' )
		);
	}

	/**
	 * Sanitize the option array.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$types = acme_callouts_get_types();
		$type  = isset( $input['default_type'] ) ? sanitize_key( $input['default_type'] ) : 'info';

		return array(
			'default_type'     => isset( $types[ $type ] ) ? $type : 'info',
			'enable_shortcode' => ! empty( $input['enable_shortcode'] ),
		);
	}

	/**
	 * Default type <select>.
	 */
	public function field_default_type() {
		$current = acme_callouts_get_option( 'default_type' );
		echo '<select id="acme-callouts-default-type" name="' . esc_attr( self::OPTION ) . '[default_type]">';
		foreach ( acme_callouts_get_types() as $slug => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $current, $slug, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used by the [callout] shortcode when no type is given, and for newly inserted callout blocks.', 'acme-callouts' ) . '</p>';
	}

	/**
	 * Enable shortcode checkbox.
	 */
	public function field_enable_shortcode() {
		printf(
			'<label><input type="checkbox" id="acme-callouts-enable-shortcode" name="%s[enable_shortcode]" value="1"%s /> %s</label>',
			esc_attr( self::OPTION ),
			checked( acme_callouts_get_option( 'enable_shortcode' ), true, false ),
			esc_html__( 'Render [callout] shortcodes in old content', 'acme-callouts' )
		);
	}

	/**
	 * Render the page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html( get_admin_page_title() ) . '</h1><form action="options.php" method="post">';
		settings_fields( self::PAGE );
		do_settings_sections( self::PAGE );
		submit_button();
		echo '</form></div>';
	}
}
