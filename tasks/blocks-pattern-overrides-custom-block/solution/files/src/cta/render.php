<?php
/**
 * Server rendering of the CTA block.
 *
 * @package Acme\CTA
 *
 * @var array    $attributes Block attributes (pattern overrides already applied).
 * @var string   $content    Saved markup.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

echo Acme\CTA\Plugin::instance()->block->renderer()->render( $attributes, $content, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
