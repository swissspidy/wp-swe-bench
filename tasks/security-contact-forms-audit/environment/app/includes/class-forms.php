<?php
/**
 * The form post type and its field definitions.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Forms are stored as `acme_form` posts; the field definitions live in post meta.
 *
 * A field definition is an array with these keys:
 * - `name`     (string) machine name, used as the key in submission data.
 * - `label`    (string) human label.
 * - `type`     (string) one of acme_forms_field_types().
 * - `required` (bool)
 * - `options`  (string[]) for `select` fields.
 * - `allowed`  (string) for `file` fields: comma-separated list of extensions, shown to the
 *              visitor in the file picker.
 *
 * Since 2.0 the definitions are stored as JSON in `_acme_form_fields`. Forms created with 1.x
 * still have a serialized PHP array in the same meta key (with `title` instead of `label`);
 * get_fields() understands both.
 */
class Forms {

	const POST_TYPE = 'acme_form';
	const META      = '_acme_form_fields';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Register the post type.
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Forms', 'acme-forms' ),
					'singular_name' => __( 'Form', 'acme-forms' ),
					'add_new_item'  => __( 'Add New Form', 'acme-forms' ),
					'edit_item'     => __( 'Edit Form', 'acme-forms' ),
					'all_items'     => __( 'All Forms', 'acme-forms' ),
					'menu_name'     => __( 'Acme Forms', 'acme-forms' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'acme-forms-submissions',
				'supports'     => array( 'title' ),
				'map_meta_cap' => true,
			)
		);
	}

	/**
	 * Fields of a form, normalized.
	 *
	 * @param int $form_id Form ID.
	 * @return array[]
	 */
	public static function get_fields( $form_id ) {
		$raw = get_post_meta( (int) $form_id, self::META, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$types  = acme_forms_field_types();
		$fields = array();
		foreach ( $raw as $field ) {
			if ( ! is_array( $field ) || empty( $field['name'] ) ) {
				continue;
			}
			$type     = isset( $field['type'] ) && isset( $types[ $field['type'] ] ) ? $field['type'] : 'text';
			$label    = isset( $field['label'] ) ? $field['label'] : ( isset( $field['title'] ) ? $field['title'] : $field['name'] );
			$fields[] = array(
				'name'     => sanitize_key( $field['name'] ),
				'label'    => (string) $label,
				'type'     => $type,
				'required' => ! empty( $field['required'] ),
				'options'  => isset( $field['options'] ) && is_array( $field['options'] ) ? array_values( array_map( 'strval', $field['options'] ) ) : array(),
				'allowed'  => isset( $field['allowed'] ) ? (string) $field['allowed'] : '',
			);
		}
		return $fields;
	}

	/**
	 * A single field definition by name.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $name    Field name.
	 * @return array|null
	 */
	public static function get_field( $form_id, $name ) {
		foreach ( self::get_fields( $form_id ) as $field ) {
			if ( $field['name'] === $name ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Whether notifications are enabled for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function notifications_enabled( $form_id ) {
		return '0' !== (string) get_post_meta( (int) $form_id, '_acme_form_notify', true );
	}

	/**
	 * All published forms (for filters).
	 *
	 * @return \WP_Post[]
	 */
	public static function all() {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'acme-form-fields', __( 'Fields', 'acme-forms' ), array( $this, 'render_fields_box' ), self::POST_TYPE, 'normal' );
		add_meta_box( 'acme-form-options', __( 'Options', 'acme-forms' ), array( $this, 'render_options_box' ), self::POST_TYPE, 'side' );
	}

	/**
	 * Field editor (a JSON textarea for now, a visual builder is on the roadmap).
	 *
	 * @param \WP_Post $post Form.
	 */
	public function render_fields_box( $post ) {
		wp_nonce_field( 'acme_form_save_' . $post->ID, 'acme_form_nonce' );
		$fields = self::get_fields( $post->ID );
		echo '<p>' . esc_html__( 'Field definitions as JSON (name, label, type, required, options, allowed).', 'acme-forms' ) . '</p>';
		printf(
			'<textarea name="acme_form_fields" rows="16" class="large-text code">%s</textarea>',
			esc_textarea( wp_json_encode( $fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) )
		);
		printf(
			'<p>%s <code>[acme_form id="%d"]</code></p>',
			esc_html__( 'Shortcode:', 'acme-forms' ),
			(int) $post->ID
		);
	}

	/**
	 * Options box.
	 *
	 * @param \WP_Post $post Form.
	 */
	public function render_options_box( $post ) {
		printf(
			'<label><input type="checkbox" name="acme_form_notify" value="1" %s /> %s</label>',
			checked( self::notifications_enabled( $post->ID ), true, false ),
			esc_html__( 'E-mail me about new submissions', 'acme-forms' )
		);
	}

	/**
	 * Save the meta boxes.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['acme_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acme_form_nonce'] ) ), 'acme_form_save_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( isset( $_POST['acme_form_fields'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['acme_form_fields'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_array( $decoded ) ) {
				update_post_meta( $post_id, self::META, wp_slash( wp_json_encode( $decoded ) ) );
			}
		}
		update_post_meta( $post_id, '_acme_form_notify', empty( $_POST['acme_form_notify'] ) ? '0' : '1' );
	}

	/**
	 * List columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['acme_shortcode']   = __( 'Shortcode', 'acme-forms' );
		$columns['acme_submissions'] = __( 'Submissions', 'acme-forms' );
		return $columns;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Form ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'acme_shortcode' === $column ) {
			printf( '<code>[acme_form id="%d"]</code>', (int) $post_id );
		} elseif ( 'acme_submissions' === $column ) {
			$count = Submissions::count( array( 'form_id' => $post_id ) );
			printf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=acme-forms-submissions&form_id=' . (int) $post_id ) ),
				esc_html( number_format_i18n( $count ) )
			);
		}
	}
}
