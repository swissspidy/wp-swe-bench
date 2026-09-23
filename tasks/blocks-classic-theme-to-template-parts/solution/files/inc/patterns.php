<?php
/**
 * Block patterns: the pattern category, and the synced "Contact card" pattern.
 *
 * The patterns themselves live in /patterns (registered automatically by WordPress).
 * Their slugs must not change: page content embeds them by slug.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme's pattern category.
 */
function acme_corporate_register_pattern_categories() {
	register_block_pattern_category(
		'acme',
		array( 'label' => __( 'Acme', 'acme-corporate' ) )
	);
}
add_action( 'init', 'acme_corporate_register_pattern_categories', 9 );

/**
 * Creates the synced "Contact card" pattern once, from the Customizer contact
 * details. Never re-created: once it exists, editors own it (they may edit or
 * delete it).
 */
function acme_corporate_maybe_create_contact_card() {
	if ( get_option( 'acme_corporate_contact_card_id' ) || wp_installing() ) {
		return;
	}
	// Claim the flag first so concurrent requests don't create duplicates.
	if ( ! add_option( 'acme_corporate_contact_card_id', -1, '', false ) ) {
		return;
	}

	$contact = acme_corporate_get_contact();
	$content = '<!-- wp:heading {"level":3} -->' . "\n" . '<h3 class="wp-block-heading">' . esc_html__( 'Contact us', 'acme-corporate' ) . '</h3>' . "\n" . '<!-- /wp:heading -->';
	if ( $contact['phone'] ) {
		$content .= "\n\n" . '<!-- wp:paragraph {"className":"contact-card__phone"} -->' . "\n" . '<p class="contact-card__phone"><a href="' . esc_url( acme_corporate_tel_uri( $contact['phone'] ), array( 'tel' ) ) . '">' . esc_html( $contact['phone'] ) . '</a></p>' . "\n" . '<!-- /wp:paragraph -->';
	}
	if ( $contact['email'] && is_email( $contact['email'] ) ) {
		$content .= "\n\n" . '<!-- wp:paragraph {"className":"contact-card__email"} -->' . "\n" . '<p class="contact-card__email"><a href="' . esc_url( 'mailto:' . $contact['email'], array( 'mailto' ) ) . '">' . esc_html( $contact['email'] ) . '</a></p>' . "\n" . '<!-- /wp:paragraph -->';
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'wp_block',
			'post_status'  => 'publish',
			'post_title'   => __( 'Contact card', 'acme-corporate' ),
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		delete_option( 'acme_corporate_contact_card_id' );
		return;
	}

	if ( taxonomy_exists( 'wp_pattern_category' ) ) {
		$term = term_exists( 'Acme', 'wp_pattern_category' );
		if ( ! $term ) {
			$term = wp_insert_term( 'Acme', 'wp_pattern_category', array( 'slug' => 'acme' ) );
		}
		if ( ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), 'wp_pattern_category' );
		}
	}
	update_option( 'acme_corporate_contact_card_id', $post_id, false );
}
add_action( 'init', 'acme_corporate_maybe_create_contact_card', 20 );
