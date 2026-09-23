<?php
/**
 * Template tags (echo functions) used by header.php / footer.php and the content templates.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site logo or title, plus the tagline when enabled.
 */
function acme_corporate_site_branding() {
	echo '<div class="site-branding">';
	if ( has_custom_logo() ) {
		the_custom_logo();
	}
	$tag = ( is_front_page() && is_home() ) ? 'h1' : 'p';
	printf(
		'<%1$s class="site-title"><a href="%2$s" rel="home">%3$s</a></%1$s>',
		tag_escape( $tag ),
		esc_url( home_url( '/' ) ),
		esc_html( get_bloginfo( 'name' ) )
	);
	$description = get_bloginfo( 'description', 'display' );
	if ( $description && get_theme_mod( 'acme_corporate_show_tagline', true ) ) {
		printf( '<p class="site-description">%s</p>', esc_html( $description ) );
	}
	echo '</div>';
}

/**
 * Header call-to-action button.
 */
function acme_corporate_header_cta() {
	$cta = acme_corporate_get_header_cta();
	if ( ! $cta ) {
		return;
	}
	printf(
		'<a class="header-cta" href="%s">%s</a>',
		esc_url( $cta['url'] ),
		esc_html( $cta['label'] )
	);
}

/**
 * Inline SVG icon for a social network.
 *
 * @param string $network Network key.
 * @return string
 */
function acme_corporate_social_icon( $network ) {
	$paths = array(
		'linkedin' => 'M4.98 3.5A2.5 2.5 0 1 1 5 8.5a2.5 2.5 0 0 1-.02-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.83-2.05 3.77-2.05C20.6 8.65 21 11.2 21 14.5V21h-4v-5.8c0-1.4-.03-3.2-1.95-3.2-1.95 0-2.25 1.52-2.25 3.1V21H9z',
		'twitter'  => 'M22 5.8c-.7.3-1.5.5-2.3.6.8-.5 1.5-1.3 1.8-2.2-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.4 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5 0 2 1.4 3.7 3.3 4.1-.6.2-1.2.2-1.9.1a4.1 4.1 0 0 0 3.8 2.8A8.2 8.2 0 0 1 2 18.3 11.6 11.6 0 0 0 8.3 20c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2z',
		'github'   => 'M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-1.7c-2.8.6-3.4-1.3-3.4-1.3-.4-1.2-1.1-1.5-1.1-1.5-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.6.3-1.1.6-1.3-2.2-.3-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.7 1a9.4 9.4 0 0 1 5 0c1.9-1.3 2.7-1 2.7-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.4 4.7-4.6 5 .4.3.7.9.7 1.9v2.8c0 .3.2.6.7.5A10 10 0 0 0 12 2z',
		'youtube'  => 'M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3z',
		'facebook' => 'M14 8V6.3c0-.8.2-1.3 1.4-1.3H17V2h-2.6C11.3 2 10 3.6 10 6.2V8H8v3h2v11h4V11h2.7l.3-3z',
	);
	if ( ! isset( $paths[ $network ] ) ) {
		return '';
	}
	return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $paths[ $network ] ) . '"/></svg>';
}

/**
 * Social profile links.
 */
function acme_corporate_social_links() {
	$links = acme_corporate_get_social_links();
	if ( ! $links ) {
		return;
	}
	$labels = acme_corporate_social_networks();
	echo '<ul class="social-links">';
	foreach ( $links as $network => $url ) {
		$url = esc_url( $url );
		if ( '' === $url ) {
			continue;
		}
		printf(
			'<li><a class="social-link social-link--%1$s" href="%2$s" rel="me noopener"><span class="screen-reader-text">%3$s</span>%4$s</a></li>',
			esc_attr( $network ),
			$url, // Already escaped.
			esc_html( $labels[ $network ] ?? $network ),
			acme_corporate_social_icon( $network ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
	echo '</ul>';
}

/**
 * Contact details (phone + email).
 */
function acme_corporate_contact_details() {
	$contact = acme_corporate_get_contact();
	if ( ! $contact['phone'] && ! $contact['email'] ) {
		return;
	}
	echo '<div class="contact-details">';
	if ( $contact['phone'] ) {
		printf( '<p class="contact-details__phone"><a href="%s">%s</a></p>', esc_url( acme_corporate_tel_uri( $contact['phone'] ), array( 'tel' ) ), esc_html( $contact['phone'] ) );
	}
	if ( $contact['email'] && is_email( $contact['email'] ) ) {
		printf( '<p class="contact-details__email"><a href="%s">%s</a></p>', esc_url( 'mailto:' . antispambot( $contact['email'] ), array( 'mailto' ) ), esc_html( antispambot( $contact['email'] ) ) );
	}
	echo '</div>';
}

/**
 * Footer text.
 */
function acme_corporate_footer_text() {
	echo '<p class="site-info__text">' . acme_corporate_get_footer_text() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd.
}

/**
 * Post date + author.
 */
function acme_corporate_posted_on() {
	printf(
		'<span class="posted-on"><time datetime="%1$s">%2$s</time></span> <span class="byline">%3$s</span>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() ),
		/* translators: %s: author name. */
		esc_html( sprintf( __( 'by %s', 'acme-corporate' ), get_the_author() ) )
	);
}
