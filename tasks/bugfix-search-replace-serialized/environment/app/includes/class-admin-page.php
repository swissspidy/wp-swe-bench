<?php
/**
 * Tools → Acme Migrate.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen with the search & replace form, the last report and the run history.
 */
class Admin_Page {

	const SLUG         = 'acme-migrate';
	const ACTION       = 'acme_migrate_run';
	const NOTICE_TRANS = 'acme_migrate_report_';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Menu entry.
	 */
	public function add_page() {
		add_management_page(
			__( 'Acme Migrate', 'acme-migrate' ),
			__( 'Acme Migrate', 'acme-migrate' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Styles.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'tools_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'acme-migrate-admin', ACME_MIGRATE_URL . 'assets/admin.css', array(), ACME_MIGRATE_VERSION );
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'acme-migrate' ), 403 );
		}

		$user_id = get_current_user_id();
		$last    = get_transient( self::NOTICE_TRANS . $user_id );
		if ( false !== $last ) {
			delete_transient( self::NOTICE_TRANS . $user_id );
		}

		$tables  = array_keys( Table_Map::get() );
		$history = History::get();

		include ACME_MIGRATE_DIR . 'views/admin-page.php';
	}

	/**
	 * Handle the form submission.
	 */
	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'acme-migrate' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$input   = isset( $_POST['acme_migrate'] ) && is_array( $_POST['acme_migrate'] ) ? wp_unslash( $_POST['acme_migrate'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- search/replace strings are used verbatim.
		$search  = isset( $input['search'] ) ? (string) $input['search'] : '';
		$replace = isset( $input['replace'] ) ? (string) $input['replace'] : '';
		$tables  = isset( $input['tables'] ) ? array_map( 'sanitize_text_field', (array) $input['tables'] ) : array();
		$dry_run = ! empty( $input['dryrun'] );

		$report = acme_migrate_run(
			$search,
			$replace,
			array(
				'tables'  => $tables,
				'dry_run' => $dry_run,
			)
		);

		if ( is_wp_error( $report ) ) {
			$notice = array( 'error' => $report->get_error_message() );
		} else {
			$notice = array(
				'report'  => $report->to_array(),
				'dry_run' => $dry_run,
				'summary' => acme_migrate_summary( $report, $dry_run ),
			);
		}
		set_transient( self::NOTICE_TRANS . get_current_user_id(), $notice, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'done' => 1 ), admin_url( 'tools.php' ) ) );
		exit;
	}
}
