<?php
/**
 * Front end: the specifications table below single products, and the
 * [acme_specs] shortcode used in buying guides.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * Renders spec tables.
 */
class Frontend {

	/**
	 * Transient prefix for rendered tables.
	 *
	 * Rendering is cheap-ish, but category pages with 40 [acme_specs]
	 * shortcodes were not (2.3.0).
	 */
	const CACHE_PREFIX = 'acme_specs_html_';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'the_content', array( $this, 'append_table' ), 20 );
		add_shortcode( 'acme_specs', array( $this, 'shortcode' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( __CLASS__, 'flush_cache' ) );
		add_action( 'acme_specs_saved', array( __CLASS__, 'flush_cache' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Styles.
	 */
	public function enqueue() {
		if ( is_singular( Post_Type::POST_TYPE ) ) {
			wp_enqueue_style( 'acme-specs', ACME_SPECS_URL . 'assets/specs.css', array(), ACME_SPECS_VERSION );
		}
	}

	/**
	 * Append the table to single product content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_table( $content ) {
		if ( ! is_singular( Post_Type::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		return $content . self::render_table( get_the_ID() );
	}

	/**
	 * [acme_specs id="123"].
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'acme_specs' );
		$id   = absint( $atts['id'] );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || Post_Type::POST_TYPE !== $post->post_type || ! is_post_publicly_viewable( $post ) ) {
			return '';
		}
		return self::render_table( $id );
	}

	/**
	 * Render (and cache) the specs table of a product.
	 *
	 * @param int $post_id Product ID.
	 * @return string HTML; empty when the product has no specs.
	 */
	public static function render_table( $post_id ) {
		$post_id = (int) $post_id;
		$cached  = get_transient( self::CACHE_PREFIX . $post_id );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$specs = Specs::get( $post_id );
		$html  = '';
		if ( $specs['dimensions'] || $specs['materials'] || $specs['certifications'] ) {
			$codes = Specs::certification_codes();
			ob_start();
			include ACME_SPECS_DIR . 'templates/specs-table.php';
			$html = (string) ob_get_clean();
		}

		set_transient( self::CACHE_PREFIX . $post_id, $html, 12 * HOUR_IN_SECONDS );
		return $html;
	}

	/**
	 * Forget a product's cached table.
	 *
	 * @param int $post_id Product ID.
	 */
	public static function flush_cache( $post_id ) {
		delete_transient( self::CACHE_PREFIX . (int) $post_id );
	}
}
