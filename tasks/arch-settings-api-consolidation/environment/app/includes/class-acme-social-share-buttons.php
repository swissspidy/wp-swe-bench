<?php
/**
 * Share buttons below/above the content.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Appends/prepends share buttons to singular content.
 */
class Acme_Social_Share_Buttons {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_shortcode( 'acme_share', array( $this, 'shortcode' ) );
	}

	/**
	 * Adds the buttons to the main post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || ! $this->should_display( $post ) ) {
			return $content;
		}

		$buttons  = $this->render( $post );
		$position = acme_social_share_position();
		if ( 'before' === $position || 'both' === $position ) {
			$content = $buttons . $content;
		}
		if ( 'after' === $position || 'both' === $position ) {
			$content .= $buttons;
		}
		return $content;
	}

	/**
	 * [acme_share] renders the buttons anywhere (ignores the post type setting).
	 *
	 * @return string
	 */
	public function shortcode() {
		$post = get_post();
		if ( ! $post || ! acme_social_share_enabled() ) {
			return '';
		}
		return $this->render( $post );
	}

	/**
	 * Whether a post gets automatic buttons.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function should_display( $post ) {
		$display = acme_social_share_enabled()
			&& in_array( $post->post_type, acme_social_share_post_types(), true )
			&& ! get_post_meta( $post->ID, '_acme_social_hide_buttons', true );

		/**
		 * Filters whether share buttons are shown for a post.
		 *
		 * @since 1.3.0
		 *
		 * @param bool    $display Whether to display the buttons.
		 * @param WP_Post $post    The post.
		 */
		return (bool) apply_filters( 'acme_social_display_share_buttons', $display, $post );
	}

	/**
	 * Button markup.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function render( $post ) {
		$networks = acme_social_enabled_networks();
		if ( ! $networks ) {
			return '';
		}
		$available = acme_social_available_networks();
		$style     = acme_social_button_style();

		/** This filter is documented in includes/class-acme-social-open-graph.php */
		$url   = rawurlencode( (string) apply_filters( 'acme_social_share_url', get_permalink( $post ), $post ) );
		$title = rawurlencode( html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) );

		$html  = '<div class="acme-social-share acme-social-share--' . esc_attr( $style ) . '">';
		$html .= '<span class="acme-social-share__label">' . esc_html__( 'Share:', 'acme-social' ) . '</span>';
		foreach ( $networks as $slug ) {
			$network = $available[ $slug ];
			$href    = sprintf( $network['share_url'], $url, $title );
			$text    = 'icons' === $style
				? '<span class="screen-reader-text">' . esc_html( $network['label'] ) . '</span>'
				: '<span class="acme-social-share__text">' . esc_html( $network['label'] ) . '</span>';
			$icon    = 'text' === $style ? '' : '<span class="acme-social-share__icon" aria-hidden="true"></span>';
			$html   .= sprintf(
				'<a class="acme-social-share__link acme-social-share__link--%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s%4$s</a>',
				esc_attr( $slug ),
				esc_url( $href, array( 'http', 'https', 'mailto' ) ),
				$icon,
				$text
			);
		}
		$html .= '</div>';
		return $html;
	}
}
