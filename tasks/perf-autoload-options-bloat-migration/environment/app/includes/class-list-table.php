<?php
/**
 * The log table on Tools → Activity Log.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table.
 */
class List_Table extends \WP_List_Table {

	/**
	 * Store.
	 *
	 * @var Log_Store
	 */
	private $store;

	/**
	 * UI state of the current user.
	 *
	 * @var array
	 */
	private $state;

	/**
	 * Cache of user display names.
	 *
	 * @var array<int, string>
	 */
	private $user_names = array();

	/**
	 * Constructor.
	 *
	 * @param Log_Store $store Store.
	 * @param array     $state UI state.
	 */
	public function __construct( Log_Store $store, array $state ) {
		$this->store = $store;
		$this->state = $state;
		parent::__construct(
			array(
				'singular' => 'activity-entry',
				'plural'   => 'activity-entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Query args from the request (filters + search).
	 *
	 * @return array
	 */
	public static function query_args_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$args = array();
		if ( ! empty( $_REQUEST['filter_action'] ) ) {
			$args['action'] = sanitize_key( wp_unslash( $_REQUEST['filter_action'] ) );
		}
		if ( ! empty( $_REQUEST['filter_user'] ) ) {
			$args['user_id'] = absint( $_REQUEST['filter_user'] );
		}
		if ( isset( $_REQUEST['s'] ) && '' !== $_REQUEST['s'] ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		// phpcs:enable
		return $args;
	}

	/**
	 * Filters as query args for links.
	 *
	 * @return array
	 */
	public function get_filter_query_args() {
		$args = self::query_args_from_request();
		$out  = array();
		if ( isset( $args['action'] ) ) {
			$out['filter_action'] = $args['action'];
		}
		if ( isset( $args['user_id'] ) ) {
			$out['filter_user'] = $args['user_id'];
		}
		if ( isset( $args['search'] ) ) {
			$out['s'] = rawurlencode( $args['search'] );
		}
		return $out;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'time'    => __( 'Date', 'acme-activity-log' ),
			'user'    => __( 'User', 'acme-activity-log' ),
			'action'  => __( 'Action', 'acme-activity-log' ),
			'object'  => __( 'Object', 'acme-activity-log' ),
			'message' => __( 'Message', 'acme-activity-log' ),
			'ip'      => __( 'IP', 'acme-activity-log' ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'acme-activity-log' ) );
	}

	/**
	 * Load the page of entries.
	 */
	public function prepare_items() {
		$per_page = (int) $this->state['per_page'];
		$args     = self::query_args_from_request();

		$args['per_page'] = $per_page;
		$args['page']     = $this->get_pagenum();

		$result      = $this->store->query( $args );
		$this->items = $result['entries'];

		$this->_column_headers = array( $this->get_columns(), (array) $this->state['hidden_columns'], array() );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);
	}

	/**
	 * Filter dropdowns.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$args = self::query_args_from_request();
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="acme-filter-action"><?php esc_html_e( 'Filter by action', 'acme-activity-log' ); ?></label>
			<select name="filter_action" id="acme-filter-action">
				<option value=""><?php esc_html_e( 'All actions', 'acme-activity-log' ); ?></option>
				<?php foreach ( $this->store->distinct_actions() as $action ) : ?>
					<option value="<?php echo esc_attr( $action ); ?>" <?php selected( isset( $args['action'] ) ? $args['action'] : '', $action ); ?>><?php echo esc_html( $action ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'acme-activity-log' ), '', 'filter_submit', false, array( 'formmethod' => 'get' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Row markup: themes' admin CSS and our E2E scripts rely on the row ID.
	 *
	 * @param array $item Entry.
	 */
	public function single_row( $item ) {
		$classes = array( 'acme-activity-row', 'action-' . $item['action'] );
		if ( $item['time'] > (int) $this->state['last_seen'] ) {
			$classes[] = 'is-new';
		}
		printf( '<tr id="activity-entry-%d" class="%s">', (int) $item['id'], esc_attr( implode( ' ', $classes ) ) );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Checkbox.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="entry[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Date.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_time( $item ) {
		return sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( gmdate( 'c', $item['time'] ) ),
			esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['time'] ) )
		);
	}

	/**
	 * User.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_user( $item ) {
		$id = (int) $item['user_id'];
		if ( ! $id ) {
			return esc_html__( 'System', 'acme-activity-log' );
		}
		if ( ! isset( $this->user_names[ $id ] ) ) {
			$user                    = get_userdata( $id );
			$this->user_names[ $id ] = $user ? $user->display_name : '';
		}
		if ( '' === $this->user_names[ $id ] ) {
			/* translators: %d: user ID */
			return esc_html( sprintf( __( '(deleted user #%d)', 'acme-activity-log' ), $id ) );
		}
		$url = add_query_arg( array( 'page' => Admin_Page::PAGE, 'filter_user' => $id ), admin_url( 'tools.php' ) );
		return sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $this->user_names[ $id ] ) );
	}

	/**
	 * Action.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_action( $item ) {
		$events = Settings::events();
		$label  = isset( $events[ $item['action'] ] ) ? $events[ $item['action'] ] : $item['action'];
		return sprintf( '<span class="acme-activity-action">%s</span>', esc_html( $label ) );
	}

	/**
	 * Object.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_object( $item ) {
		if ( '' === $item['object_type'] ) {
			return '&mdash;';
		}
		return esc_html( $item['object_type'] . ( $item['object_id'] ? ' #' . $item['object_id'] : '' ) );
	}

	/**
	 * Message.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_message( $item ) {
		return sprintf( '<span class="acme-activity-message">%s</span>', esc_html( $item['message'] ) );
	}

	/**
	 * IP.
	 *
	 * @param array $item Entry.
	 * @return string
	 */
	protected function column_ip( $item ) {
		return esc_html( $item['ip'] );
	}

	/**
	 * Nothing found.
	 */
	public function no_items() {
		esc_html_e( 'No activity found.', 'acme-activity-log' );
	}
}
