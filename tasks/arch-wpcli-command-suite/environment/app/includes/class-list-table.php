<?php
/**
 * Redirects list table.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List of rules on Tools → Redirects.
 */
class List_Table extends \WP_List_Table {

	/**
	 * Repository.
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules Repository.
	 */
	public function __construct( Rule_Repository $rules ) {
		$this->rules = $rules;
		parent::__construct(
			array(
				'singular' => 'rule',
				'plural'   => 'rules',
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
			'cb'         => '<input type="checkbox" />',
			'source'     => __( 'Source', 'acme-redirects' ),
			'target'     => __( 'Target', 'acme-redirects' ),
			'match_type' => __( 'Match', 'acme-redirects' ),
			'status'     => __( 'Status', 'acme-redirects' ),
			'priority'   => __( 'Priority', 'acme-redirects' ),
			'hits'       => __( 'Hits', 'acme-redirects' ),
			'last_hit'   => __( 'Last hit', 'acme-redirects' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'source'   => array( 'source', false ),
			'priority' => array( 'priority', false ),
			'hits'     => array( 'hits', true ),
			'last_hit' => array( 'last_hit', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'enable'     => __( 'Enable', 'acme-redirects' ),
			'disable'    => __( 'Disable', 'acme-redirects' ),
			'reset_hits' => __( 'Reset hits', 'acme-redirects' ),
			'delete'     => __( 'Delete', 'acme-redirects' ),
		);
	}

	/**
	 * Match type / status filters.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
		$current_type   = isset( $_GET['match_type'] ) ? sanitize_key( $_GET['match_type'] ) : '';
		$current_status = isset( $_GET['status'] ) ? absint( $_GET['status'] ) : 0;
		// phpcs:enable
		echo '<div class="alignleft actions">';
		echo '<select name="match_type"><option value="">' . esc_html__( 'All match types', 'acme-redirects' ) . '</option>';
		foreach ( match_types() as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $current_type, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '<select name="status"><option value="">' . esc_html__( 'All status codes', 'acme-redirects' ) . '</option>';
		foreach ( status_codes() as $code => $label ) {
			printf( '<option value="%d" %s>%s</option>', (int) $code, selected( $current_status, $code, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( __( 'Filter', 'acme-redirects' ), '', 'filter_action', false, array( 'formaction' => Admin::url(), 'formmethod' => 'get' ) );
		echo '</div>';
	}

	/**
	 * Loads the items.
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'acme_redirects_per_page', 50 );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- list filters.
		$result = $this->rules->query(
			array(
				'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'match_type' => isset( $_GET['match_type'] ) ? sanitize_key( $_GET['match_type'] ) : '',
				'status'     => isset( $_GET['status'] ) ? absint( $_GET['status'] ) : 0,
				'orderby'    => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'priority',
				'order'      => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'asc',
				'per_page'   => $per_page,
				'page'       => $this->get_pagenum(),
			)
		);
		// phpcs:enable
		$this->items = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'source' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param Rule $item Rule.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="rule[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Source column with row actions.
	 *
	 * @param Rule $item Rule.
	 * @return string
	 */
	protected function column_source( $item ) {
		$edit    = Admin::url(
			array(
				'action' => 'edit',
				'id'     => $item->id,
			)
		);
		$delete  = wp_nonce_url( admin_url( 'admin-post.php?action=acme_redirects_delete&id=' . $item->id ), 'acme_redirects_delete_' . $item->id );
		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'acme-redirects' ) ),
			'delete' => sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( $delete ), esc_html__( 'Delete', 'acme-redirects' ) ),
		);
		$label   = sprintf( '<a class="row-title" href="%s"><code>%s</code></a>', esc_url( $edit ), esc_html( $item->source ) );
		if ( ! $item->enabled ) {
			$label .= ' — <span class="post-state">' . esc_html__( 'Disabled', 'acme-redirects' ) . '</span>';
		}
		return $label . $this->row_actions( $actions );
	}

	/**
	 * Other columns.
	 *
	 * @param Rule   $item        Rule.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'target':
				return $item->is_redirect() ? '<code>' . esc_html( $item->target ) . '</code>' : '—';
			case 'match_type':
				$types = match_types();
				return esc_html( $types[ $item->match_type ] ?? $item->match_type );
			case 'status':
				return esc_html( (string) $item->status );
			case 'priority':
				return esc_html( (string) $item->priority );
			case 'hits':
				return esc_html( number_format_i18n( $item->hits ) );
			case 'last_hit':
				return esc_html( format_date( $item->last_hit ) );
		}
		return '';
	}

	/**
	 * Row attributes (used by our support team's browser bookmarklets).
	 *
	 * @param Rule $item Rule.
	 */
	public function single_row( $item ) {
		printf( '<tr id="rule-%d" data-hits="%d">', (int) $item->id, (int) $item->hits );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No redirects found.', 'acme-redirects' );
	}
}
