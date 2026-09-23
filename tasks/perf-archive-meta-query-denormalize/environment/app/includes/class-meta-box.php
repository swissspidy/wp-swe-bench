<?php
/**
 * "Listing details" meta box on the edit screen.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Meta box.
 */
class Meta_Box {

	const NONCE = 'acme_re_listing_details';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . Listing::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Listing::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Add the box.
	 */
	public function add() {
		add_meta_box( 'acme-re-details', __( 'Listing details', 'acme-real-estate' ), array( $this, 'render' ), Listing::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Render.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render( $post ) {
		$listing = new Listing( $post );
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		$beds = $listing->bedrooms();
		?>
		<p><label><?php esc_html_e( 'Price (USD, 0 = on request)', 'acme-real-estate' ); ?> <input type="number" min="0" name="acme_re[price]" value="<?php echo esc_attr( $listing->price() ); ?>" /></label></p>
		<p><label><?php esc_html_e( 'Bedrooms (empty for land)', 'acme-real-estate' ); ?> <input type="number" min="0" name="acme_re[bedrooms]" value="<?php echo null === $beds ? '' : esc_attr( $beds ); ?>" /></label></p>
		<p><label><?php esc_html_e( 'Bathrooms', 'acme-real-estate' ); ?> <input type="number" min="0" name="acme_re[bathrooms]" value="<?php echo esc_attr( $listing->bathrooms() ); ?>" /></label></p>
		<p><label><?php esc_html_e( 'Living area (sq ft)', 'acme-real-estate' ); ?> <input type="number" min="0" name="acme_re[sqft]" value="<?php echo esc_attr( $listing->sqft() ); ?>" /></label></p>
		<p><label><?php esc_html_e( 'City', 'acme-real-estate' ); ?>
			<select name="acme_re[city]">
				<?php foreach ( Listing::cities() as $city ) : ?>
					<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $listing->city(), $city ); ?>><?php echo esc_html( $city ); ?></option>
				<?php endforeach; ?>
			</select>
		</label></p>
		<p><label><?php esc_html_e( 'Status', 'acme-real-estate' ); ?>
			<select name="acme_re[status]">
				<?php foreach ( Listing::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $listing->status(), $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label></p>
		<fieldset><legend><?php esc_html_e( 'Features', 'acme-real-estate' ); ?></legend>
			<?php foreach ( Features::all() as $slug => $label ) : ?>
				<label><input type="checkbox" name="acme_re[features][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $listing->features(), true ) ); ?> /> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE . '_nonce' ] ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$data = isset( $_POST['acme_re'] ) ? (array) wp_unslash( $_POST['acme_re'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.

		update_post_meta( $post_id, Listing::META_PRICE, absint( $data['price'] ?? 0 ) );
		if ( isset( $data['bedrooms'] ) && '' !== trim( (string) $data['bedrooms'] ) ) {
			update_post_meta( $post_id, Listing::META_BEDROOMS, absint( $data['bedrooms'] ) );
		} else {
			delete_post_meta( $post_id, Listing::META_BEDROOMS );
		}
		update_post_meta( $post_id, Listing::META_BATHROOMS, absint( $data['bathrooms'] ?? 0 ) );
		update_post_meta( $post_id, Listing::META_SQFT, absint( $data['sqft'] ?? 0 ) );
		$city = sanitize_text_field( $data['city'] ?? '' );
		update_post_meta( $post_id, Listing::META_CITY, in_array( $city, Listing::cities(), true ) ? $city : '' );
		$status = sanitize_key( $data['status'] ?? 'for-sale' );
		update_post_meta( $post_id, Listing::META_STATUS, isset( Listing::statuses()[ $status ] ) ? $status : 'for-sale' );
		update_post_meta( $post_id, Listing::META_FEATURES, Features::sanitize_list( $data['features'] ?? array() ) );
	}
}
