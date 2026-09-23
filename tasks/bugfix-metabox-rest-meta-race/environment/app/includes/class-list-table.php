<?php
/**
 * Products list: columns, Quick Edit and Bulk Edit.
 *
 * @package Acme\ProductFields
 */

namespace Acme\ProductFields;

defined( 'ABSPATH' ) || exit;

/**
 * Price / stock / featured columns on the products list. Price and stock can be changed with
 * Quick Edit; stock and featured with Bulk Edit.
 */
class List_Table {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_box' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'bulk_edit_box' ), 10, 2 );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save_bulk_edit' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Quick Edit script.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ) {
		$screen = get_current_screen();
		if ( 'edit.php' !== $hook_suffix || ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'acme-pf-quick-edit', ACME_PF_URL . 'assets/quick-edit.js', array( 'jquery', 'inline-edit-post' ), ACME_PF_VERSION, true );
	}

	/**
	 * Columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['acme_pf_price']    = __( 'Price', 'acme-product-fields' );
				$new['acme_pf_sku']      = __( 'SKU', 'acme-product-fields' );
				$new['acme_pf_in_stock'] = __( 'Stock', 'acme-product-fields' );
				$new['acme_pf_featured'] = __( 'Featured', 'acme-product-fields' );
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Product ID.
	 */
	public function column( $column, $post_id ) {
		switch ( $column ) {
			case 'acme_pf_price':
				echo esc_html( acme_pf_get_price_html( $post_id ) );
				// Raw values for Quick Edit (see assets/quick-edit.js).
				printf(
					'<span class="hidden acme-pf-inline" data-price="%s" data-in-stock="%s"></span>',
					esc_attr( Fields::get( $post_id, 'price' ) ),
					acme_pf_is_in_stock( $post_id ) ? '1' : '0'
				);
				break;
			case 'acme_pf_sku':
				echo esc_html( Fields::get( $post_id, 'sku' ) );
				break;
			case 'acme_pf_in_stock':
				echo acme_pf_is_in_stock( $post_id ) ? esc_html__( 'In stock', 'acme-product-fields' ) : esc_html__( 'Out of stock', 'acme-product-fields' );
				break;
			case 'acme_pf_featured':
				echo acme_pf_is_featured( $post_id ) ? '<span class="dashicons dashicons-star-filled" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Featured', 'acme-product-fields' ) . '</span>' : '&mdash;';
				break;
		}
	}

	/**
	 * Quick Edit fields: price and stock.
	 *
	 * @param string $column    Column.
	 * @param string $post_type Post type.
	 */
	public function quick_edit_box( $column, $post_type ) {
		if ( Post_Type::POST_TYPE !== $post_type || 'acme_pf_price' !== $column ) {
			return;
		}
		wp_nonce_field( Metabox::NONCE_ACTION, Metabox::NONCE_NAME );
		?>
		<fieldset class="inline-edit-col-right acme-pf-quick-edit">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title"><?php esc_html_e( 'Price', 'acme-product-fields' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="acme_pf[price]" class="acme-pf-price" value="" /></span>
				</label>
				<label class="inline-edit-group">
					<input type="checkbox" name="acme_pf[in_stock]" class="acme-pf-in-stock" value="1" />
					<span class="checkbox-title"><?php esc_html_e( 'In stock', 'acme-product-fields' ); ?></span>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Bulk Edit fields: stock and featured.
	 *
	 * @param string $column    Column.
	 * @param string $post_type Post type.
	 */
	public function bulk_edit_box( $column, $post_type ) {
		if ( Post_Type::POST_TYPE !== $post_type || 'acme_pf_price' !== $column ) {
			return;
		}
		$options = array(
			''  => __( '— No Change —', 'acme-product-fields' ),
			'1' => __( 'Yes', 'acme-product-fields' ),
			'0' => __( 'No', 'acme-product-fields' ),
		);
		?>
		<fieldset class="inline-edit-col-right acme-pf-bulk-edit">
			<div class="inline-edit-col">
				<?php foreach ( array( 'in_stock', 'featured' ) as $key ) : ?>
					<?php $field = Fields::get_field( $key ); ?>
					<label class="inline-edit-group">
						<span class="title"><?php echo esc_html( $field['label'] ); ?></span>
						<select name="acme_pf_bulk[<?php echo esc_attr( $key ); ?>]">
							<?php foreach ( $options as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Apply the Bulk Edit fields (Bulk Edit saves every selected product).
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_bulk_edit( $post_id ) {
		// Core verified the bulk-posts nonce before bulk_edit_posts() saves the products.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_REQUEST['bulk_edit'] ) || empty( $_REQUEST['acme_pf_bulk'] ) || ! is_array( $_REQUEST['acme_pf_bulk'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$bulk = array_map( 'sanitize_text_field', wp_unslash( $_REQUEST['acme_pf_bulk'] ) );
		// phpcs:enable

		foreach ( array( 'in_stock', 'featured' ) as $key ) {
			Fields::set( $post_id, $key, ! empty( $bulk[ $key ] ) );
		}
	}
}
