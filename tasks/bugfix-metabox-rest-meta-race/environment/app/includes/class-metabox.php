<?php
/**
 * "Product details" meta box.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * The meta box shows all fields in the classic editor. In the block editor the sidebar edits the
 * sidebar fields, so the meta box only shows the staff fields.
 */
class Metabox {

	const NONCE_ACTION = 'acme_pf_save';
	const NONCE_NAME   = 'acme_pf_nonce';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the meta box.
	 */
	public function add() {
		add_meta_box( 'acme-product-details', __( 'Product details', 'acme-product-fields' ), array( $this, 'render' ), null, 'normal', 'high' );
	}

	/**
	 * Styles for the edit screen.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			wp_enqueue_style( 'acme-pf-admin', ACME_PF_URL . 'assets/admin.css', array(), ACME_PF_VERSION );
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Product.
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$screen       = get_current_screen();
		$block_editor = $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();

		echo '<div class="acme-pf-fields">';
		foreach ( Fields::all() as $key => $field ) {
			$value = Fields::get( $post->ID, $key );

			if ( $block_editor && $field['sidebar'] ) {
				/*
				 * The sidebar edits this field in the block editor. Keep the current value in the
				 * form, otherwise saving the meta box would clear it (see #41).
				 */
				if ( 'checkbox' === $field['type'] ) {
					if ( $value ) {
						printf( '<input type="hidden" name="acme_pf[%s]" value="1" />', esc_attr( $key ) );
					}
				} else {
					printf( '<input type="hidden" name="acme_pf[%s]" value="%s" />', esc_attr( $key ), esc_attr( $value ) );
				}
				continue;
			}

			self::render_field( $key, $field, $value );
		}
		echo '</div>';
	}

	/**
	 * Render one field.
	 *
	 * @param string $key   Field key.
	 * @param array  $field Definition.
	 * @param mixed  $value Current value.
	 */
	public static function render_field( $key, $field, $value ) {
		$id   = 'acme-pf-' . str_replace( '_', '-', $key );
		$name = 'acme_pf[' . $key . ']';

		echo '<p class="acme-pf-field acme-pf-field--' . esc_attr( $field['type'] ) . '">';
		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( $field['label'] )
				);
				break;
			case 'textarea':
				printf(
					'<label for="%1$s">%3$s</label><textarea id="%1$s" name="%2$s" rows="4" class="widefat">%4$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_html( $field['label'] ),
					esc_textarea( $value )
				);
				break;
			default:
				printf(
					'<label for="%1$s">%3$s</label><input type="text" id="%1$s" name="%2$s" value="%4$s" class="widefat" %5$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_html( $field['label'] ),
					esc_attr( $value ),
					'price' === $field['type'] ? 'inputmode="decimal"' : ''
				);
		}
		echo '</p>';
	}

	/**
	 * Save the meta box (classic editor form, and the block editor's meta box request).
	 *
	 * @param int      $post_id Product ID.
	 * @param \WP_Post $post    Product.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field in Fields::set().
		$input = isset( $_POST['acme_pf'] ) && is_array( $_POST['acme_pf'] ) ? $_POST['acme_pf'] : array();

		foreach ( Fields::all() as $key => $field ) {
			if ( 'checkbox' === $field['type'] ) {
				// Unchecked boxes are not submitted.
				if ( isset( $input[ $key ] ) ) {
					Fields::set( $post_id, $key, true );
				}
				continue;
			}
			Fields::set( $post_id, $key, isset( $input[ $key ] ) ? $input[ $key ] : '' );
		}

		/**
		 * Fires after the product fields were saved from the edit screen.
		 *
		 * @since 2.1.0
		 *
		 * @param int $post_id Product ID.
		 */
		do_action( 'acme_pf_saved', $post_id );
	}
}
