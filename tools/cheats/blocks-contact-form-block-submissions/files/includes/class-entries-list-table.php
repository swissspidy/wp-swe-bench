<?php
/**
 * List table for the Contact entries screen.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Entries list table.
 */
class Entries_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'created_at' => __( 'Date', 'acme-contact' ),
			'email'      => __( 'Email', 'acme-contact' ),
			'form'       => __( 'Form', 'acme-contact' ),
			'fields'     => __( 'Message', 'acme-contact' ),
		);
	}

	/**
	 * Load items.
	 */
	public function prepare_items() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$total  = Entries::count( $search );
		$page   = $this->get_pagenum();

		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = Entries::query( $search, Entries_Admin::PER_PAGE, $page );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => Entries_Admin::PER_PAGE,
				'total_pages' => (int) ceil( $total / Entries_Admin::PER_PAGE ),
			)
		);
	}

	/**
	 * Row markup (adds the entry ID).
	 *
	 * @param array $item Entry.
	 */
	public function single_row( $item ) {
		printf( '<tr id="entry-%d">', (int) $item['id'] );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Default column.
	 *
	 * @param array  $item        Entry.
	 * @param string $column_name Column.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'created_at':
				return esc_html( get_date_from_gmt( $item['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
			case 'email':
				return '' !== $item['email'] ? sprintf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $item['email'] ), esc_html( $item['email'] ) ) : '—';
			case 'form':
				$title = get_the_title( (int) $item['post_id'] );
				return esc_html( ( '' !== $title ? $title : '#' . (int) $item['post_id'] ) . ' (' . $item['form_id'] . ')' );
			case 'fields':
				$lines = array();
				foreach ( $item['fields'] as $name => $value ) {
					$lines[] = '<strong>' . esc_html( $name ) . ':</strong> ' . esc_html( is_scalar( $value ) ? (string) $value : '' );
				}
				return implode( '<br />', $lines );
		}
		return '';
	}

	/**
	 * Message when empty.
	 */
	public function no_items() {
		esc_html_e( 'No entries found.', 'acme-contact' );
	}
}
