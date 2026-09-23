<?php
/**
 * Block registration and front-end tweaks.
 *
 * @package Acme\MediaCard
 */

namespace Acme\MediaCard;

defined( 'ABSPATH' ) || exit;

/**
 * Registers acme/media-card and its block styles.
 */
class Block {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'render_block_acme/media-card', array( $this, 'external_cta_rel' ), 10, 2 );
	}

	/**
	 * Register the block and its styles.
	 */
	public function register() {
		$block = register_block_type( ACME_MEDIA_CARD_DIR . 'build/media-card' );
		if ( $block && ! empty( $block->editor_script_handles[0] ) ) {
			wp_set_script_translations( $block->editor_script_handles[0], 'acme-media-card', ACME_MEDIA_CARD_DIR . 'languages' );
		}

		register_block_style(
			'acme/media-card',
			array(
				'name'  => 'outlined',
				'label' => __( 'Outlined', 'acme-media-card' ),
			)
		);
		register_block_style(
			'acme/media-card',
			array(
				'name'  => 'elevated',
				'label' => __( 'Elevated', 'acme-media-card' ),
			)
		);
	}

	/**
	 * Add rel="noopener" and an "external" class to CTA links pointing to other sites.
	 *
	 * Done on output so that saved block markup stays stable.
	 *
	 * @param string $html  Block HTML.
	 * @param array  $block Parsed block.
	 * @return string
	 */
	public function external_cta_rel( $html, $block ) {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$home      = wp_parse_url( home_url(), PHP_URL_HOST );
		while ( $processor->next_tag( array( 'class_name' => 'wp-block-acme-media-card__cta' ) ) ) {
			$href = (string) $processor->get_attribute( 'href' );
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( $host && $host !== $home ) {
				$processor->set_attribute( 'rel', 'noopener' );
				$processor->add_class( 'is-external' );
			}
		}
		return $processor->get_updated_html();
	}
}
