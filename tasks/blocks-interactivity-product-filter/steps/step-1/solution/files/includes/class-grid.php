<?php
/**
 * Product grid markup (shared by the block and the [acme_products] shortcode).
 *
 * @package Acme\Catalog
 */

namespace Acme\Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a product grid.
 *
 * The complete initial state (selected category, search text, visible
 * products, count) is rendered on the server; the view script module only
 * updates it when visitors interact with the grid. Server and client use the
 * same state and the same matching rules, so the grid never "jumps".
 */
class Grid {

	/**
	 * Store namespace of the front-end module.
	 */
	const STORE = 'acme/catalog';

	/**
	 * Id of the view script module (registered from block.json).
	 */
	const VIEW_MODULE = 'acme-product-grid-view-script-module';

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
	 * Lower-cased text the search matches against (name + SKU).
	 *
	 * @param array $product Product data.
	 * @return string
	 */
	public static function haystack( array $product ) {
		return mb_strtolower( $product['title'] . ' ' . $product['sku'], 'UTF-8' );
	}

	/**
	 * Does a product (as stored in the grid context) match category + search?
	 *
	 * Same rules as the view module: the category must be one of the
	 * product's categories ('' = all), and every word of the search text must
	 * appear in the name or SKU (case-insensitive).
	 *
	 * @param array  $product  Context product: [ 'id' => int, 'c' => string[], 'h' => string ].
	 * @param string $category Selected category slug.
	 * @param string $query    Search text.
	 * @return bool
	 */
	public static function matches( array $product, $category, $query ) {
		if ( '' !== $category && ! in_array( $category, $product['c'], true ) ) {
			return false;
		}
		$words = preg_split( '/\s+/u', mb_strtolower( trim( (string) $query ), 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $words as $word ) {
			if ( false === mb_strpos( $product['h'], $word, 0, 'UTF-8' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Products of a grid context that match its current state, in grid order.
	 *
	 * @param array $context Grid context.
	 * @return array
	 */
	public static function matching( array $context ) {
		$out = array();
		foreach ( $context['products'] as $product ) {
			if ( self::matches( $product, (string) $context['category'], (string) $context['query'] ) ) {
				$out[] = $product;
			}
		}
		return $out;
	}

	/**
	 * Server-side implementation of the derived state (mirrors view.js).
	 */
	public static function register_state() {
		wp_interactivity_state(
			self::STORE,
			array(
				'isVisible'      => static function () {
					$context = wp_interactivity_get_context();
					foreach ( $context['products'] as $product ) {
						$product_id = (int) ( $context['productId'] ?? 0 );
						if ( $product_id === (int) $product['id'] ) {
							return Grid::matches( $product, (string) $context['category'], (string) $context['query'] );
						}
					}
					return false;
				},
				'isActiveFilter' => static function () {
					$context = wp_interactivity_get_context();
					$slug = (string) ( $context['slug'] ?? '' );
					return $slug === (string) $context['category'];
				},
				'shownCount'     => static function () {
					return count( Grid::matching( wp_interactivity_get_context() ) );
				},
				'hasResults'     => static function () {
					return count( Grid::matching( wp_interactivity_get_context() ) ) > 0;
				},
				'countText'      => static function () {
					$context = wp_interactivity_get_context();
					return strtr(
						(string) $context['countTemplate'],
						array(
							'%1$s' => (string) count( Grid::matching( $context ) ),
							'%2$s' => (string) count( $context['products'] ),
						)
					);
				},
			)
		);
	}

	/**
	 * Render the grid (with interactivity directives, not yet processed).
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

		$slugs    = wp_list_pluck( $categories, 'slug' );
		$selected = in_array( $settings['defaultCategory'], $slugs, true ) ? $settings['defaultCategory'] : '';
		$total    = count( $products );

		$context = array(
			'category'      => $selected,
			'query'         => '',
			'countTemplate' => sprintf(
				/* translators: 1: number of shown products, 2: number of products in the grid. Keep the placeholders as they are. */
				_n( 'Showing %1$s of %2$s product', 'Showing %1$s of %2$s products', $total, 'acme-catalog' ),
				'%1$s',
				'%2$s'
			),
			'products'      => array_map(
				static function ( $product ) {
					return array(
						'id' => (int) $product['id'],
						'c'  => array_values( array_map( 'strval', $product['categories'] ) ),
						'h'  => Grid::haystack( $product ),
					);
				},
				$products
			),
		);

		self::register_state();

		$filters = array_merge(
			array(
				array(
					'slug' => '',
					'name' => __( 'All', 'acme-catalog' ),
				),
			),
			$categories
		);

		ob_start();
		?>
		<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-interactive="<?php echo esc_attr( self::STORE ); ?>" <?php echo wp_interactivity_data_wp_context( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( '' !== $settings['heading'] ) : ?>
				<h2 class="acme-grid__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>
			<div class="acme-grid__filters" role="group" aria-label="<?php esc_attr_e( 'Filter products by category', 'acme-catalog' ); ?>">
				<?php foreach ( $filters as $filter ) : ?>
					<button type="button" class="acme-grid__filter" data-category="<?php echo esc_attr( $filter['slug'] ); ?>" <?php echo wp_interactivity_data_wp_context( array( 'slug' => $filter['slug'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-bind--aria-pressed="state.isActiveFilter" data-wp-class--is-active="state.isActiveFilter" data-wp-on--click="actions.selectCategory"><?php echo esc_html( $filter['name'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php if ( $settings['showSearch'] ) : ?>
				<input type="search" class="acme-grid__search" placeholder="<?php esc_attr_e( 'Search products…', 'acme-catalog' ); ?>" aria-label="<?php esc_attr_e( 'Search products', 'acme-catalog' ); ?>" data-wp-bind--value="context.query" data-wp-on--input="actions.search" />
			<?php endif; ?>
			<p class="acme-grid__count" aria-live="polite" data-wp-text="state.countText"></p>
			<ul class="acme-grid__items" style="--acme-grid-columns:<?php echo (int) $settings['columns']; ?>">
				<?php foreach ( $products as $product ) : ?>
					<li class="acme-grid__item" data-product-id="<?php echo (int) $product['id']; ?>" <?php echo wp_interactivity_data_wp_context( array( 'productId' => (int) $product['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-wp-bind--hidden="!state.isVisible">
						<a class="acme-grid__link" href="<?php echo esc_url( $product['url'] ); ?>">
							<span class="acme-grid__title"><?php echo esc_html( $product['title'] ); ?></span>
							<span class="acme-grid__sku"><?php echo esc_html( $product['sku'] ); ?></span>
							<span class="acme-grid__price"><?php echo esc_html( $product['price_html'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="acme-grid__empty" data-wp-bind--hidden="state.hasResults"><?php esc_html_e( 'No products match your filters.', 'acme-catalog' ); ?></p>
		</div>
		<?php
		return trim( (string) ob_get_clean() );
	}
}
