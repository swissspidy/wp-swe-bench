<?php
/**
 * Customizer: header, footer, social links and contact details.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Customizer sections, settings and controls.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function acme_corporate_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport        = 'postMessage';
	$wp_customize->get_setting( 'blogdescription' )->transport = 'postMessage';

	// Tagline toggle lives next to the core title/tagline fields.
	$wp_customize->add_setting(
		'acme_corporate_show_tagline',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'acme_corporate_show_tagline',
		array(
			'label'   => __( 'Display tagline', 'acme-corporate' ),
			'section' => 'title_tagline',
			'type'    => 'checkbox',
		)
	);

	// Header button.
	$wp_customize->add_section(
		'acme_corporate_header',
		array(
			'title'    => __( 'Header', 'acme-corporate' ),
			'priority' => 60,
		)
	);
	$wp_customize->add_setting( 'acme_corporate_header_cta_label', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control(
		'acme_corporate_header_cta_label',
		array(
			'label'   => __( 'Button label', 'acme-corporate' ),
			'section' => 'acme_corporate_header',
		)
	);
	$wp_customize->add_setting( 'acme_corporate_header_cta_url', array( 'sanitize_callback' => 'acme_corporate_sanitize_url_or_path' ) );
	$wp_customize->add_control(
		'acme_corporate_header_cta_url',
		array(
			'label'   => __( 'Button link', 'acme-corporate' ),
			'section' => 'acme_corporate_header',
			'type'    => 'text',
		)
	);

	// Footer.
	$wp_customize->add_section(
		'acme_corporate_footer',
		array(
			'title'    => __( 'Footer', 'acme-corporate' ),
			'priority' => 120,
		)
	);
	$wp_customize->add_setting(
		'acme_corporate_footer_text',
		array(
			'default'           => acme_corporate_default_footer_text(),
			'sanitize_callback' => 'acme_corporate_sanitize_footer_text',
		)
	);
	$wp_customize->add_control(
		'acme_corporate_footer_text',
		array(
			'label'       => __( 'Footer text', 'acme-corporate' ),
			'description' => __( 'Use {year} for the current year and {site} for the site title.', 'acme-corporate' ),
			'section'     => 'acme_corporate_footer',
			'type'        => 'textarea',
		)
	);

	foreach ( acme_corporate_social_networks() as $network => $label ) {
		$id = 'acme_corporate_social_links[' . $network . ']';
		$wp_customize->add_setting( $id, array( 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control(
			$id,
			array(
				/* translators: %s: social network name. */
				'label'   => sprintf( __( '%s URL', 'acme-corporate' ), $label ),
				'section' => 'acme_corporate_footer',
				'type'    => 'url',
			)
		);
	}

	$wp_customize->add_setting( 'acme_corporate_contact_phone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control(
		'acme_corporate_contact_phone',
		array(
			'label'   => __( 'Phone', 'acme-corporate' ),
			'section' => 'acme_corporate_footer',
		)
	);
	$wp_customize->add_setting( 'acme_corporate_contact_email', array( 'sanitize_callback' => 'sanitize_email' ) );
	$wp_customize->add_control(
		'acme_corporate_contact_email',
		array(
			'label'   => __( 'Email', 'acme-corporate' ),
			'section' => 'acme_corporate_footer',
			'type'    => 'email',
		)
	);

	if ( isset( $wp_customize->selective_refresh ) ) {
		$wp_customize->selective_refresh->add_partial(
			'acme_corporate_footer_text',
			array(
				'selector'        => '.site-info__text',
				'render_callback' => 'acme_corporate_footer_text',
			)
		);
	}
}
add_action( 'customize_register', 'acme_corporate_customize_register' );

/**
 * Sanitizes the footer text (limited HTML).
 *
 * @param string $text Text.
 * @return string
 */
function acme_corporate_sanitize_footer_text( $text ) {
	return wp_kses( (string) $text, acme_corporate_footer_text_allowed_html() );
}

/**
 * Accepts absolute URLs and site-relative paths ("/contact/").
 *
 * @param string $value Value.
 * @return string
 */
function acme_corporate_sanitize_url_or_path( $value ) {
	$value = trim( (string) $value );
	if ( 0 === strpos( $value, '/' ) && 0 !== strpos( $value, '//' ) ) {
		return '/' . ltrim( sanitize_text_field( $value ), '/' );
	}
	return esc_url_raw( $value );
}
