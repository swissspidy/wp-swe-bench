<?php
/**
 * Product grid markup (shared by the block and the [acme_products] shortcode).
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a product grid. Filtering happens in the browser (view.js).
 */
class Grid {

	/**
	 * Handle of the front-end script (registered from block.json).
	 */
	const VIEW_SCRIPT = 'acme-product-grid-view-script';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'heading'         => '',
			'categories'      => array(),
			'defaultCategory' => '',
			'showSearch'      => true,
			'orderBy'         => 'title',
			'columns'         => 3,
		);
	}

	/**
	 * Normalize grid settings (block attributes or shortcode attributes mapped to them).
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function normalize( array $settings ) {
		$settings = array_merge( self::defaults(), $settings );

		$settings['heading']         = (string) $settings['heading'];
		$settings['categories']      = parse_slugs( $settings['categories'] );
		$settings['defaultCategory'] = sanitize_title( (string) $settings['defaultCategory'] );
		$settings['showSearch']      = (bool) $settings['showSearch'];
		$settings['orderBy']         = in_array( $settings['orderBy'], array( 'title', 'price', 'date' ), true ) ? $settings['orderBy'] : 'title';
		$settings['columns']         = max( 1, min( 6, (int) $settings['columns'] ) );
		return $settings;
	}

	/**
	 * Render the grid.
	 *
	 * @param array  $settings      Grid settings.
	 * @param string $wrapper_attrs Wrapper attributes (from the block supports), if any.
	 * @return string
	 */
	public static function render( array $settings, $wrapper_attrs = '' ) {
		$settings   = self::normalize( $settings );
		$products   = Products::query(
			array(
				'categories' => $settings['categories'],
				'order_by'   => $settings['orderBy'],
			)
		);
		$categories = Products::categories( $settings['categories'] );

		if ( '' === $wrapper_attrs ) {
			$wrapper_attrs = 'class="wp-block-acme-product-grid"';
		}

		$config = array(
			'defaultCategory' => $settings['defaultCategory'],
			'products'        => array_map(
				static function ( $product ) {
					return array(
						'id'         => $product['id'],
						'title'      => $product['title'],
						'sku'        => $product['sku'],
						'categories' => $product['categories'],
					);
				},
				$products
			),
		);

		ob_start();
		?>
		<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-acme-grid>
			<?php if ( '' !== $settings['heading'] ) : ?>
				<h2 class="acme-grid__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>
			<div class="acme-grid__filters" role="group" aria-label="<?php esc_attr_e( 'Filter products by category', 'acme-catalog' ); ?>">
				<button type="button" class="acme-grid__filter is-active" data-category=""><?php esc_html_e( 'All', 'acme-catalog' ); ?></button>
				<?php foreach ( $categories as $category ) : ?>
					<button type="button" class="acme-grid__filter" data-category="<?php echo esc_attr( $category['slug'] ); ?>"><?php echo esc_html( $category['name'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php if ( $settings['showSearch'] ) : ?>
				<input type="search" class="acme-grid__search" placeholder="<?php esc_attr_e( 'Search products…', 'acme-catalog' ); ?>" aria-label="<?php esc_attr_e( 'Search products', 'acme-catalog' ); ?>" />
			<?php endif; ?>
			<p class="acme-grid__count" aria-live="polite"></p>
			<ul class="acme-grid__items" style="--acme-grid-columns:<?php echo (int) $settings['columns']; ?>">
				<?php foreach ( $products as $product ) : ?>
					<li class="acme-grid__item" data-product-id="<?php echo (int) $product['id']; ?>">
						<a class="acme-grid__link" href="<?php echo esc_url( $product['url'] ); ?>">
							<span class="acme-grid__title"><?php echo esc_html( $product['title'] ); ?></span>
							<span class="acme-grid__sku"><?php echo esc_html( $product['sku'] ); ?></span>
							<span class="acme-grid__price"><?php echo esc_html( $product['price_html'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="acme-grid__empty" style="display:none"><?php esc_html_e( 'No products match your filters.', 'acme-catalog' ); ?></p>
			<script type="application/json" class="acme-grid__data"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		</div>
		<?php
		return trim( (string) ob_get_clean() );
	}

	/**
	 * Strings for the front-end script.
	 */
	public static function localize() {
		wp_localize_script(
			self::VIEW_SCRIPT,
			'acmeCatalogGrid',
			array(
				/* translators: 1: number of shown products, 2: number of products in the grid. */
				'countPlural'   => __( 'Showing %1$s of %2$s products', 'acme-catalog' ),
				/* translators: 1: number of shown products, 2: number of products in the grid (1). */
				'countSingular' => __( 'Showing %1$s of %2$s product', 'acme-catalog' ),
			)
		);
	}
}
