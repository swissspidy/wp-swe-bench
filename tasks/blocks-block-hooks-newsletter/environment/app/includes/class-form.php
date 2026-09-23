<?php
/**
 * Signup form markup.
 *
 * The markup is a public contract: the Acme themes style it and the CRM team's
 * tooling scrapes `form.acme-newsletter__form` and its field names. Don't change
 * class names or field names without talking to them.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the signup form.
 */
class Form {

	const ACTION       = 'acme_newsletter_subscribe';
	const NONCE_ACTION = 'acme_newsletter_subscribe';
	const NONCE_FIELD  = '_acme_nonce';

	/**
	 * Render a form.
	 *
	 * @param array $args {
	 *     Optional. Form arguments. Missing values fall back to the settings.
	 *
	 *     @type string $heading            Heading text ('' for none).
	 *     @type string $description        Text under the heading.
	 *     @type string $button_label       Submit button label.
	 *     @type bool   $show_name          Whether to ask for the name.
	 *     @type string $source             Where the form is displayed (see get_sources()).
	 *     @type string $class              Extra CSS classes for the wrapper.
	 *     @type string $wrapper_attributes Pre-built wrapper attributes (blocks). Overrides $class.
	 * }
	 * @return string
	 */
	public static function render( array $args = array() ) {
		$settings = get_settings();
		$args     = wp_parse_args(
			$args,
			array(
				'heading'            => $settings['heading'],
				'description'        => $settings['description'],
				'button_label'       => $settings['button_label'],
				'show_name'          => (bool) $settings['show_name'],
				'source'             => 'content',
				'class'              => '',
				'wrapper_attributes' => '',
			)
		);

		/**
		 * Filters the form arguments before rendering.
		 *
		 * @param array $args Form arguments.
		 */
		$args = apply_filters( 'acme_newsletter_form_args', $args );

		$source = is_valid_source( $args['source'] ) ? $args['source'] : 'content';

		if ( '' !== $args['wrapper_attributes'] ) {
			$wrapper = $args['wrapper_attributes'];
		} else {
			$classes = trim( 'acme-newsletter acme-newsletter--' . $source . ' ' . $args['class'] );
			$wrapper = 'class="' . esc_attr( $classes ) . '"';
		}

		ob_start();
		?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above / by get_block_wrapper_attributes(). ?>>
		<?php if ( '' !== (string) $args['heading'] ) : ?>
	<h2 class="acme-newsletter__heading"><?php echo esc_html( $args['heading'] ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== (string) $args['description'] ) : ?>
	<p class="acme-newsletter__description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php echo self::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<form class="acme-newsletter__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
		<input type="hidden" name="acme_source" value="<?php echo esc_attr( $source ); ?>" />
		<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
		<?php if ( $args['show_name'] ) : ?>
		<p class="acme-newsletter__field">
			<label for="acme-newsletter-name"><?php esc_html_e( 'Name', 'acme-newsletter' ); ?></label>
			<input type="text" id="acme-newsletter-name" name="acme_name" autocomplete="name" />
		</p>
		<?php endif; ?>
		<p class="acme-newsletter__field">
			<label for="acme-newsletter-email"><?php esc_html_e( 'Email address', 'acme-newsletter' ); ?></label>
			<input type="email" id="acme-newsletter-email" name="acme_email" autocomplete="email" required />
		</p>
		<p class="acme-newsletter__hp" aria-hidden="true">
			<label for="acme-newsletter-website"><?php esc_html_e( 'Website', 'acme-newsletter' ); ?></label>
			<input type="text" id="acme-newsletter-website" name="acme_website" tabindex="-1" autocomplete="off" />
		</p>
		<p class="acme-newsletter__consent">
			<input type="checkbox" id="acme-newsletter-consent" name="acme_consent" value="1" required />
			<label for="acme-newsletter-consent"><?php echo esc_html( get_setting( 'consent_text' ) ); ?></label>
		</p>
		<p class="acme-newsletter__actions">
			<button type="submit" class="acme-newsletter__submit wp-element-button"><?php echo esc_html( $args['button_label'] ); ?></button>
		</p>
	</form>
</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Result notice after a submission (the handler redirects back with ?acme_newsletter=<status>).
	 *
	 * @return string
	 */
	private static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$status = isset( $_GET['acme_newsletter'] ) ? sanitize_key( wp_unslash( $_GET['acme_newsletter'] ) ) : '';
		if ( '' === $status ) {
			return '';
		}
		$messages = array(
			'subscribed' => get_setting( 'success_message' ),
			'exists'     => __( 'You are already subscribed.', 'acme-newsletter' ),
			'invalid'    => __( 'Please enter a valid email address and accept the terms.', 'acme-newsletter' ),
			'error'      => __( 'Something went wrong. Please try again.', 'acme-newsletter' ),
		);
		if ( ! isset( $messages[ $status ] ) ) {
			return '';
		}
		$type = 'subscribed' === $status ? 'success' : 'error';
		return sprintf(
			'<p class="acme-newsletter__notice acme-newsletter__notice--%1$s" role="status">%2$s</p>',
			esc_attr( $type ),
			esc_html( $messages[ $status ] )
		);
	}
}
