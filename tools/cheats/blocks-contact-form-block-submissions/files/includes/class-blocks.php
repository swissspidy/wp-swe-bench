<?php
/**
 * Contact form block + field blocks: registration and server-side rendering.
 *
 * Posts store only block comments (the landing-page importer writes them directly), everything
 * visible is rendered here.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks.
 */
class Blocks {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register the blocks from their built block.json files.
	 */
	public function register_blocks() {
		$dir = ACME_CONTACT_DIR . 'build/blocks/';
		if ( ! is_dir( $dir . 'contact-form' ) ) {
			return;
		}
		register_block_type( $dir . 'contact-form', array( 'render_callback' => array( $this, 'render_form' ) ) );
		foreach ( array( 'field-text', 'field-email', 'field-textarea', 'field-select', 'field-checkbox' ) as $field ) {
			// Fields are rendered by their form.
			register_block_type( $dir . $field, array( 'render_callback' => '__return_empty_string' ) );
		}
	}

	/**
	 * Result of a previous no-JS submission of this form (from the redirect back).
	 *
	 * @param string $form_id Form ID.
	 * @return array{status:string, errors:array, values:array, message:string}
	 */
	private function state( $form_id ) {
		$state = array(
			'status'  => '',
			'errors'  => array(),
			'values'  => array(),
			'message' => '',
		);
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$status = isset( $_GET['acme_contact'] ) ? sanitize_key( $_GET['acme_contact'] ) : '';
		if ( 'sent' === $status && isset( $_GET['acme_form'] ) && sanitize_text_field( wp_unslash( $_GET['acme_form'] ) ) === $form_id ) {
			$state['status'] = 'sent';
		} elseif ( 'error' === $status && isset( $_GET['acme_state'] ) ) {
			$saved = get_transient( 'acme_contact_state_' . sanitize_key( $_GET['acme_state'] ) );
			if ( is_array( $saved ) && isset( $saved['form_id'] ) && $saved['form_id'] === $form_id ) {
				$state = array_merge( $state, $saved, array( 'status' => 'error' ) );
			}
		}
		// phpcs:enable
		return $state;
	}

	/**
	 * Render the form.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Inner content (unused, fields are rendered here).
	 * @param \WP_Block $block      Block.
	 * @return string
	 */
	public function render_form( $attributes, $content, $block ) {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$form    = Forms::from_block( $block->parsed_block, $post_id );
		if ( '' === $form['form_id'] || ! $form['fields'] ) {
			return '';
		}
		$state   = $this->state( $form['form_id'] );
		$prefix  = 'acme-contact-' . sanitize_html_class( $form['form_id'] );
		$labels  = array();
		$message = 'sent' === $state['status'] ? Block_Submissions::success_message( $form ) : '';

		$wrapper = get_block_wrapper_attributes(
			array(
				'id'    => $prefix,
				'class' => 'acme-contact-form',
			)
		);

		ob_start();
		?>
		<form <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-acme-contact-form data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-form-id="<?php echo esc_attr( $form['form_id'] ); ?>" data-endpoint="<?php echo esc_url( rest_url( 'acme-contact/v1/submissions' ) ); ?>">
			<input type="hidden" name="action" value="acme_contact_submit" />
			<input type="hidden" name="acme_post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
			<input type="hidden" name="acme_form_id" value="<?php echo esc_attr( $form['form_id'] ); ?>" />

			<div class="acme-contact-form__status" role="status" aria-live="polite"><?php echo '' !== $message ? '<p>' . esc_html( $message ) . '</p>' : ''; ?></div>
			<div class="acme-contact-form__errors" role="alert"<?php echo $state['errors'] || ( 'error' === $state['status'] && '' !== $state['message'] ) ? '' : ' hidden'; ?>>
				<?php if ( 'error' === $state['status'] ) : ?>
					<p><?php echo esc_html( $state['message'] ); ?></p>
					<?php if ( $state['errors'] ) : ?>
						<ul>
							<?php foreach ( $form['fields'] as $field ) : ?>
								<?php if ( isset( $state['errors'][ $field['name'] ] ) ) : ?>
									<li><a href="#<?php echo esc_attr( $prefix . '-' . $field['name'] ); ?>"><?php echo esc_html( $field['label'] . ': ' . $state['errors'][ $field['name'] ] ); ?></a></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php
			foreach ( $form['fields'] as $field ) {
				$value = isset( $state['values'][ $field['name'] ] ) ? (string) $state['values'][ $field['name'] ] : '';
				$error = isset( $state['errors'][ $field['name'] ] ) ? $state['errors'][ $field['name'] ] : '';
				$this->render_field( $field, $prefix, $value, $error );
			}
			?>

			<p class="acme-contact-form__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $prefix ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'acme-contact' ); ?></label>
				<input id="<?php echo esc_attr( $prefix ); ?>-website" type="text" name="acme_website" value="" tabindex="-1" autocomplete="off" />
			</p>
			<div class="acme-contact-form__actions">
				<button type="submit" class="wp-element-button"><?php echo esc_html( $form['submit_label'] ); ?></button>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render one field.
	 *
	 * @param array  $field  Field definition.
	 * @param string $prefix ID prefix.
	 * @param string $value  Current value.
	 * @param string $error  Error message ('' if none).
	 */
	private function render_field( array $field, $prefix, $value, $error ) {
		$id       = $prefix . '-' . $field['name'];
		$name     = 'acme_fields[' . $field['name'] . ']';
		$required = $field['required'] ? ' required' : '';
		$aria     = '';
		$classes  = 'acme-contact-field acme-contact-field--' . $field['type'] . ( '' !== $error ? ' has-error' : '' );
		$marker   = $field['required'] ? '<span class="acme-contact-field__required" aria-hidden="true"> *</span>' : '';
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" data-field="<?php echo esc_attr( $field['name'] ); ?>">
			<?php if ( 'checkbox' === $field['type'] ) : ?>
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( in_array( $value, array( '1', 'Yes' ), true ) ); ?><?php echo $required . $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> />
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo $marker; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></label>
			<?php else : ?>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><?php echo $marker; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></label>
				<?php if ( 'textarea' === $field['type'] ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="6" maxlength="<?php echo esc_attr( (string) Validator::MAX_TEXTAREA ); ?>"<?php echo $required . $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>><?php echo esc_textarea( $value ); ?></textarea>
				<?php elseif ( 'select' === $field['type'] ) : ?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required . $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
						<option value=""><?php esc_html_e( '— Please choose —', 'acme-contact' ); ?></option>
						<?php foreach ( $field['options'] as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $value, $option ); ?>><?php echo esc_html( $option ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="<?php echo 'email' === $field['type'] ? 'email' : 'text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) Validator::MAX_TEXT ); ?>"<?php echo 'email' === $field['type'] ? ' autocomplete="email"' : ''; ?><?php echo $required . $aria; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> />
				<?php endif; ?>
			<?php endif; ?>
			<p class="acme-contact-field__error" id="<?php echo esc_attr( $id . '-error' ); ?>"<?php echo '' === $error ? ' hidden' : ''; ?>><?php echo esc_html( $error ); ?></p>
		</div>
		<?php
	}
}
