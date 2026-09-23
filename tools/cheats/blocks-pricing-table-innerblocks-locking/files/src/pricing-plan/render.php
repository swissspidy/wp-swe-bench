<?php
/**
 * Server rendering of a pricing plan.
 *
 * @package Acme\Pricing
 *
 * @var array     $attributes Block attributes.
 * @var string    $content    Rendered feature list.
 * @var \WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

echo Acme\Pricing\Block::render_plan( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_plan().
