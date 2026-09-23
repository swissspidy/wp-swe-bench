<?php
/**
 * Title: Footer text (Customizer setting)
 * Slug: acme-corporate/footer-text
 * Inserter: no
 *
 * The footer text from Appearance → Customize → Footer, {year} and {site} replaced.
 *
 * @package Acme_Corporate
 */

?>
<!-- wp:paragraph {"className":"site-info__text"} -->
<p class="site-info__text"><?php echo acme_corporate_get_footer_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses'd. ?></p>
<!-- /wp:paragraph -->
