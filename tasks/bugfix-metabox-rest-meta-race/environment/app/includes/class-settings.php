<?php
/**
 * Settings → Product fields.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Currency symbol and which editor products use.
 */
class Settings {

	const OPTION = 'acme_pf_settings';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'use_block_editor' ), 10, 2 );
	}

	/**
	 * Settings with defaults.
	 *
	 * @return array{currency: string, editor: string}
	 */
	public static function get() {
		$settings = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $settings ) ? $settings : array(),
			array(
				'currency' => '$',
				'editor'   => 'block',
			)
		);
	}

	/**
	 * Some shops still edit products in the classic editor.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        Post type.
	 * @return bool
	 */
	public function use_block_editor( $use_block_editor, $post_type ) {
		if ( Post_Type::POST_TYPE === $post_type && 'classic' === self::get()['editor'] ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Register the setting.
	 */
	public function register_setting() {
		register_setting(
			'acme-product-fields',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'currency' => isset( $input['currency'] ) ? substr( sanitize_text_field( $input['currency'] ), 0, 5 ) : '$',
			'editor'   => isset( $input['editor'] ) && 'classic' === $input['editor'] ? 'classic' : 'block',
		);
	}

	/**
	 * Menu entry.
	 */
	public function add_page() {
		add_options_page( __( 'Product fields', 'acme-product-fields' ), __( 'Product fields', 'acme-product-fields' ), 'manage_options', 'acme-product-fields', array( $this, 'render' ) );
	}

	/**
	 * Page.
	 */
	public function render() {
		$settings = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Product fields', 'acme-product-fields' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'acme-product-fields' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acme-pf-currency"><?php esc_html_e( 'Currency symbol', 'acme-product-fields' ); ?></label></th>
						<td><input type="text" id="acme-pf-currency" name="<?php echo esc_attr( self::OPTION ); ?>[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="acme-pf-editor"><?php esc_html_e( 'Edit products in', 'acme-product-fields' ); ?></label></th>
						<td>
							<select id="acme-pf-editor" name="<?php echo esc_attr( self::OPTION ); ?>[editor]">
								<option value="block" <?php selected( $settings['editor'], 'block' ); ?>><?php esc_html_e( 'Block editor', 'acme-product-fields' ); ?></option>
								<option value="classic" <?php selected( $settings['editor'], 'classic' ); ?>><?php esc_html_e( 'Classic editor', 'acme-product-fields' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
