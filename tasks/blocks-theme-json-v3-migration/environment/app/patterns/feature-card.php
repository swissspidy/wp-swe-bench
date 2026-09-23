<?php
/**
 * Title: Feature card
 * Slug: acme-magazine/feature-card
 * Categories: acme-magazine
 *
 * @package Acme_Magazine
 */

?>
<!-- wp:group {"className":"is-style-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card"><!-- wp:paragraph {"className":"is-style-kicker"} -->
<p class="is-style-kicker"><?php esc_html_e( 'Feature', 'acme-magazine' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'A headline for the feature', 'acme-magazine' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'A short teaser for the story.', 'acme-magazine' ); ?> <a href="#"><?php esc_html_e( 'Read more', 'acme-magazine' ); ?></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
