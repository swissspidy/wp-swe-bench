<?php
/**
 * Signup block (server-rendered).
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/newsletter-signup.
 */
class Block {

	const NAME = 'acme/newsletter-signup';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block type from the build directory.
	 */
	public function register_block() {
		register_block_type(
			ACME_NEWSLETTER_DIR . 'build/signup',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render( $attributes ) {
		$placement = isset( $attributes['placement'] ) ? (string) $attributes['placement'] : '';
		$source    = 'block';

		if ( 'after_content' === $placement ) {
			// Added to the Single templates automatically: same rules as the classic placement.
			if ( ! $this->show_after_content() ) {
				return '';
			}
			$source = 'content';
		} elseif ( 'footer' === $placement ) {
			$source = 'footer';
		}

		$settings = get_settings();
		$heading  = isset( $attributes['heading'] ) && '' !== $attributes['heading'] ? $attributes['heading'] : $settings['heading'];
		$button   = isset( $attributes['buttonLabel'] ) && '' !== $attributes['buttonLabel'] ? $attributes['buttonLabel'] : $settings['button_label'];

		return render_form(
			array(
				'heading'            => $heading,
				'button_label'       => $button,
				'show_name'          => ! empty( $attributes['showName'] ),
				'source'             => $source,
				'wrapper_attributes' => get_block_wrapper_attributes( array( 'class' => 'acme-newsletter acme-newsletter--' . $source ) ),
			)
		);
	}

	/**
	 * Whether the automatically placed after-content block should output the form
	 * on the current front-end request.
	 *
	 * @return bool
	 */
	private function show_after_content() {
		if ( ! is_singular() ) {
			return true; // E.g. a preview of the template outside the main query.
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return true;
		}
		/** This filter is documented in includes/class-content.php */
		return (bool) apply_filters( 'acme_newsletter_auto_insert', ! Content::post_has_form( $post ), $post );
	}
}
