<?php
/**
 * Product field definitions and storage.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * The product fields, where they are edited and how they are stored.
 *
 * Storage (post meta, one value per key):
 *
 * - `_acme_price`          Decimal string with two decimals ("129.00"), empty when not set.
 * - `_acme_sku`            Upper case SKU.
 * - `_acme_badge`          Short text shown next to the price ("New", "15% off"...).
 * - `_acme_featured`       Checkbox: "1" / "". Products imported from the 1.x shop have "on" / "off".
 * - `_acme_in_stock`       Checkbox: "1" / "". Products imported from the 1.x shop have "yes" / "no".
 *                          A product without the key is in stock.
 * - `_acme_internal_notes` Free text for the shop staff. Never shown on the front end or in the REST API.
 * - `_acme_supplier`       Supplier name. Staff only.
 */
class Fields {

	/**
	 * Field definitions.
	 *
	 * `sidebar`: edited in the block editor's "Product details" panel (and exposed in the REST API).
	 *
	 * @return array<string, array{meta_key: string, type: string, label: string, sidebar: bool, default: mixed}>
	 */
	public static function all() {
		$fields = array(
			'price'          => array(
				'meta_key' => '_acme_price', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'price',
				'label'    => __( 'Price', 'acme-product-fields' ),
				'sidebar'  => true,
				'default'  => '',
			),
			'sku'            => array(
				'meta_key' => '_acme_sku', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'text',
				'label'    => __( 'SKU', 'acme-product-fields' ),
				'sidebar'  => true,
				'default'  => '',
			),
			'badge'          => array(
				'meta_key' => '_acme_badge', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'text',
				'label'    => __( 'Badge text', 'acme-product-fields' ),
				'sidebar'  => true,
				'default'  => '',
			),
			'featured'       => array(
				'meta_key' => '_acme_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'checkbox',
				'label'    => __( 'Featured product', 'acme-product-fields' ),
				'sidebar'  => true,
				'default'  => false,
			),
			'in_stock'       => array(
				'meta_key' => '_acme_in_stock', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'checkbox',
				'label'    => __( 'In stock', 'acme-product-fields' ),
				'sidebar'  => true,
				'default'  => true,
			),
			'internal_notes' => array(
				'meta_key' => '_acme_internal_notes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'textarea',
				'label'    => __( 'Internal notes', 'acme-product-fields' ),
				'sidebar'  => false,
				'default'  => '',
			),
			'supplier'       => array(
				'meta_key' => '_acme_supplier', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'type'     => 'text',
				'label'    => __( 'Supplier', 'acme-product-fields' ),
				'sidebar'  => false,
				'default'  => '',
			),
		);

		/**
		 * Filters the product field definitions (labels only; keys and storage are fixed).
		 *
		 * @since 2.0.0
		 *
		 * @param array $fields Field definitions.
		 */
		return apply_filters( 'acme_pf_fields', $fields );
	}

	/**
	 * One field definition.
	 *
	 * @param string $key Field key.
	 * @return array|null
	 */
	public static function get_field( $key ) {
		$fields = self::all();
		return isset( $fields[ $key ] ) ? $fields[ $key ] : null;
	}

	/**
	 * Current value of a field, normalized (checkboxes as bool, everything else as string).
	 *
	 * @param int    $post_id Product ID.
	 * @param string $key     Field key.
	 * @return mixed
	 */
	public static function get( $post_id, $key ) {
		$field = self::get_field( $key );
		if ( ! $field ) {
			return null;
		}

		if ( ! metadata_exists( 'post', $post_id, $field['meta_key'] ) ) {
			return $field['default'];
		}

		$value = get_post_meta( $post_id, $field['meta_key'], true );

		if ( 'checkbox' === $field['type'] ) {
			return self::to_bool( $value );
		}

		return (string) $value;
	}

	/**
	 * Store a field.
	 *
	 * @param int    $post_id Product ID.
	 * @param string $key     Field key.
	 * @param mixed  $value   New value (sanitized here).
	 * @return bool
	 */
	public static function set( $post_id, $key, $value ) {
		$field = self::get_field( $key );
		if ( ! $field ) {
			return false;
		}

		$value = self::sanitize( $key, $value );
		if ( 'checkbox' === $field['type'] ) {
			$value = $value ? '1' : '';
		}

		// Meta values are expected slashed.
		return (bool) update_post_meta( $post_id, $field['meta_key'], wp_slash( $value ) );
	}

	/**
	 * Sanitize a value for a field.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( $key, $value ) {
		$field = self::get_field( $key );
		switch ( $field ? $field['type'] : 'text' ) {
			case 'price':
				return self::sanitize_price( $value );
			case 'checkbox':
				return self::to_bool( $value );
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
		}
		if ( 'sku' === $key ) {
			return self::sanitize_sku( $value );
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * "129", "129.5", "1,299.90" => "129.00", "129.50", "1299.90". Anything else => "".
	 *
	 * @param mixed $value Raw price.
	 * @return string
	 */
	public static function sanitize_price( $value ) {
		$value = trim( str_replace( array( ',', ' ', '$' ), '', (string) $value ) );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			return '';
		}
		return number_format( (float) $value, 2, '.', '' );
	}

	/**
	 * SKUs are upper case letters, digits and dashes.
	 *
	 * @param mixed $value Raw SKU.
	 * @return string
	 */
	public static function sanitize_sku( $value ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $value ) );
	}

	/**
	 * Interpret a stored or submitted checkbox value (including the 1.x "yes"/"no"/"on"/"off" values).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$value = strtolower( trim( (string) $value ) );
		return ! in_array( $value, array( '', '0', 'no', 'off', 'false' ), true );
	}
}
