<?php
/**
 * Block patterns using the media card.
 *
 * @package Acme\MediaCard
 */

namespace Acme\MediaCard;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Acme" pattern category and card patterns from patterns/*.php.
 */
class Patterns {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register patterns.
	 */
	public function register() {
		register_block_pattern_category( 'acme', array( 'label' => __( 'Acme', 'acme-media-card' ) ) );
		foreach ( glob( ACME_MEDIA_CARD_DIR . 'patterns/*.php' ) as $file ) {
			$pattern = require $file;
			if ( is_array( $pattern ) && ! empty( $pattern['content'] ) ) {
				register_block_pattern( 'acme/' . basename( $file, '.php' ), $pattern );
			}
		}
	}
}
