<?php
/**
 * Server rendering of the pricing table.
 *
 * @package Acme\Pricing
 *
 * @var array     $attributes Block attributes.
 * @var string    $content    Rendered plans.
 * @var \WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

echo Acme\Pricing\Block::render_table( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_table().
