<?php
/**
 * Extra columns on the Products list screen.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

defined( 'ABSPATH' ) || exit;

/**
 * "Dimensions" and "Certifications" columns.
 */
class Admin_Columns {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Add the columns after the title.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['acme_dimensions']     = __( 'Dimensions', 'acme-specs' );
				$out['acme_certifications'] = __( 'Certifications', 'acme-specs' );
			}
		}
		return $out;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Product ID.
	 */
	public function column( $column, $post_id ) {
		if ( 'acme_dimensions' !== $column && 'acme_certifications' !== $column ) {
			return;
		}
		$specs = Specs::get( $post_id );
		if ( 'acme_dimensions' === $column ) {
			echo $specs['dimensions'] ? esc_html( acme_specs_format_dimensions( $specs['dimensions'] ) ) : '&mdash;';
			return;
		}
		$codes = wp_list_pluck( $specs['certifications'], 'code' );
		echo $codes ? esc_html( implode( ', ', $codes ) ) : '&mdash;';
	}
}
