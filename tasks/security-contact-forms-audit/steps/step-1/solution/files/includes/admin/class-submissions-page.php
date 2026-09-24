<?php
/**
 * The Submissions screen (inbox list + single submission view).
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

use Acme\Forms\Formatter;
use Acme\Forms\Forms;
use Acme\Forms\Installer;
use Acme\Forms\Submissions;

defined( 'ABSPATH' ) || exit;

/**
 * Screen controller for admin.php?page=acme-forms-submissions.
 *
 * - list:   admin.php?page=acme-forms-submissions[&form_id=&status=&s=&paged=]
 * - view:   admin.php?page=acme-forms-submissions&action=view&submission=ID
 * - delete: admin.php?page=acme-forms-submissions&action=delete&submission=ID&_wpnonce=… (row action,
 *           see delete_url()) or the "Delete" bulk action (submission[]=ID&..., list table token).
 */
class Submissions_Page {

	/**
	 * List table (built on load).
	 *
	 * @var Submissions_List_Table|null
	 */
	protected $table = null;

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
	}

	/**
	 * Base URL of the screen.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . Admin::MENU_SLUG ) );
	}

	/**
	 * Runs before output (load-{hook}): handles actions, prepares the list table.
	 */
	public function load() {
		$action = $this->current_action();

		if ( 'delete' === $action ) {
			$this->handle_delete();
		}

		if ( 'view' !== $action ) {
			require_once __DIR__ . '/class-submissions-list-table.php';
			add_screen_option(
				'per_page',
				array(
					'default' => (int) acme_forms_get_setting( 'per_page', 20 ),
					'option'  => 'acme_forms_per_page',
				)
			);
			$this->table = new Submissions_List_Table();
		}
	}

	/**
	 * Current action (row action or bulk action from either dropdown).
	 *
	 * @return string
	 */
	protected function current_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && '-1' !== $_REQUEST[ $key ] && '' !== $_REQUEST[ $key ] ) {
				return sanitize_key( $_REQUEST[ $key ] );
			}
		}
		// phpcs:enable
		return '';
	}

	/**
	 * URL of the "Delete" row action for a submission (carries a one-time token for that
	 * submission, so a link on another site cannot trigger a deletion).
	 *
	 * @param int $id Submission ID.
	 * @return string
	 */
	public static function delete_url( $id ) {
		return wp_nonce_url(
			self::url(
				array(
					'action'     => 'delete',
					'submission' => (int) $id,
				)
			),
			'acme_forms_delete_' . (int) $id
		);
	}

	/**
	 * Delete one or more submissions, then redirect back to the list.
	 *
	 * Row actions carry a per-submission token, the bulk action the list table's form token.
	 * Requests without a valid token (e.g. forged from another site) are refused.
	 */
	protected function handle_delete() {
		if ( ! current_user_can( Installer::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to delete submissions.', 'acme-forms' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
		$raw = isset( $_REQUEST['submission'] ) ? wp_unslash( $_REQUEST['submission'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( is_array( $raw ) ) {
			check_admin_referer( 'bulk-submissions' );
			$ids = array_filter( array_map( 'absint', $raw ) );
		} else {
			$id = absint( $raw );
			check_admin_referer( 'acme_forms_delete_' . $id );
			$ids = array( $id );
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( Submissions::delete( $id ) ) {
				++$deleted;
			}
		}

		wp_safe_redirect( self::url( array( 'deleted' => $deleted ) ) );
		exit;
	}

	/**
	 * Persist the per-page screen option.
	 *
	 * @param mixed  $status Status.
	 * @param string $option Option.
	 * @param mixed  $value  Value.
	 * @return mixed
	 */
	public function save_screen_option( $status, $option, $value ) {
		return 'acme_forms_per_page' === $option ? min( 200, max( 1, (int) $value ) ) : $status;
	}

	/**
	 * Output.
	 */
	public function render() {
		if ( 'view' === $this->current_action() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_view( isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0 );
			return;
		}
		$this->render_list();
	}

	/**
	 * The inbox.
	 */
	protected function render_list() {
		$this->table->prepare_items();

		echo '<div class="wrap acme-forms-inbox">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Submissions', 'acme-forms' ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['deleted'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint( $_GET['deleted'] );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of submissions. */
						_n( '%d submission deleted.', '%d submissions deleted.', $count, 'acme-forms' ),
						$count
					)
				)
			);
		}

		$this->table->views();

		echo '<form id="acme-forms-submissions" method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( Admin::MENU_SLUG ) . '" />';
		$this->table->search_box( __( 'Search submissions', 'acme-forms' ), 'acme-forms-search' );
		$this->table->display();
		echo '</form>';

		$this->render_export_box();
		echo '</div>';
	}

	/**
	 * CSV export form.
	 */
	protected function render_export_box() {
		$forms = Forms::all();
		if ( ! $forms ) {
			return;
		}
		echo '<div class="acme-forms-export card">';
		echo '<h2>' . esc_html__( 'Export to CSV', 'acme-forms' ) . '</h2>';
		echo '<form id="acme-forms-export" method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="acme_forms_export" />';
		wp_nonce_field( Export::NONCE, '_wpnonce', false );
		echo '<p><label>' . esc_html__( 'Form', 'acme-forms' ) . ' <select name="form_id">';
		foreach ( $forms as $form ) {
			echo '<option value="' . esc_attr( $form->ID ) . '">' . esc_html( $form->post_title ) . '</option>';
		}
		echo '</select></label> ';
		echo '<label>' . esc_html__( 'From', 'acme-forms' ) . ' <input type="date" name="from" /></label> ';
		echo '<label>' . esc_html__( 'To', 'acme-forms' ) . ' <input type="date" name="to" /></label> ';
		submit_button( __( 'Download CSV', 'acme-forms' ), 'secondary', 'submit', false );
		echo '</p></form></div>';
	}

	/**
	 * A single submission.
	 *
	 * @param int $id Submission ID.
	 */
	protected function render_view( $id ) {
		$submission = Submissions::get( $id );
		echo '<div class="wrap acme-forms-submission">';
		if ( ! $submission ) {
			echo '<h1>' . esc_html__( 'Submission not found', 'acme-forms' ) . '</h1>';
			echo '<p><a href="' . esc_url( self::url() ) . '">' . esc_html__( '&larr; Back to submissions', 'acme-forms' ) . '</a></p></div>';
			return;
		}
		if ( 'new' === $submission->status ) {
			Submissions::mark_read( $submission->id );
		}
		$form = get_post( $submission->form_id );

		printf(
			'<h1>%s</h1>',
			esc_html(
				sprintf(
					/* translators: %d: submission ID. */
					__( 'Submission #%d', 'acme-forms' ),
					$submission->id
				)
			)
		);
		echo '<p class="acme-forms-submission__meta">';
		echo esc_html( $form ? $form->post_title : __( '(deleted form)', 'acme-forms' ) ) . ' &middot; ';
		echo esc_html( acme_forms_format_date( $submission->created_at ) );
		if ( $submission->ip ) {
			echo ' &middot; ' . esc_html( $submission->ip );
		}
		echo '</p>';

		echo '<table class="widefat striped acme-forms-submission__fields"><tbody>';
		foreach ( Formatter::rows( $submission ) as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row['field']['label'] ) . '</th>';
			echo '<td>' . Formatter::value_html( $row['field'], $row['value'], $row['file'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- formatted HTML.
		}
		echo '</tbody></table>';

		echo '<p class="acme-forms-submission__actions">';
		echo '<a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( '&larr; Back to submissions', 'acme-forms' ) . '</a> ';
		echo '<a class="button button-link-delete" href="' . esc_url( self::delete_url( $submission->id ) ) . '">' . esc_html__( 'Delete', 'acme-forms' ) . '</a>';
		echo '</p></div>';
	}
}
