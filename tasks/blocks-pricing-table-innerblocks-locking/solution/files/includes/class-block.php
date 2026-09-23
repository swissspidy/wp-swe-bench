<?php
/**
 * Block registration.
 *
 * @package Acme\Pricing
 */

namespace Acme\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the pricing table + pricing plan blocks, renders them and passes settings to the editor.
 */
class Block {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
	}

	/**
	 * Register the block types from their (built) block.json.
	 */
	public function register() {
		register_block_type( ACME_PRICING_DIR . 'build/pricing-table' );
		register_block_type( ACME_PRICING_DIR . 'build/pricing-plan' );
	}

	/**
	 * Whether pricing tables are locked (structure and settings) for the current user.
	 *
	 * @return bool
	 */
	public static function is_locked_for_current_user() {
		return (bool) acme_pricing_get_option( 'lock_tables' ) && ! current_user_can( 'manage_options' );
	}

	/**
	 * Render the table wrapper around its (rendered) plans.
	 *
	 * @param array          $attributes Block attributes.
	 * @param string         $content    Rendered plans.
	 * @param \WP_Block|null $block      Block instance.
	 * @return string
	 */
	public static function render_table( $attributes, $content, $block = null ) {
		$currency = Currency::sanitize_code( isset( $attributes['currency'] ) ? $attributes['currency'] : 'USD' );
		$count    = 0;
		if ( $block instanceof \WP_Block ) {
			foreach ( $block->inner_blocks as $inner ) {
				if ( 'acme/pricing-plan' === $inner->name ) {
					++$count;
				}
			}
		}

		// Tables saved by 1.x (not re-saved since) have no plan blocks: their saved
		// markup is complete and must be output unchanged.
		if ( 0 === $count ) {
			return $content;
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => sprintf( 'acme-pricing acme-pricing--cols-%d acme-pricing--currency-%s', $count, strtolower( $currency ) ),
			)
		);
		return sprintf( '<div %s>%s</div>', $wrapper, $content );
	}

	/**
	 * Render one plan.
	 *
	 * @param array          $attributes Block attributes.
	 * @param string         $content    Rendered feature list.
	 * @param \WP_Block|null $block      Block instance.
	 * @return string
	 */
	public static function render_plan( $attributes, $content, $block = null ) {
		$attributes = wp_parse_args(
			is_array( $attributes ) ? $attributes : array(),
			array(
				'name'       => '',
				'price'      => '',
				'period'     => '',
				'featured'   => false,
				'buttonText' => '',
				'buttonUrl'  => '',
			)
		);
		$currency   = ( $block instanceof \WP_Block && ! empty( $block->context['acme/pricingCurrency'] ) ) ? $block->context['acme/pricingCurrency'] : acme_pricing_get_option( 'default_currency' );

		$classes = 'acme-pricing__plan' . ( ! empty( $attributes['featured'] ) ? ' is-featured' : '' );
		$html    = '<div ' . get_block_wrapper_attributes( array( 'class' => $classes ) ) . '>';
		$html   .= '<h3 class="acme-pricing__name">' . wp_kses( (string) $attributes['name'], self::inline_tags() ) . '</h3>';
		$html   .= '<p class="acme-pricing__price"><span class="acme-pricing__amount">' . esc_html( Currency::format( $attributes['price'], $currency ) ) . '</span>';
		if ( '' !== trim( (string) $attributes['period'] ) ) {
			$html .= '<span class="acme-pricing__period">' . wp_kses( (string) $attributes['period'], self::inline_tags() ) . '</span>';
		}
		$html .= '</p>';
		$html .= $content;
		$url   = esc_url( (string) $attributes['buttonUrl'] );
		if ( '' !== $url ) {
			$html .= '<a class="acme-pricing__button" href="' . $url . '">' . wp_kses( (string) $attributes['buttonText'], self::inline_tags() ) . '</a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Inline formatting allowed in plan texts.
	 *
	 * @return array
	 */
	private static function inline_tags() {
		return array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'br'     => array(),
		);
	}

	/**
	 * Data the editor script needs: currencies (with formatting rules) and the default currency.
	 *
	 * @return array
	 */
	public static function editor_settings() {
		$currencies = array();
		foreach ( acme_pricing_currencies() as $code => $rules ) {
			$currencies[ $code ] = array(
				'label'     => $rules['label'],
				'symbol'    => $rules['symbol'],
				'position'  => $rules['position'],
				'space'     => (bool) $rules['space'],
				'decimal'   => $rules['decimal'],
				'thousands' => $rules['thousands'],
				'whole'     => $rules['whole'],
			);
		}

		return array(
			'currencies'      => $currencies,
			'defaultCurrency' => acme_pricing_get_option( 'default_currency' ),
			'locked'          => self::is_locked_for_current_user(),
		);
	}

	/**
	 * Print the editor settings before the block's editor script.
	 */
	public function editor_data() {
		$handle = generate_block_asset_handle( 'acme/pricing-table', 'editorScript' );
		wp_add_inline_script(
			$handle,
			'window.acmePricing = ' . wp_json_encode( self::editor_settings() ) . ';',
			'before'
		);
	}
}
