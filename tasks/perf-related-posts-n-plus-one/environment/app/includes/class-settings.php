<?php
/**
 * Settings → Related reading.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin options (stored in the `acme_related_settings` option).
 */
class Settings {

	const OPTION = 'acme_related_settings';

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Where the list is appended to post content: 'single', 'everywhere' (single posts and archives) or 'none'.
			'display'         => 'single',
			'count'           => 4,
			'heading'         => __( 'Related posts', 'acme-related' ),
			'show_thumbnails' => true,
			'show_views'      => true,
		);
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * All settings merged with the defaults.
	 *
	 * @return array
	 */
	public function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		// 1.x stored the count as "related_count".
		if ( isset( $stored['related_count'] ) && ! isset( $stored['count'] ) ) {
			$stored['count'] = $stored['related_count'];
		}
		return wp_parse_args( $stored, self::defaults() );
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
	 * Number of related posts to show.
	 *
	 * @return int
	 */
	public function count() {
		return max( 1, min( 12, (int) $this->get( 'count' ) ) );
	}

	/**
	 * Register the option.
	 */
	public function register_setting() {
		register_setting(
			'acme_related',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize the submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$out['display']         = isset( $input['display'] ) && in_array( $input['display'], array( 'single', 'everywhere', 'none' ), true ) ? $input['display'] : 'single';
		$out['count']           = isset( $input['count'] ) ? max( 1, min( 12, absint( $input['count'] ) ) ) : 4;
		$out['heading']         = isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : $out['heading'];
		$out['show_thumbnails'] = ! empty( $input['show_thumbnails'] );
		$out['show_views']      = ! empty( $input['show_views'] );
		return $out;
	}

	/**
	 * Menu entry.
	 */
	public function add_page() {
		add_options_page(
			__( 'Related reading', 'acme-related' ),
			__( 'Related reading', 'acme-related' ),
			'manage_options',
			'acme-related',
			array( $this, 'render_page' )
		);
	}

	/**
	 * The settings screen.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = $this->all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Related reading', 'acme-related' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'acme_related' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acme-related-display"><?php esc_html_e( 'Show the list', 'acme-related' ); ?></label></th>
						<td>
							<select id="acme-related-display" name="<?php echo esc_attr( self::OPTION ); ?>[display]">
								<option value="single" <?php selected( $s['display'], 'single' ); ?>><?php esc_html_e( 'Below single posts', 'acme-related' ); ?></option>
								<option value="everywhere" <?php selected( $s['display'], 'everywhere' ); ?>><?php esc_html_e( 'Below posts everywhere (also on the blog and archives)', 'acme-related' ); ?></option>
								<option value="none" <?php selected( $s['display'], 'none' ); ?>><?php esc_html_e( 'Nowhere (block and REST API only)', 'acme-related' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-related-count"><?php esc_html_e( 'Number of posts', 'acme-related' ); ?></label></th>
						<td><input type="number" min="1" max="12" id="acme-related-count" name="<?php echo esc_attr( self::OPTION ); ?>[count]" value="<?php echo esc_attr( $s['count'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-related-heading"><?php esc_html_e( 'Heading', 'acme-related' ); ?></label></th>
						<td><input type="text" id="acme-related-heading" name="<?php echo esc_attr( self::OPTION ); ?>[heading]" value="<?php echo esc_attr( $s['heading'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Details', 'acme-related' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_thumbnails]" value="1" <?php checked( $s['show_thumbnails'] ); ?> /> <?php esc_html_e( 'Show featured images', 'acme-related' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_views]" value="1" <?php checked( $s['show_views'] ); ?> /> <?php esc_html_e( 'Show view counts', 'acme-related' ); ?></label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
