<?php
/**
 * "Product specifications" meta box.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Meta box on the product edit screen (shown below the block editor).
 */
class Metabox {

	const NONCE_ACTION = 'acme_specs_save';
	const NONCE_FIELD  = 'acme_specs_nonce';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the box.
	 */
	public function add() {
		add_meta_box(
			'acme-specs',
			__( 'Product specifications', 'acme-specs' ),
			array( $this, 'render' ),
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Assets for the repeatable certification rows.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || Post_Type::POST_TYPE !== get_current_screen()->post_type ) {
			return;
		}
		wp_enqueue_style( 'acme-specs-admin', ACME_SPECS_URL . 'assets/admin.css', array(), ACME_SPECS_VERSION );
		wp_enqueue_script( 'acme-specs-admin', ACME_SPECS_URL . 'assets/admin.js', array(), ACME_SPECS_VERSION, true );
	}

	/**
	 * Render the box.
	 *
	 * @param \WP_Post $post Product.
	 */
	public function render( $post ) {
		$specs = Specs::get( $post->ID );
		$dims  = $specs['dimensions'] ? $specs['dimensions'] : array(
			'width'  => '',
			'height' => '',
			'depth'  => '',
			'unit'   => 'cm',
		);
		$codes     = Specs::certification_codes();
		$certs     = $specs['certifications'];
		$can_certs = Specs::user_can_edit_certifications( $post->ID );
		if ( $can_certs ) {
			// Always offer one empty row.
			$certs[] = array(
				'code'   => '',
				'issued' => '',
			);
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<fieldset class="acme-specs-box__dimensions">
			<legend><?php esc_html_e( 'Dimensions', 'acme-specs' ); ?></legend>
			<?php
			$axes = array(
				'width'  => __( 'Width', 'acme-specs' ),
				'height' => __( 'Height', 'acme-specs' ),
				'depth'  => __( 'Depth', 'acme-specs' ),
			);
			foreach ( $axes as $axis => $label ) :
				?>
				<label>
					<?php echo esc_html( $label ); ?>
					<input type="text" inputmode="decimal" size="6" name="acme_specs[<?php echo esc_attr( $axis ); ?>]" value="<?php echo esc_attr( '' === $dims[ $axis ] ? '' : acme_specs_format_number( $dims[ $axis ] ) ); ?>" />
				</label>
			<?php endforeach; ?>
			<label>
				<?php esc_html_e( 'Unit', 'acme-specs' ); ?>
				<select name="acme_specs[unit]">
					<?php foreach ( Specs::UNITS as $unit ) : ?>
						<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $dims['unit'], $unit ); ?>><?php echo esc_html( $unit ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</fieldset>

		<p>
			<label for="acme-specs-materials"><strong><?php esc_html_e( 'Materials', 'acme-specs' ); ?></strong> <span class="description"><?php esc_html_e( 'One per line.', 'acme-specs' ); ?></span></label><br />
			<textarea id="acme-specs-materials" name="acme_specs[materials]" rows="4" class="widefat"><?php echo esc_textarea( implode( "\n", $specs['materials'] ) ); ?></textarea>
		</p>

		<table class="acme-specs-box__certs widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Certification', 'acme-specs' ); ?></th>
					<th><?php esc_html_e( 'Issued', 'acme-specs' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'acme-specs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_values( $certs ) as $i => $cert ) : ?>
					<tr class="acme-specs-box__cert">
						<td>
							<select <?php disabled( ! $can_certs ); ?> name="acme_specs[certs][<?php echo (int) $i; ?>][code]">
								<option value=""><?php esc_html_e( '— None —', 'acme-specs' ); ?></option>
								<?php foreach ( $codes as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $cert['code'], $code ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="date" <?php disabled( ! $can_certs ); ?> name="acme_specs[certs][<?php echo (int) $i; ?>][issued]" value="<?php echo esc_attr( $cert['issued'] ); ?>" /></td>
						<td><input type="date" <?php disabled( ! $can_certs ); ?> name="acme_specs[certs][<?php echo (int) $i; ?>][expires]" value="<?php echo esc_attr( $cert['expires'] ?? '' ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $can_certs ) : ?>
			<p><button type="button" class="button acme-specs-box__add-cert"><?php esc_html_e( 'Add certification', 'acme-specs' ); ?></button></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Only editors can change certifications.', 'acme-specs' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the box.
	 *
	 * @param int      $post_id Product ID.
	 * @param \WP_Post $post    Product.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by Specs::save().
		$raw = isset( $_POST['acme_specs'] ) && is_array( $_POST['acme_specs'] ) ? wp_unslash( $_POST['acme_specs'] ) : array();

		$materials = isset( $raw['materials'] ) ? preg_split( '/\r\n|\r|\n/', (string) $raw['materials'] ) : array();

		$certs = array();
		$rows  = (array) ( $raw['certs'] ?? array() );
		if ( ! Specs::user_can_edit_certifications( $post_id ) ) {
			// Authors keep whatever certifications the product has.
			$rows = Specs::get( $post_id )['certifications'];
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['code'] ) || ! array_key_exists( (string) $row['code'], Specs::certification_codes() ) ) {
				continue;
			}
			$certs[] = array(
				'code'    => (string) $row['code'],
				'issued'  => (string) ( $row['issued'] ?? '' ),
				'expires' => (string) ( $row['expires'] ?? '' ),
			);
		}

		Specs::save(
			$post_id,
			array(
				'dimensions'     => array(
					'width'  => $raw['width'] ?? '',
					'height' => $raw['height'] ?? '',
					'depth'  => $raw['depth'] ?? '',
					'unit'   => $raw['unit'] ?? 'cm',
				),
				'materials'      => $materials,
				'certifications' => $certs,
			)
		);
	}
}
