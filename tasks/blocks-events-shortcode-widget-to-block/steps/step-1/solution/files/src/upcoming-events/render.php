<?php
/**
 * Server rendering of the Upcoming Events block (same markup as the [acme_events] shortcode).
 *
 * @package Acme\Events
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

echo \Acme\Events\Blocks::render_upcoming_events( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates.
