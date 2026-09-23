<?php
/**
 * Front end: price, badge and stock below the product description.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Appends the product summary to the content of single products.
 */
class Frontend {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'the_content', array( $this, 'append_summary' ), 20 );
	}

	/**
	 * Append the summary.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public function append_summary( $content ) {
		$post = get_post();
		if ( ! $post || Post_Type::POST_TYPE !== $post->post_type || ! is_singular( Post_Type::POST_TYPE ) || ! in_the_loop() ) {
			return $content;
		}

		ob_start();
		$template = locate_template( 'acme-product-fields/product-summary.php' );
		include $template ? $template : ACME_PF_DIR . 'templates/product-summary.php';
		return $content . ob_get_clean();
	}
}
