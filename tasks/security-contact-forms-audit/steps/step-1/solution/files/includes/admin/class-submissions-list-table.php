<?php
/**
 * Submissions list table.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

use Acme\Forms\Forms;
use Acme\Forms\Submissions;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The inbox table.
 */
class Submissions_List_Table extends \WP_List_Table {

	/**
	 * Form titles by ID (cache for the current page).
	 *
	 * @var string[]
	 */
	protected $form_titles = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Request arguments for the repository.
	 *
	 * @return array
	 */
	protected function query_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		return array(
			'form_id' => isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0,
			'status'  => isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], array( 'new', 'read' ), true ) ? sanitize_key( $_REQUEST['status'] ) : '',
			'search'  => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'orderby' => isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'created_at',
			'order'   => isset( $_REQUEST['order'] ) ? sanitize_key( $_REQUEST['order'] ) : 'desc',
		);
		// phpcs:enable
	}

	/**
	 * Load the items.
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'acme_forms_per_page', (int) acme_forms_get_setting( 'per_page', 20 ) );
		$args     = $this->query_args();
		$total    = Submissions::count( $args );

		$this->items = Submissions::query(
			$args + array(
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'created_at' );
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'created_at' => __( 'Submitted', 'acme-forms' ),
			'form'       => __( 'Form', 'acme-forms' ),
			'email'      => __( 'E-mail', 'acme-forms' ),
			'summary'    => __( 'Summary', 'acme-forms' ),
			'status'     => __( 'Status', 'acme-forms' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'email'      => array( 'email', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'acme-forms' ) );
	}

	/**
	 * "All | New | Read" links.
	 *
	 * @return array
	 */
	protected function get_views() {
		$args    = $this->query_args();
		$current = $args['status'];
		$base    = array_filter( array( 'form_id' => $args['form_id'] ) );
		$views   = array();
		foreach (
			array(
				''     => __( 'All', 'acme-forms' ),
				'new'  => __( 'New', 'acme-forms' ),
				'read' => __( 'Read', 'acme-forms' ),
			) as $status => $label
		) {
			$count            = Submissions::count( $base + array( 'status' => $status ) );
			$url              = Submissions_Page::url( $base + array_filter( array( 'status' => $status ) ) );
			$views[ $status ? $status : 'all' ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( $url ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		return $views;
	}

	/**
	 * Form filter.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$args = $this->query_args();
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="acme-forms-filter-form">' . esc_html__( 'Filter by form', 'acme-forms' ) . '</label>';
		echo '<select name="form_id" id="acme-forms-filter-form">';
		echo '<option value="0">' . esc_html__( 'All forms', 'acme-forms' ) . '</option>';
		foreach ( Forms::all() as $form ) {
			printf( '<option value="%d"%s>%s</option>', (int) $form->ID, selected( $args['form_id'], $form->ID, false ), esc_html( $form->post_title ) );
		}
		echo '</select>';
		if ( $args['status'] ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $args['status'] ) . '" />';
		}
		submit_button( __( 'Filter', 'acme-forms' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="submission[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Date + row actions.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		$view    = Submissions_Page::url(
			array(
				'action'     => 'view',
				'submission' => $item->id,
			)
		);
		$delete  = Submissions_Page::delete_url( $item->id );
		$actions = array(
			'view'   => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'acme-forms' ) . '</a>',
			'delete' => '<a class="submitdelete" href="' . esc_url( $delete ) . '">' . esc_html__( 'Delete', 'acme-forms' ) . '</a>',
		);
		$date    = '<a class="row-title" href="' . esc_url( $view ) . '">' . esc_html( acme_forms_format_date( $item->created_at ) ) . '</a>';
		return $date . $this->row_actions( $actions );
	}

	/**
	 * Form title.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_form( $item ) {
		if ( ! isset( $this->form_titles[ $item->form_id ] ) ) {
			$form                                = get_post( $item->form_id );
			$this->form_titles[ $item->form_id ] = $form ? $form->post_title : __( '(deleted form)', 'acme-forms' );
		}
		return esc_html( $this->form_titles[ $item->form_id ] );
	}

	/**
	 * E-mail column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_email( $item ) {
		return $item->email ? esc_html( $item->email ) : '&mdash;';
	}

	/**
	 * First few values ("Label: value · Label: value").
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_summary( $item ) {
		$parts = array();
		foreach ( Forms::get_fields( $item->form_id ) as $field ) {
			if ( in_array( $field['type'], array( 'file', 'email' ), true ) || empty( $item->data[ $field['name'] ] ) ) {
				continue;
			}
			$parts[] = '<strong>' . esc_html( $field['label'] ) . ':</strong> ' . esc_html( acme_forms_excerpt( acme_forms_value_to_string( $item->data[ $field['name'] ] ), 60 ) );
			if ( count( $parts ) >= 3 ) {
				break;
			}
		}
		if ( ! $parts && $item->data ) {
			// Legacy rows of deleted forms: show the raw values.
			$parts[] = esc_html( acme_forms_excerpt( implode( ' · ', array_map( 'acme_forms_value_to_string', $item->data ) ), 120 ) );
		}
		return $parts ? implode( ' &middot; ', $parts ) : '&mdash;';
	}

	/**
	 * Status column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_status( $item ) {
		return 'new' === $item->status
			? '<span class="acme-forms-status acme-forms-status--new">' . esc_html__( 'New', 'acme-forms' ) . '</span>'
			: esc_html__( 'Read', 'acme-forms' );
	}

	/**
	 * Empty table.
	 */
	public function no_items() {
		esc_html_e( 'No submissions found.', 'acme-forms' );
	}
}
