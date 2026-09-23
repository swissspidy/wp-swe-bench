<?php
/**
 * Title: Site tagline (Customizer setting)
 * Slug: acme-corporate/site-tagline
 * Inserter: no
 *
 * Shows the tagline unless it was switched off in the Customizer.
 *
 * @package Acme_Corporate
 */

if ( ! get_theme_mod( 'acme_corporate_show_tagline', true ) ) {
	return;
}
?>
<!-- wp:site-tagline {"className":"site-description"} /-->
