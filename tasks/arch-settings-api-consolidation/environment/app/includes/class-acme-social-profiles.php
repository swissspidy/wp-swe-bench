<?php
/**
 * Social profile links ([acme_social_profiles] shortcode).
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders links to the site's social profiles.
 */
class Acme_Social_Profiles {

	/**
	 * Hooks.
	 */
	public function register() {
		add_shortcode( 'acme_social_profiles', array( $this, 'shortcode' ) );
	}

	/**
	 * Profile links in display order.
	 *
	 * @return array<string, array{label: string, url: string}>
	 */
	public function links() {
		$links  = array();
		$handle = acme_social_twitter_handle();
		if ( '' !== $handle ) {
			$links['twitter'] = array(
				'label' => __( 'X (Twitter)', 'acme-social' ),
				'url'   => 'https://x.com/' . $handle,
			);
		}
		$labels = array(
			'facebook'  => __( 'Facebook', 'acme-social' ),
			'instagram' => __( 'Instagram', 'acme-social' ),
			'linkedin'  => __( 'LinkedIn', 'acme-social' ),
			'youtube'   => __( 'YouTube', 'acme-social' ),
		);
		foreach ( acme_social_profile_urls() as $network => $url ) {
			if ( '' !== $url ) {
				$links[ $network ] = array(
					'label' => $labels[ $network ],
					'url'   => $url,
				);
			}
		}

		/**
		 * Filters the profile links.
		 *
		 * @since 1.1.0
		 *
		 * @param array $links Network => array( 'label' => ..., 'url' => ... ).
		 */
		return (array) apply_filters( 'acme_social_profile_links', $links );
	}

	/**
	 * [acme_social_profiles] output.
	 *
	 * @return string
	 */
	public function shortcode() {
		$links = $this->links();
		if ( ! $links ) {
			return '';
		}
		$html = '<ul class="acme-social-profiles">';
		foreach ( $links as $network => $link ) {
			$html .= sprintf(
				'<li class="acme-social-profiles__item acme-social-profiles__item--%1$s"><a href="%2$s" rel="me noopener" target="_blank">%3$s</a></li>',
				esc_attr( $network ),
				esc_url( $link['url'] ),
				esc_html( $link['label'] )
			);
		}
		$html .= '</ul>';
		return $html;
	}
}
