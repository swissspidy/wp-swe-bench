<?php
/**
 * Title: Footer widgets
 * Slug: acme-corporate/footer-widgets
 * Inserter: no
 *
 * The widgets of the "Footer" widget area (Appearance → Widgets), rendered live.
 *
 * @package Acme_Corporate
 */

$acme_corporate_widgets = wp_get_sidebars_widgets();
if ( empty( $acme_corporate_widgets['footer-1'] ) ) {
	return;
}
?>
<!-- wp:group {"className":"footer-widgets","layout":{"type":"default"}} -->
<div class="wp-block-group footer-widgets">
<?php foreach ( $acme_corporate_widgets['footer-1'] as $acme_corporate_widget_id ) : ?>
<!-- wp:legacy-widget <?php echo wp_json_encode( array( 'id' => (string) $acme_corporate_widget_id ) ); ?> /-->
<?php endforeach; ?>
</div>
<!-- /wp:group -->
