<?php
/**
 * Newsroom blocks (all server-rendered).
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the newsroom blocks.
 */
class Blocks {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the blocks from their built block.json files.
	 */
	public function register() {
		$callbacks = array(
			'dateline'        => array( $this, 'render_dateline' ),
			'boilerplate'     => array( $this, 'render_boilerplate' ),
			'media-contact'   => array( $this, 'render_media_contact' ),
			'breaking-banner' => array( $this, 'render_breaking_banner' ),
		);
		foreach ( $callbacks as $dir => $callback ) {
			register_block_type( ACME_NEWSROOM_DIR . 'build/' . $dir, array( 'render_callback' => $callback ) );
		}
	}

	/**
	 * "ZURICH, September 22, 2026 –"
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Content.
	 * @param \WP_Block $block      Block.
	 * @return string
	 */
	public function render_dateline( $attributes, $content, $block ) {
		$details = acme_newsroom_details();
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
		$city    = ! empty( $attributes['city'] ) ? $attributes['city'] : $details['city'];
		$time    = $post_id ? get_post_time( 'U', true, $post_id ) : time();

		return sprintf(
			'<p %1$s><span class="acme-dateline__city">%2$s</span>, <time datetime="%3$s">%4$s</time> –</p>',
			get_block_wrapper_attributes(),
			esc_html( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $city ) : strtoupper( $city ) ),
			esc_attr( gmdate( 'Y-m-d', $time ) ),
			esc_html( date_i18n( get_option( 'date_format' ), $time ) )
		);
	}

	/**
	 * "About Acme" boilerplate from the settings.
	 *
	 * @return string
	 */
	public function render_boilerplate() {
		$details = acme_newsroom_details();
		if ( '' === trim( $details['boilerplate'] ) ) {
			return '';
		}
		return sprintf(
			'<div %1$s><h2 class="acme-boilerplate__title">%2$s</h2>%3$s</div>',
			get_block_wrapper_attributes(),
			esc_html__( 'About Acme', 'acme-newsroom' ),
			wpautop( esc_html( $details['boilerplate'] ) )
		);
	}

	/**
	 * Media contact from the settings.
	 *
	 * @return string
	 */
	public function render_media_contact() {
		$d     = acme_newsroom_details();
		$lines = array();
		if ( $d['contact_name'] ) {
			$lines[] = esc_html( $d['contact_name'] );
		}
		if ( $d['contact_email'] ) {
			$lines[] = sprintf( '<a href="mailto:%1$s">%2$s</a>', esc_attr( antispambot( $d['contact_email'] ) ), esc_html( antispambot( $d['contact_email'] ) ) );
		}
		if ( $d['contact_phone'] ) {
			$lines[] = esc_html( $d['contact_phone'] );
		}
		if ( ! $lines ) {
			return '';
		}
		return sprintf(
			'<div %1$s><h2 class="acme-media-contact__title">%2$s</h2><p>%3$s</p></div>',
			get_block_wrapper_attributes(),
			esc_html__( 'Media contact', 'acme-newsroom' ),
			implode( '<br>', $lines )
		);
	}

	/**
	 * Breaking news / update banner.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public function render_breaking_banner( $attributes ) {
		$level  = isset( $attributes['level'] ) && 'update' === $attributes['level'] ? 'update' : 'breaking';
		$labels = array(
			'breaking' => __( 'Breaking:', 'acme-newsroom' ),
			'update'   => __( 'Update:', 'acme-newsroom' ),
		);
		$text   = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
		return sprintf(
			'<div %1$s><strong class="acme-breaking-banner__label">%2$s</strong> %3$s</div>',
			get_block_wrapper_attributes( array( 'class' => 'is-level-' . $level ) ),
			esc_html( $labels[ $level ] ),
			wp_kses( $text, array( 'a' => array( 'href' => true ), 'em' => array(), 'strong' => array() ) )
		);
	}
}
