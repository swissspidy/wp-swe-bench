<?php
/**
 * Structured data (JSON-LD) for pages that contain pricing tables.
 *
 * Our marketing team relies on this for rich results, and the SEO plugin on
 * the main site consumes the `acme_pricing_schema` filter.
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Collects the plans of all pricing tables in a post and prints a Product/Offer JSON-LD script.
 */
class Schema {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_head', array( $this, 'print_schema' ), 20 );
	}

	/**
	 * Print the JSON-LD script on singular views.
	 */
	public function print_schema() {
		if ( ! is_singular() || ! acme_pricing_get_option( 'schema' ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$data = self::for_post( $post );
		if ( ! $data ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json" class="acme-pricing-schema">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
	}

	/**
	 * Build the structured data for a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array|null Null when the post has no plans.
	 */
	public static function for_post( \WP_Post $post ) {
		if ( ! has_block( 'acme/pricing-table', $post ) && ! has_block( 'core/block', $post ) ) {
			return null;
		}

		$offers = self::offers_from_blocks( parse_blocks( $post->post_content ) );
		if ( ! $offers ) {
			return null;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => wp_strip_all_tags( get_the_title( $post ) ),
			'url'      => get_permalink( $post ),
			'offers'   => $offers,
		);

		/**
		 * Filters the pricing structured data of a post.
		 *
		 * @param array    $data Structured data.
		 * @param \WP_Post $post Post.
		 */
		return apply_filters( 'acme_pricing_schema', $data, $post );
	}

	/**
	 * Walk parsed blocks (recursively, following synced patterns once) and collect offers.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $seen   Synced pattern IDs already visited.
	 * @return array[]
	 */
	public static function offers_from_blocks( array $blocks, array &$seen = array() ) {
		$offers = array();
		foreach ( $blocks as $block ) {
			if ( 'acme/pricing-table' === $block['blockName'] ) {
				$offers = array_merge( $offers, self::offers_from_table( (array) $block['attrs'] ) );
			} elseif ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
				$ref = (int) $block['attrs']['ref'];
				if ( ! in_array( $ref, $seen, true ) ) {
					$seen[]  = $ref;
					$pattern = get_post( $ref );
					if ( $pattern && 'wp_block' === $pattern->post_type && 'publish' === $pattern->post_status ) {
						$offers = array_merge( $offers, self::offers_from_blocks( parse_blocks( $pattern->post_content ), $seen ) );
					}
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$offers = array_merge( $offers, self::offers_from_blocks( $block['innerBlocks'], $seen ) );
			}
		}
		return $offers;
	}

	/**
	 * Offers of one table, from its block attributes.
	 *
	 * @param array $attrs Block attributes.
	 * @return array[]
	 */
	public static function offers_from_table( array $attrs ) {
		$plans    = self::plans_from_attributes( $attrs );
		$currency = Currency::sanitize_code( isset( $attrs['currency'] ) ? $attrs['currency'] : 'USD' );
		$offers   = array();

		foreach ( $plans as $plan ) {
			$price = Currency::normalize_amount( isset( $plan['price'] ) ? $plan['price'] : '' );
			$name  = trim( wp_strip_all_tags( isset( $plan['name'] ) ? (string) $plan['name'] : '' ) );
			if ( '' === $price || '' === $name ) {
				continue;
			}
			$offers[] = array(
				'@type'         => 'Offer',
				'name'          => $name,
				'price'         => $price,
				'priceCurrency' => $currency,
			);
		}
		return $offers;
	}

	/**
	 * Normalize the stored attributes of a table to a list of plans.
	 *
	 * 1.3+ tables store a `plans` array of objects. Tables from 1.0–1.2 stored
	 * parallel arrays (`titles`, `prices`, …) and were always in US dollars.
	 *
	 * @param array $attrs Block attributes.
	 * @return array[] List of [ 'name' => …, 'price' => … ].
	 */
	public static function plans_from_attributes( array $attrs ) {
		if ( isset( $attrs['plans'] ) && is_array( $attrs['plans'] ) ) {
			$count = isset( $attrs['columns'] ) ? max( 1, (int) $attrs['columns'] ) : count( $attrs['plans'] );
			return array_slice( array_values( array_filter( $attrs['plans'], 'is_array' ) ), 0, $count );
		}

		$plans = array();
		if ( isset( $attrs['titles'] ) && is_array( $attrs['titles'] ) ) {
			foreach ( array_values( $attrs['titles'] ) as $i => $title ) {
				$plans[] = array(
					'name'  => $title,
					'price' => isset( $attrs['prices'][ $i ] ) ? $attrs['prices'][ $i ] : '',
				);
			}
		}
		return $plans;
	}
}
