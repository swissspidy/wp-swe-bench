<?php
/**
 * Product summary below the description. Themes can override this in
 * `{theme}/acme-product-fields/product-summary.php`.
 *
 * @package Acme\ProductFields
 *
 * @var \WP_Post $post Product.
 */

defined( 'ABSPATH' ) || exit;

$acme_pf_price = acme_pf_get_price_html( $post->ID );
$acme_pf_badge = acme_pf_get_badge( $post->ID );
?>
<div class="acme-product-summary">
	<?php if ( $acme_pf_price ) : ?>
		<p class="acme-product-price"><?php echo esc_html( $acme_pf_price ); ?></p>
	<?php endif; ?>
	<?php if ( $acme_pf_badge ) : ?>
		<p class="acme-product-badge"><?php echo esc_html( $acme_pf_badge ); ?></p>
	<?php endif; ?>
	<p class="acme-product-stock <?php echo acme_pf_is_in_stock( $post->ID ) ? 'is-in-stock' : 'is-out-of-stock'; ?>">
		<?php echo acme_pf_is_in_stock( $post->ID ) ? esc_html__( 'In stock', 'acme-product-fields' ) : esc_html__( 'Out of stock', 'acme-product-fields' ); ?>
	</p>
</div>
