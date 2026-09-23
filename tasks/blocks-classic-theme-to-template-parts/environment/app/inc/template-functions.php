<?php
/**
 * Data helpers used by the templates and the Customizer.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supported social networks: key => label.
 *
 * @return array<string, string>
 */
function acme_corporate_social_networks() {
	return array(
		'linkedin' => __( 'LinkedIn', 'acme-corporate' ),
		'twitter'  => __( 'Twitter', 'acme-corporate' ),
		'github'   => __( 'GitHub', 'acme-corporate' ),
		'youtube'  => __( 'YouTube', 'acme-corporate' ),
		'facebook' => __( 'Facebook', 'acme-corporate' ),
	);
}

/**
 * Social profile URLs, in display order, empty ones removed.
 *
 * Since 3.0 the URLs live in the `acme_corporate_social_links` theme mod (array).
 * Sites that were set up with 2.x still have the old single mods
 * (`acme_corporate_twitter_url`, `acme_corporate_facebook_url`,
 * `acme_corporate_youtube_url`); they are used when the new array has no value
 * for that network. The 2.x values were never sanitized on import.
 *
 * @return array<string, string> network => URL (unescaped).
 */
function acme_corporate_get_social_links() {
	$links = get_theme_mod( 'acme_corporate_social_links', array() );
	$links = is_array( $links ) ? $links : array();

	$out = array();
	foreach ( array_keys( acme_corporate_social_networks() ) as $network ) {
		$url = isset( $links[ $network ] ) ? trim( (string) $links[ $network ] ) : '';
		if ( '' === $url ) {
			$url = trim( (string) get_theme_mod( 'acme_corporate_' . $network . '_url', '' ) );
		}
		if ( '' !== $url ) {
			$out[ $network ] = $url;
		}
	}

	/**
	 * Filters the social links shown in the footer.
	 *
	 * @param array<string, string> $out network => URL.
	 */
	return apply_filters( 'acme_corporate_social_links', $out );
}

/**
 * Default footer text.
 *
 * @return string
 */
function acme_corporate_default_footer_text() {
	/* translators: {year} and {site} are placeholders, keep them. */
	return __( '&copy; {year} {site}. All rights reserved.', 'acme-corporate' );
}

/**
 * The footer text with its placeholders replaced: {year} → current year,
 * {site} → site title. Limited HTML (links, emphasis) is allowed.
 *
 * @return string HTML.
 */
function acme_corporate_get_footer_text() {
	$text = get_theme_mod( 'acme_corporate_footer_text', acme_corporate_default_footer_text() );
	$text = strtr(
		(string) $text,
		array(
			'{year}' => gmdate( 'Y' ),
			'{site}' => get_bloginfo( 'name' ),
		)
	);
	return wp_kses( $text, acme_corporate_footer_text_allowed_html() );
}

/**
 * HTML allowed in the footer text.
 *
 * @return array
 */
function acme_corporate_footer_text_allowed_html() {
	return array(
		'a'      => array(
			'href'  => true,
			'title' => true,
			'rel'   => true,
		),
		'em'     => array(),
		'strong' => array(),
		'br'     => array(),
	);
}

/**
 * The header call-to-action button, or null when not configured.
 *
 * @return array{label:string, url:string}|null
 */
function acme_corporate_get_header_cta() {
	$label = trim( (string) get_theme_mod( 'acme_corporate_header_cta_label', '' ) );
	$url   = trim( (string) get_theme_mod( 'acme_corporate_header_cta_url', '' ) );
	if ( '' === $label || '' === $url ) {
		return null;
	}
	if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
		$url = home_url( $url );
	}
	return array(
		'label' => $label,
		'url'   => $url,
	);
}

/**
 * Contact details from the Customizer.
 *
 * @return array{phone:string, email:string}
 */
function acme_corporate_get_contact() {
	return array(
		'phone' => trim( (string) get_theme_mod( 'acme_corporate_contact_phone', '' ) ),
		'email' => trim( (string) get_theme_mod( 'acme_corporate_contact_email', '' ) ),
	);
}

/**
 * A phone number as a tel: URI ("+1 (555) 010-2030" → "tel:+15550102030").
 *
 * @param string $phone Phone number.
 * @return string
 */
function acme_corporate_tel_uri( $phone ) {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
}

/**
 * Fallback for the primary menu when no menu is assigned: a list of pages.
 *
 * @param array $args wp_nav_menu() arguments.
 */
function acme_corporate_menu_fallback( $args ) {
	$pages = wp_list_pages(
		array(
			'title_li' => '',
			'echo'     => false,
			'depth'    => 1,
			'sort_column' => 'menu_order, post_title',
		)
	);
	if ( ! $pages ) {
		return;
	}
	printf( '<ul id="%s" class="%s">%s</ul>', esc_attr( $args['menu_id'] ?? 'primary-menu' ), esc_attr( $args['menu_class'] ?? 'menu' ), $pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Adds body classes.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function acme_corporate_body_classes( $classes ) {
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}
	if ( ! is_active_sidebar( 'sidebar-1' ) || is_page() ) {
		$classes[] = 'no-sidebar';
	}
	return $classes;
}
add_filter( 'body_class', 'acme_corporate_body_classes' );
