<?php
/**
 * Block patterns.
 *
 * TODO: move these into /patterns files one day, the inline strings are painful to edit.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme's pattern category and patterns.
 */
function acme_corporate_register_patterns() {
	register_block_pattern_category(
		'acme',
		array( 'label' => __( 'Acme', 'acme-corporate' ) )
	);

	register_block_pattern(
		'acme-corporate/hero',
		array(
			'title'      => __( 'Hero', 'acme-corporate' ),
			'categories' => array( 'acme', 'banner' ),
			'content'    => '<!-- wp:group {"align":"full","backgroundColor":"secondary","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-secondary-background-color has-background"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html__( 'Industrial solutions that scale', 'acme-corporate' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Acme has been building reliable equipment for over 70 years.', 'acme-corporate' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
		)
	);

	register_block_pattern(
		'acme-corporate/services',
		array(
			'title'      => __( 'Services', 'acme-corporate' ),
			'categories' => array( 'acme', 'services' ),
			'content'    => '<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Consulting', 'acme-corporate' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'We help you plan your next facility.', 'acme-corporate' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Manufacturing', 'acme-corporate' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Precision parts, delivered on time.', 'acme-corporate' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Maintenance', 'acme-corporate' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( '24/7 support for everything we build.', 'acme-corporate' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
		)
	);

	register_block_pattern(
		'acme-corporate/testimonials',
		array(
			'title'      => __( 'Testimonials', 'acme-corporate' ),
			'categories' => array( 'acme', 'testimonials' ),
			'content'    => '<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>' . esc_html__( 'Acme delivered our production line two weeks early.', 'acme-corporate' ) . '</p>
<!-- /wp:paragraph --><cite>' . esc_html__( 'Operations lead, Globex', 'acme-corporate' ) . '</cite></blockquote>
<!-- /wp:quote -->',
		)
	);

	register_block_pattern(
		'acme-corporate/cta',
		array(
			'title'      => __( 'Call to action', 'acme-corporate' ),
			'categories' => array( 'acme', 'call-to-action' ),
			'content'    => '<!-- wp:group {"backgroundColor":"primary","textColor":"base","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-base-color has-primary-background-color has-text-color has-background"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Ready to start your project?', 'acme-corporate' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact/">' . esc_html__( 'Talk to us', 'acme-corporate' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
		)
	);
}
add_action( 'init', 'acme_corporate_register_patterns' );
