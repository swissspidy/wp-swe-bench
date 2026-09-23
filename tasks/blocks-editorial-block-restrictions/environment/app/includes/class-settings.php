<?php
/**
 * Settings → Newsroom: press release details and editorial rules.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class Settings {

	const PAGE          = 'acme-newsroom';
	const GROUP_DETAILS = 'acme-newsroom-details';
	const GROUP_RULES   = 'acme-newsroom-rules';

	/**
	 * Post types the editorial rules can be configured for.
	 *
	 * @return string[]
	 */
	public static function rule_post_types() {
		/**
		 * Filters the post types that get editorial block rules.
		 *
		 * @param string[] $post_types Post type names.
		 */
		return (array) apply_filters( 'acme_newsroom_rule_post_types', array( 'post', 'page', Press_Releases::POST_TYPE ) );
	}

	/**
	 * Roles the editorial rules can be configured for.
	 *
	 * @return string[] Role => label.
	 */
	public static function rule_roles() {
		$roles = array();
		foreach ( wp_roles()->roles as $role => $data ) {
			if ( 'administrator' !== $role ) {
				$roles[ $role ] = translate_user_role( $data['name'] );
			}
		}
		return $roles;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the page.
	 */
	public function add_page() {
		add_options_page( __( 'Newsroom', 'acme-newsroom' ), __( 'Newsroom', 'acme-newsroom' ), 'manage_options', self::PAGE, array( $this, 'render_page' ) );
	}

	/**
	 * Register both option groups.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP_DETAILS,
			'acme_newsroom_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_details' ),
				'default'           => array(),
			)
		);
		register_setting(
			self::GROUP_RULES,
			Editorial_Rules::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_rules_form' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the press release details.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_details( $input ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'city'          => sanitize_text_field( $input['city'] ?? '' ),
			'boilerplate'   => sanitize_textarea_field( $input['boilerplate'] ?? '' ),
			'contact_name'  => sanitize_text_field( $input['contact_name'] ?? '' ),
			'contact_email' => sanitize_email( $input['contact_email'] ?? '' ),
			'contact_phone' => sanitize_text_field( $input['contact_phone'] ?? '' ),
		);
	}

	/**
	 * Turn the rules form (textareas: one block per line) into the option schema.
	 *
	 * Form fields:
	 *   acme_newsroom_block_rules[post_types][{type}][restrict]      "1" when the type has an allow-list
	 *   acme_newsroom_block_rules[post_types][{type}][allowed]       textarea
	 *   acme_newsroom_block_rules[post_types][{type}][roles][{role}] textarea (empty = no role rule)
	 *   acme_newsroom_block_rules[disabled_design_tools][{role}][]   checkboxes
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize_rules_form( $input ) {
		$input = is_array( $input ) ? $input : array();
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $type => $type_rules ) {
				if ( ! is_array( $type_rules ) ) {
					continue;
				}
				if ( array_key_exists( 'restrict', $type_rules ) ) {
					if ( empty( $type_rules['restrict'] ) ) {
						$type_rules['allowed'] = null;
					}
					unset( $type_rules['restrict'] );
				}
				if ( isset( $type_rules['roles'] ) && is_array( $type_rules['roles'] ) ) {
					$type_rules['roles'] = array_filter(
						$type_rules['roles'],
						static function ( $list ) {
							return is_array( $list ) || '' !== trim( (string) $list );
						}
					);
				}
				if ( null === ( $type_rules['allowed'] ?? null ) && empty( $type_rules['roles'] ) ) {
					unset( $input['post_types'][ $type ] );
					continue;
				}
				$input['post_types'][ $type ] = $type_rules;
			}
		}
		return Editorial_Rules::sanitize( $input );
	}

	/**
	 * Render the page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$details = acme_newsroom_details();
		$rules   = Editorial_Rules::get();
		$name    = Editorial_Rules::OPTION;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2><?php esc_html_e( 'Press release details', 'acme-newsroom' ); ?></h2>
			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP_DETAILS ); ?>
				<table class="form-table" role="presentation">
					<?php
					$fields = array(
						'city'          => __( 'Dateline city', 'acme-newsroom' ),
						'contact_name'  => __( 'Media contact name', 'acme-newsroom' ),
						'contact_email' => __( 'Media contact email', 'acme-newsroom' ),
						'contact_phone' => __( 'Media contact phone', 'acme-newsroom' ),
					);
					foreach ( $fields as $key => $label ) {
						printf(
							'<tr><th scope="row"><label for="acme-newsroom-%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="acme-newsroom-%1$s" name="acme_newsroom_settings[%1$s]" value="%3$s"></td></tr>',
							esc_attr( $key ),
							esc_html( $label ),
							esc_attr( $details[ $key ] )
						);
					}
					?>
					<tr>
						<th scope="row"><label for="acme-newsroom-boilerplate"><?php esc_html_e( 'Company boilerplate', 'acme-newsroom' ); ?></label></th>
						<td><textarea class="large-text" rows="4" id="acme-newsroom-boilerplate" name="acme_newsroom_settings[boilerplate]"><?php echo esc_textarea( $details['boilerplate'] ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save details', 'acme-newsroom' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Editorial rules', 'acme-newsroom' ); ?></h2>
			<p><?php esc_html_e( 'One block name per line, e.g. core/paragraph. Use acme/* for all blocks of a namespace.', 'acme-newsroom' ); ?></p>
			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP_RULES ); ?>
				<?php foreach ( self::rule_post_types() as $type ) : ?>
					<?php
					$type_object = get_post_type_object( $type );
					if ( ! $type_object ) {
						continue;
					}
					$type_rules = $rules['post_types'][ $type ] ?? array(
						'allowed' => null,
						'roles'   => array(),
					);
					?>
					<h3><?php echo esc_html( $type_object->labels->name ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Allowed blocks (everyone)', 'acme-newsroom' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( "{$name}[post_types][{$type}][restrict]" ); ?>" value="1" <?php checked( null !== $type_rules['allowed'] ); ?>> <?php esc_html_e( 'Only allow these blocks', 'acme-newsroom' ); ?></label><br>
								<textarea class="large-text code" rows="4" name="<?php echo esc_attr( "{$name}[post_types][{$type}][allowed]" ); ?>"><?php echo esc_textarea( implode( "\n", (array) $type_rules['allowed'] ) ); ?></textarea>
							</td>
						</tr>
						<?php foreach ( self::rule_roles() as $role => $label ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td><textarea class="large-text code" rows="3" name="<?php echo esc_attr( "{$name}[post_types][{$type}][roles][{$role}]" ); ?>" placeholder="<?php esc_attr_e( 'No additional restriction', 'acme-newsroom' ); ?>"><?php echo esc_textarea( implode( "\n", $type_rules['roles'][ $role ] ?? array() ) ); ?></textarea></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>

				<h3><?php esc_html_e( 'Design tools', 'acme-newsroom' ); ?></h3>
				<table class="form-table" role="presentation">
					<?php foreach ( self::rule_roles() as $role => $label ) : ?>
						<?php $disabled = $rules['disabled_design_tools'][ $role ] ?? array(); ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( "{$name}[disabled_design_tools][{$role}][]" ); ?>" value="custom_colors" <?php checked( in_array( 'custom_colors', $disabled, true ) ); ?>> <?php esc_html_e( 'No custom colors (theme palette only)', 'acme-newsroom' ); ?></label><br>
								<label><input type="checkbox" name="<?php echo esc_attr( "{$name}[disabled_design_tools][{$role}][]" ); ?>" value="custom_font_sizes" <?php checked( in_array( 'custom_font_sizes', $disabled, true ) ); ?>> <?php esc_html_e( 'No custom font sizes (theme sizes only)', 'acme-newsroom' ); ?></label>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button( __( 'Save editorial rules', 'acme-newsroom' ) ); ?>
			</form>
		</div>
		<?php
	}
}
