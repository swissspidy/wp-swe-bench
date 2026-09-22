<?php
/**
 * Server-side rendering of the callout block (all saved formats).
 *
 * @package Acme\Callouts
 *
 * @var array     $attributes Block attributes.
 * @var string    $content    Saved inner HTML (legacy formats) or rendered inner blocks (2.0+).
 * @var WP_Block  $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

echo Acme\Callouts\Renderer::render_block( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
