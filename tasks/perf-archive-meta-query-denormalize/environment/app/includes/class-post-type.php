<?php
/**
 * The `acme_listing` post type.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Post type registration.
 */
class Post_Type {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'manage_' . Listing::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Listing::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Register.
	 */
	public function register_post_type() {
		register_post_type(
			Listing::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Listings', 'acme-real-estate' ),
					'singular_name' => __( 'Listing', 'acme-real-estate' ),
					'add_new_item'  => __( 'Add New Listing', 'acme-real-estate' ),
					'edit_item'     => __( 'Edit Listing', 'acme-real-estate' ),
				),
				'public'       => true,
				'has_archive'  => 'listings',
				'rewrite'      => array( 'slug' => 'listing' ),
				'menu_icon'    => 'dashicons-admin-home',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest' => false, // Edited with the classic screen + meta box.
			)
		);
	}

	/**
	 * Admin columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$date = $columns['date'];
		unset( $columns['date'] );
		$columns['acme_price']  = __( 'Price', 'acme-real-estate' );
		$columns['acme_city']   = __( 'City', 'acme-real-estate' );
		$columns['acme_status'] = __( 'Status', 'acme-real-estate' );
		$columns['date']        = $date;
		return $columns;
	}

	/**
	 * Admin column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column( $column, $post_id ) {
		$listing = Listing::get( $post_id );
		if ( ! $listing ) {
			return;
		}
		switch ( $column ) {
			case 'acme_price':
				echo esc_html( $listing->price_label() );
				break;
			case 'acme_city':
				echo esc_html( $listing->city() );
				break;
			case 'acme_status':
				echo esc_html( Listing::statuses()[ $listing->status() ] );
				break;
		}
	}
}
