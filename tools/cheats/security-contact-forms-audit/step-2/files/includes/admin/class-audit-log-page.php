<?php
/**
 * Read-only audit log screen.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

use Acme\Forms\Audit_Log;

defined( 'ABSPATH' ) || exit;

/**
 * Acme Forms → Audit log (administrators only). Newest entries first, 50 per page.
 */
class Audit_Log_Page {

	const SLUG     = 'acme-forms-audit-log';
	const PER_PAGE = 50;

	/**
	 * Hooks (none: the screen is registered by Admin).
	 */
	public function register_hooks() {}

	/**
	 * Output.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to view the audit log.', 'acme-forms' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination only.
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$total   = Audit_Log::total();
		$pages   = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$entries = Audit_Log::entries( self::PER_PAGE, $page );

		echo '<div class="wrap acme-forms-audit-log">';
		echo '<h1>' . esc_html__( 'Audit log', 'acme-forms' ) . '</h1>';
		echo '<p>' . esc_html__( 'Deletions, exports and settings changes made in Acme Forms, newest first.', 'acme-forms' ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( __( 'Date (UTC)', 'acme-forms' ), __( 'User', 'acme-forms' ), __( 'Action', 'acme-forms' ), __( 'Object', 'acme-forms' ), __( 'Details', 'acme-forms' ) ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $entries ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nothing has been logged yet.', 'acme-forms' ) . '</td></tr>';
		}
		foreach ( $entries as $entry ) {
			$user = get_userdata( (int) $entry->user_id );
			echo '<tr>';
			echo '<td>' . esc_html( $entry->created_at ) . '</td>';
			echo '<td>' . esc_html( $user ? $user->display_name : sprintf( '#%d', (int) $entry->user_id ) ) . '</td>';
			echo '<td><code>' . esc_html( $entry->action ) . '</code></td>';
			echo '<td>' . esc_html( $this->object_label( $entry ) ) . '</td>';
			echo '<td><code>' . esc_html( (string) $entry->details ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $page,
						'total'   => $pages,
					)
				)
			);
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Human label of the entry's object.
	 *
	 * @param object $entry Entry.
	 * @return string
	 */
	protected function object_label( $entry ) {
		switch ( $entry->action ) {
			case 'submission_deleted':
				/* translators: %d: submission ID. */
				return sprintf( __( 'Submission #%d', 'acme-forms' ), (int) $entry->object_id );
			case 'submissions_exported':
				$form = get_post( (int) $entry->object_id );
				return $form ? $form->post_title : sprintf( '#%d', (int) $entry->object_id );
			default:
				return (int) $entry->object_id ? sprintf( '#%d', (int) $entry->object_id ) : '—';
		}
	}
}
