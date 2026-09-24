<?php
/**
 * Front-end form output (`[acme_form]` shortcode).
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Renders forms.
 */
class Renderer {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_shortcode( 'acme_form', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register the stylesheet (enqueued when a form is rendered).
	 */
	public function register_assets() {
		wp_register_style( 'acme-forms', ACME_FORMS_URL . 'assets/form.css', array(), ACME_FORMS_VERSION );
	}

	/**
	 * `[acme_form id="123"]`.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'acme_form' );
		$form = get_post( (int) $atts['id'] );
		if ( ! $form || Forms::POST_TYPE !== $form->post_type || 'publish' !== $form->post_status ) {
			return '';
		}
		wp_enqueue_style( 'acme-forms' );
		return $this->render( $form );
	}

	/**
	 * Form HTML.
	 *
	 * @param \WP_Post $form Form.
	 * @return string
	 */
	public function render( $form ) {
		$fields = Forms::get_fields( $form->ID );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$status = isset( $_GET['acme_form'] ) ? sanitize_key( $_GET['acme_form'] ) : '';
		$errors = isset( $_GET['acme_errors'] ) ? array_filter( explode( ',', sanitize_text_field( wp_unslash( $_GET['acme_errors'] ) ) ) ) : array();
		// phpcs:enable

		ob_start();
		echo '<form class="acme-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';

		if ( 'sent' === $status ) {
			echo '<p class="acme-form__notice acme-form__notice--success" role="status">' . esc_html__( 'Thank you! Your message has been sent.', 'acme-forms' ) . '</p>';
		} elseif ( 'invalid' === $status ) {
			echo '<p class="acme-form__notice acme-form__notice--error" role="alert">' . esc_html__( 'Please check the highlighted fields and try again.', 'acme-forms' ) . '</p>';
		}

		echo '<input type="hidden" name="action" value="acme_forms_submit" />';
		echo '<input type="hidden" name="acme_form_id" value="' . esc_attr( $form->ID ) . '" />';
		echo '<input type="hidden" name="acme_return" value="' . esc_url( get_permalink() ) . '" />';
		// Honeypot: humans don't see it, bots fill it in.
		echo '<p class="acme-form__hp" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'acme-forms' ) . ' <input type="text" name="acme_hp" value="" tabindex="-1" autocomplete="off" /></label></p>';

		foreach ( $fields as $field ) {
			$this->render_field( $field, in_array( $field['name'], $errors, true ) );
		}

		echo '<p class="acme-form__submit"><button type="submit">' . esc_html__( 'Send', 'acme-forms' ) . '</button></p>';
		echo '</form>';
		return (string) ob_get_clean();
	}

	/**
	 * One field.
	 *
	 * @param array $field     Definition.
	 * @param bool  $has_error Whether validation failed for it.
	 */
	protected function render_field( array $field, $has_error ) {
		$id       = 'acme-field-' . $field['name'];
		$name     = 'acme_fields[' . $field['name'] . ']';
		$required = $field['required'] ? ' required' : '';
		$class    = 'acme-form__field acme-form__field--' . $field['type'] . ( $has_error ? ' has-error' : '' );

		echo '<p class="' . esc_attr( $class ) . '">';
		echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . ( $field['required'] ? ' <span class="required">*</span>' : '' ) . '</label> ';

		switch ( $field['type'] ) {
			case 'textarea':
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="6"' . $required . '></textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $required . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<option value="">' . esc_html__( '— Select —', 'acme-forms' ) . '</option>';
				foreach ( $field['options'] as $option ) {
					echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
				}
				echo '</select>';
				break;
			case 'file':
				$accept = '';
				if ( $field['allowed'] ) {
					$exts   = array_filter( array_map( 'trim', explode( ',', $field['allowed'] ) ) );
					$accept = ' accept="' . esc_attr( '.' . implode( ',.', $exts ) ) . '"';
				}
				echo '<input type="file" id="' . esc_attr( $id ) . '" name="' . esc_attr( 'acme_file_' . $field['name'] ) . '"' . $accept . $required . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			default:
				$type = 'email' === $field['type'] ? 'email' : ( 'url' === $field['type'] ? 'url' : 'text' );
				echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $required . ' />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</p>';
	}
}
