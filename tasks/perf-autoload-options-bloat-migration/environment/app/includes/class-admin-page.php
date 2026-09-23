<?php
/**
 * Tools → Activity Log.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * The log screen, bulk delete and CSV export.
 */
class Admin_Page {

	/**
	 * Page slug.
	 */
	const PAGE = 'acme-activity-log';

	/**
	 * Store.
	 *
	 * @var Log_Store
	 */
	private $store;

	/**
	 * UI state.
	 *
	 * @var User_State
	 */
	private $state;

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Log_Store  $store Store.
	 * @param User_State $state UI state.
	 */
	public function __construct( Log_Store $store, User_State $state ) {
		$this->store = $store;
		$this->state = $state;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_acme_activity_export', array( $this, 'export' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Menu.
	 */
	public function menu() {
		$this->hook = add_management_page(
			__( 'Activity Log', 'acme-activity-log' ),
			__( 'Activity Log', 'acme-activity-log' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
		add_action( 'load-' . $this->hook, array( $this, 'load' ) );
	}

	/**
	 * CSS.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function assets( $hook ) {
		if ( $hook === $this->hook ) {
			wp_enqueue_style( 'acme-activity-admin', ACME_ACTIVITY_URL . 'assets/admin.css', array(), ACME_ACTIVITY_VERSION );
		}
	}

	/**
	 * Before output: rows-per-page changes and bulk actions.
	 */
	public function load() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view the activity log.', 'acme-activity-log' ), 403 );
		}

		$user_id = get_current_user_id();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view preference only.
		if ( isset( $_GET['per_page'] ) ) {
			$this->state->update( $user_id, array( 'per_page' => absint( $_GET['per_page'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- view preference only.
		if ( isset( $_GET['hide'] ) ) {
			$hide = array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_GET['hide'] ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->state->update( $user_id, array( 'hidden_columns' => $hide ) );
		}

		$action = $this->current_bulk_action();
		if ( 'delete' === $action ) {
			check_admin_referer( 'bulk-activity-entries' );
			$ids     = isset( $_POST['entry'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['entry'] ) ) : array();
			$deleted = $this->store->delete( $ids );
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, 'deleted' => $deleted ), admin_url( 'tools.php' ) ) );
			exit;
		}
	}

	/**
	 * Selected bulk action (top or bottom dropdown).
	 *
	 * @return string
	 */
	private function current_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller.
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_POST[ $key ] ) && '-1' !== $_POST[ $key ] ) {
				return sanitize_key( wp_unslash( $_POST[ $key ] ) );
			}
		}
		// phpcs:enable
		return '';
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		require_once ACME_ACTIVITY_DIR . 'includes/class-list-table.php';

		$user_id = get_current_user_id();
		$state   = $this->state->get( $user_id );
		$table   = new List_Table( $this->store, $state );
		$table->prepare_items();

		// Everything up to now is "seen".
		$this->state->update( $user_id, array( 'last_seen' => time() ) );

		$export_url = wp_nonce_url(
			add_query_arg( array_merge( array( 'action' => 'acme_activity_export' ), $table->get_filter_query_args() ), admin_url( 'admin-post.php' ) ),
			'acme_activity_export'
		);
		?>
		<div class="wrap acme-activity">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Activity Log', 'acme-activity-log' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'acme-activity-log' ); ?></a>
			<hr class="wp-header-end" />
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['deleted'] ) ) {
				printf(
					'<div class="notice notice-success"><p>%s</p></div>',
					/* translators: %d: number of entries */
					esc_html( sprintf( _n( '%d entry deleted.', '%d entries deleted.', absint( $_GET['deleted'] ), 'acme-activity-log' ), absint( $_GET['deleted'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				);
			}
			?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<?php $table->search_box( __( 'Search messages', 'acme-activity-log' ), 'acme-activity-search' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( add_query_arg( $table->get_filter_query_args(), admin_url( 'tools.php?page=' . self::PAGE ) ) ); ?>">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * CSV export of the (filtered) log.
	 */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export the activity log.', 'acme-activity-log' ), 403 );
		}
		check_admin_referer( 'acme_activity_export' );

		require_once ACME_ACTIVITY_DIR . 'includes/class-list-table.php';
		$args             = List_Table::query_args_from_request();
		$args['per_page'] = -1;
		$entries          = $this->store->query( $args )['entries'];

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=activity-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'id', 'time', 'user_id', 'action', 'object_type', 'object_id', 'message', 'ip' ) );
		foreach ( $entries as $entry ) {
			fputcsv(
				$out,
				array(
					$entry['id'],
					gmdate( 'Y-m-d H:i:s', $entry['time'] ),
					$entry['user_id'],
					$entry['action'],
					$entry['object_type'],
					$entry['object_id'],
					$entry['message'],
					$entry['ip'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
