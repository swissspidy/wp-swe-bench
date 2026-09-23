<?php
/**
 * Title: Footer contact details and social links (Customizer settings)
 * Slug: acme-corporate/footer-contact
 * Inserter: no
 *
 * @package Acme_Corporate
 */

$acme_corporate_contact = acme_corporate_get_contact();
if ( $acme_corporate_contact['phone'] ) :
	?>
<!-- wp:paragraph {"className":"contact-details__phone"} -->
<p class="contact-details__phone"><a href="<?php echo esc_url( acme_corporate_tel_uri( $acme_corporate_contact['phone'] ), array( 'tel' ) ); ?>"><?php echo esc_html( $acme_corporate_contact['phone'] ); ?></a></p>
<!-- /wp:paragraph -->
	<?php
endif;
if ( $acme_corporate_contact['email'] && is_email( $acme_corporate_contact['email'] ) ) :
	?>
<!-- wp:paragraph {"className":"contact-details__email"} -->
<p class="contact-details__email"><a href="<?php echo esc_url( 'mailto:' . $acme_corporate_contact['email'], array( 'mailto' ) ); ?>"><?php echo esc_html( $acme_corporate_contact['email'] ); ?></a></p>
<!-- /wp:paragraph -->
	<?php
endif;

$acme_corporate_links = array();
foreach ( acme_corporate_get_social_links() as $acme_corporate_network => $acme_corporate_url ) {
	$acme_corporate_url = esc_url_raw( $acme_corporate_url, array( 'http', 'https' ) );
	if ( '' !== $acme_corporate_url ) {
		$acme_corporate_links[ $acme_corporate_network ] = $acme_corporate_url;
	}
}
if ( $acme_corporate_links ) :
	?>
<!-- wp:social-links {"className":"social-links"} -->
<ul class="wp-block-social-links social-links">
	<?php foreach ( $acme_corporate_links as $acme_corporate_network => $acme_corporate_url ) : ?>
<!-- wp:social-link <?php echo wp_json_encode( array( 'url' => $acme_corporate_url, 'service' => $acme_corporate_network ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT ); ?> /-->
	<?php endforeach; ?>
</ul>
<!-- /wp:social-links -->
	<?php
endif;
