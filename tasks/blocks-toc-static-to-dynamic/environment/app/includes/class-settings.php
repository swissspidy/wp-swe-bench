<?php
/**
 * Settings → Table of Contents.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class Settings {

	const PAGE   = 'acme-toc';
	const OPTION = 'acme_toc_options';

	/**
	 * Add the options page.
	 */
	public function add_page() {
		add_options_page(
			__( 'Table of Contents', 'acme-toc' ),
			__( 'Table of Contents', 'acme-toc' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option and fields.
	 */
	public function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => default_options(),
			)
		);

		add_settings_section( 'acme_toc_main', '', '__return_false', self::PAGE );

		add_settings_field(
			'max_level',
			__( 'Deepest heading level for new tables of contents', 'acme-toc' ),
			array( $this, 'field_max_level' ),
			self::PAGE,
			'acme_toc_main',
			array( 'label_for' => 'acme-toc-max-level' )
		);
		add_settings_field(
			'smooth_scroll',
			__( 'Smooth scrolling', 'acme-toc' ),
			array( $this, 'field_smooth_scroll' ),
			self::PAGE,
			'acme_toc_main'
		);
	}

	/**
	 * Sanitize the option.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'max_level'     => clamp_level( $value['max_level'] ?? 3 ),
			'smooth_scroll' => ! empty( $value['smooth_scroll'] ),
		);
	}

	/**
	 * Max level field.
	 */
	public function field_max_level() {
		$options = get_options();
		echo '<select id="acme-toc-max-level" name="' . esc_attr( self::OPTION ) . '[max_level]">';
		foreach ( allowed_levels() as $level ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $level,
				selected( (int) $options['max_level'], $level, false ),
				/* translators: %d: heading level */
				esc_html( sprintf( __( 'Heading %d', 'acme-toc' ), $level ) )
			);
		}
		echo '</select>';
	}

	/**
	 * Smooth scroll field.
	 */
	public function field_smooth_scroll() {
		$options = get_options();
		printf(
			'<label><input type="checkbox" name="%1$s[smooth_scroll]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( ! empty( $options['smooth_scroll'] ), true, false ),
			esc_html__( 'Scroll smoothly when a table of contents link is clicked', 'acme-toc' )
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
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
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

	/**
	 * "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ), esc_html__( 'Settings', 'acme-toc' ) )
		);
		return $links;
	}
}
