<?php
/**
 * Event date block: <time> with the event's start in the site's formats/timezone.
 *
 * @package Acme\Events
 *
 * @var array     $attributes Block attributes.
 * @var string    $content    Block content.
 * @var \WP_Block $block      Block instance.
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

$acme_event_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
$acme_event_label   = $acme_event_post_id ? format_event_date( $acme_event_post_id ) : '';

if ( '' === $acme_event_label ) {
	return;
}

printf(
	'<time %1$s datetime="%2$s">%3$s</time>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_attr( event_datetime_attr( $acme_event_post_id ) ),
	esc_html( $acme_event_label )
);
