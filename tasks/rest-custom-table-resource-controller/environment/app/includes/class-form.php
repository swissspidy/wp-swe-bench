<?php
/**
 * [acme_lead_form] shortcode: the "Talk to sales" form.
 *
 * @package Acme\Leads
 */

namespace Acme\Leads;

defined( 'ABSPATH' ) || exit;

/**
 * Lead form.
 */
class Form {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_shortcode( 'acme_lead_form', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register the script (enqueued by the shortcode).
	 */
	public function register_assets() {
		wp_register_script( 'acme-lead-form', ACME_LEADS_URL . 'assets/form.js', array(), ACME_LEADS_VERSION, true );
		wp_localize_script(
			'acme-lead-form',
			'acmeLeadForm',
			array(
				'endpoint' => esc_url_raw( rest_url( Rest::NAMESPACE_V1 . '/leads' ) ),
				'thanks'   => __( 'Thanks! Our sales team will get back to you shortly.', 'acme-leads' ),
				'error'    => __( 'Sorry, something went wrong. Please try again.', 'acme-leads' ),
			)
		);
	}

	/**
	 * Render the form.
	 *
	 * @param array|string $atts Attributes (title).
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'title' => __( 'Talk to sales', 'acme-leads' ) ), $atts, 'acme_lead_form' );
		wp_enqueue_script( 'acme-lead-form' );

		ob_start();
		?>
		<form class="acme-lead-form" novalidate>
			<h3 class="acme-lead-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<p>
				<label><?php esc_html_e( 'Name', 'acme-leads' ); ?> <input type="text" name="name" required maxlength="191" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Work email', 'acme-leads' ); ?> <input type="email" name="email" required /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'Company', 'acme-leads' ); ?> <input type="text" name="company" maxlength="191" /></label>
			</p>
			<p>
				<label><?php esc_html_e( 'How can we help?', 'acme-leads' ); ?> <textarea name="message" rows="4"></textarea></label>
			</p>
			<p class="acme-lead-form__hp" aria-hidden="true" style="position:absolute;left:-9999px">
				<label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
			</p>
			<p><button type="submit"><?php esc_html_e( 'Send', 'acme-leads' ); ?></button></p>
			<p class="acme-lead-form__status" role="status"></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
