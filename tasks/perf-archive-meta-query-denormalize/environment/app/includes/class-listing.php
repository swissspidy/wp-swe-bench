<?php
/**
 * A listing (an `acme_listing` post and its meta).
 *
 * Meta (the source of truth, other code reads it directly):
 * - `_acme_price`     int, USD. 0 or empty = "price on request". (Old imports: "425000.00".)
 * - `_acme_bedrooms`  int. Missing for land / commercial lots.
 * - `_acme_bathrooms` int.
 * - `_acme_sqft`      int.
 * - `_acme_city`      string, as listed in Listing::cities().
 * - `_acme_features`  see Features::normalize().
 * - `_acme_status`    'for-sale' | 'pending' | 'sold'.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Listing model.
 */
class Listing {

	const POST_TYPE = 'acme_listing';

	/**
	 * Meta keys.
	 */
	const META_PRICE     = '_acme_price';
	const META_BEDROOMS  = '_acme_bedrooms';
	const META_BATHROOMS = '_acme_bathrooms';
	const META_SQFT      = '_acme_sqft';
	const META_CITY      = '_acme_city';
	const META_FEATURES  = '_acme_features';
	const META_STATUS    = '_acme_status';

	/**
	 * Post.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Load a listing.
	 *
	 * @param int|\WP_Post $post Post or ID.
	 * @return Listing|null
	 */
	public static function get( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return new self( $post );
	}

	/**
	 * Listing statuses.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return array(
			'for-sale' => __( 'For sale', 'acme-real-estate' ),
			'pending'  => __( 'Sale pending', 'acme-real-estate' ),
			'sold'     => __( 'Sold', 'acme-real-estate' ),
		);
	}

	/**
	 * Cities we list in (the search form's dropdown).
	 *
	 * @return string[]
	 */
	public static function cities() {
		/**
		 * Filters the cities offered in the search form.
		 *
		 * @param string[] $cities City names.
		 */
		return apply_filters(
			'acme_re_cities',
			array( 'Capital City', "Coeur d'Alene", 'New York', 'North Haverbrook', 'Ogdenville', 'San Francisco', 'Shelbyville', 'Springfield' )
		);
	}

	/**
	 * Post ID.
	 *
	 * @return int
	 */
	public function id() {
		return (int) $this->post->ID;
	}

	/**
	 * Post.
	 *
	 * @return \WP_Post
	 */
	public function post() {
		return $this->post;
	}

	/**
	 * Price in USD, 0 = on request.
	 *
	 * @return int
	 */
	public function price() {
		return (int) get_post_meta( $this->id(), self::META_PRICE, true );
	}

	/**
	 * Bedrooms, null if not applicable.
	 *
	 * @return int|null
	 */
	public function bedrooms() {
		$value = get_post_meta( $this->id(), self::META_BEDROOMS, true );
		return '' === $value ? null : (int) $value;
	}

	/**
	 * Bathrooms.
	 *
	 * @return int
	 */
	public function bathrooms() {
		return (int) get_post_meta( $this->id(), self::META_BATHROOMS, true );
	}

	/**
	 * Living area.
	 *
	 * @return int
	 */
	public function sqft() {
		return (int) get_post_meta( $this->id(), self::META_SQFT, true );
	}

	/**
	 * City.
	 *
	 * @return string
	 */
	public function city() {
		return (string) get_post_meta( $this->id(), self::META_CITY, true );
	}

	/**
	 * Feature slugs.
	 *
	 * @return string[]
	 */
	public function features() {
		return Features::normalize( get_post_meta( $this->id(), self::META_FEATURES, true ) );
	}

	/**
	 * Status.
	 *
	 * @return string
	 */
	public function status() {
		$status = (string) get_post_meta( $this->id(), self::META_STATUS, true );
		return isset( self::statuses()[ $status ] ) ? $status : 'for-sale';
	}

	/**
	 * Formatted price.
	 *
	 * @return string
	 */
	public function price_label() {
		$price = $this->price();
		if ( $price <= 0 ) {
			return __( 'Price on request', 'acme-real-estate' );
		}
		return '$' . number_format_i18n( $price );
	}

	/**
	 * Public representation (REST, CLI export).
	 *
	 * @return array
	 */
	public function to_array() {
		$data = array(
			'id'          => $this->id(),
			'slug'        => $this->post->post_name,
			'title'       => get_the_title( $this->post ),
			'link'        => get_permalink( $this->post ),
			'price'       => $this->price(),
			'price_label' => $this->price_label(),
			'bedrooms'    => $this->bedrooms(),
			'bathrooms'   => $this->bathrooms(),
			'sqft'        => $this->sqft(),
			'city'        => $this->city(),
			'features'    => $this->features(),
			'status'      => $this->status(),
			'date'        => mysql_to_rfc3339( $this->post->post_date ),
		);

		/**
		 * Filters the public data of a listing.
		 *
		 * @param array   $data    Data.
		 * @param Listing $listing Listing.
		 */
		return apply_filters( 'acme_re_listing_data', $data, $this );
	}
}
