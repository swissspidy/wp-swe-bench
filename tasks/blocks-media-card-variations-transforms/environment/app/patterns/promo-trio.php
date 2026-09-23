<?php
/**
 * Pattern: three promo cards in columns.
 *
 * @package Acme\MediaCard
 */

defined( 'ABSPATH' ) || exit;

$acme_card = static function ( $heading, $text ) {
	return '<!-- wp:acme/media-card -->' . "\n" .
		'<div class="wp-block-acme-media-card"><div class="wp-block-acme-media-card__content"><h3 class="wp-block-acme-media-card__heading">' . esc_html( $heading ) . '</h3><p class="wp-block-acme-media-card__text">' . esc_html( $text ) . '</p></div></div>' . "\n" .
		'<!-- /wp:acme/media-card -->';
};

return array(
	'title'      => __( 'Three promo cards', 'acme-media-card' ),
	'categories' => array( 'acme' ),
	'content'    => '<!-- wp:columns --><div class="wp-block-columns">' .
		'<!-- wp:column --><div class="wp-block-column">' . $acme_card( __( 'Fast', 'acme-media-card' ), __( 'Ships in two days.', 'acme-media-card' ) ) . '</div><!-- /wp:column -->' .
		'<!-- wp:column --><div class="wp-block-column">' . $acme_card( __( 'Friendly', 'acme-media-card' ), __( 'Real humans answer.', 'acme-media-card' ) ) . '</div><!-- /wp:column -->' .
		'<!-- wp:column --><div class="wp-block-column">' . $acme_card( __( 'Fair', 'acme-media-card' ), __( 'No hidden fees.', 'acme-media-card' ) ) . '</div><!-- /wp:column -->' .
		'</div><!-- /wp:columns -->',
);
