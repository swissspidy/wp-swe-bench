<?php
/**
 * Course Duration block.
 *
 * @package Acme\Courses
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$acme_course = Acme\Courses\Course::from_post( $block->context['postId'] ?? null );
if ( ! $acme_course ) {
	return;
}
$acme_duration = $acme_course->duration_label();
if ( '' === $acme_duration ) {
	return;
}
printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html( $acme_duration )
);
