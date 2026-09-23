<?php
/**
 * Server rendering of acme/toc.
 *
 * @package Acme\Toc
 *
 * @var array     $attributes Block attributes.
 * @var string    $content    Saved markup (TOCs saved by 1.x only).
 * @var \WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

echo \Acme\Toc\Renderer::render( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
