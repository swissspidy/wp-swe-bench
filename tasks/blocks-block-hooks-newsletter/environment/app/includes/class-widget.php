<?php
/**
 * Classic widget.
 *
 * @package Acme\Newsletter
 */

namespace Acme\Newsletter;

defined( 'ABSPATH' ) || exit;

/**
 * "Newsletter signup" widget for classic themes' sidebars.
 */
class Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'acme_newsletter',
			__( 'Newsletter signup', 'acme-newsletter' ),
			array(
				'description'           => __( 'Newsletter signup form.', 'acme-newsletter' ),
				'show_instance_in_rest' => true,
			)
		);
	}

	/**
	 * Front end.
	 *
	 * @param array $args     Sidebar args.
	 * @param array $instance Widget settings.
	 */
	public function widget( $args, $instance ) {
		$heading = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== $heading ) {
			echo $args['before_title'] . esc_html( $heading ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo render_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'heading'   => '',
				'show_name' => false,
				'source'    => 'widget',
			)
		);
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Admin form.
	 *
	 * @param array $instance Widget settings.
	 * @return string
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : get_setting( 'heading' );
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" /></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title:', 'acme-newsletter' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
		return '';
	}

	/**
	 * Save.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		return array( 'title' => sanitize_text_field( $new_instance['title'] ?? '' ) );
	}
}
