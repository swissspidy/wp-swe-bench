<?php
/**
 * Course Enroll Button block.
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
printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	wp_kses_post( acme_courses_enroll_button_html( $acme_course ) )
);
