<?php
/**
 * Title: Header button (Customizer setting)
 * Slug: acme-corporate/header-cta
 * Inserter: no
 *
 * The header call-to-action from Appearance → Customize → Header, as a button.
 *
 * @package Acme_Corporate
 */

$acme_corporate_cta = acme_corporate_get_header_cta();
if ( ! $acme_corporate_cta ) {
	return;
}
?>
<!-- wp:buttons {"className":"header-cta-buttons"} -->
<div class="wp-block-buttons header-cta-buttons"><!-- wp:button {"className":"header-cta"} -->
<div class="wp-block-button header-cta"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $acme_corporate_cta['url'] ); ?>"><?php echo esc_html( $acme_corporate_cta['label'] ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
