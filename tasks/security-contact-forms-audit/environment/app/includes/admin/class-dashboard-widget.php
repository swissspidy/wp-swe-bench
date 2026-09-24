<?php
/**
 * "Latest form submissions" dashboard widget.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

use Acme\Forms\Formatter;
use Acme\Forms\Installer;
use Acme\Forms\Submissions;

defined( 'ABSPATH' ) || exit;

/**
 * The widget loads its data from admin-ajax.php?action=acme_forms_widget:
 *
 * - `limit=N`         → { submissions: [ {id, form_id, form, created, email, status, summary}, … ] }
 * - `submission=ID`   → { submission: {id, form_id, form, created, email, status, ip, fields: [ {label, value}, … ]} }
 *
 * Responses use the usual { success, data } envelope.
 */
class Dashboard_Widget {

	const ACTION = 'acme_forms_widget';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax' ) );
	}

	/**
	 * Register the widget for inbox users.
	 */
	public function register() {
		if ( ! current_user_can( Installer::CAP ) ) {
			return;
		}
		wp_add_dashboard_widget( 'acme_forms_latest', __( 'Latest form submissions', 'acme-forms' ), array( $this, 'render' ) );
		wp_enqueue_script( 'acme-forms-dashboard', ACME_FORMS_URL . 'assets/dashboard.js', array(), ACME_FORMS_VERSION, true );
		wp_localize_script(
			'acme-forms-dashboard',
			'acmeFormsWidget',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::ACTION,
				'nonce'   => wp_create_nonce( self::ACTION ),
				'viewUrl' => Submissions_Page::url(
					array(
						'action'     => 'view',
						'submission' => '',
					)
				),
				'i18n'    => array(
					'loading' => __( 'Loading…', 'acme-forms' ),
					'empty'   => __( 'No submissions yet.', 'acme-forms' ),
					'error'   => __( 'Could not load submissions.', 'acme-forms' ),
					'back'    => __( 'Back', 'acme-forms' ),
				),
			)
		);
	}

	/**
	 * Widget container (filled by assets/dashboard.js).
	 */
	public function render() {
		echo '<div class="acme-forms-widget" data-limit="5"><p>' . esc_html__( 'Loading…', 'acme-forms' ) . '</p></div>';
	}

	/**
	 * AJAX endpoint.
	 */
	public function ajax() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$id    = isset( $_REQUEST['submission'] ) ? absint( $_REQUEST['submission'] ) : 0;
		$limit = isset( $_REQUEST['limit'] ) ? min( 20, max( 1, absint( $_REQUEST['limit'] ) ) ) : 5;
		// phpcs:enable

		if ( $id ) {
			$submission = Submissions::get( $id );
			if ( ! $submission ) {
				wp_send_json_error( array( 'message' => __( 'Submission not found.', 'acme-forms' ) ), 404 );
			}
			wp_send_json_success( array( 'submission' => $this->detail( $submission ) ) );
		}

		$items = array();
		foreach ( Submissions::query( array( 'per_page' => $limit ) ) as $submission ) {
			$items[] = $this->summary( $submission );
		}
		wp_send_json_success( array( 'submissions' => $items ) );
	}

	/**
	 * Common fields.
	 *
	 * @param object $submission Submission.
	 * @return array
	 */
	protected function base( $submission ) {
		$form = get_post( $submission->form_id );
		return array(
			'id'      => $submission->id,
			'form_id' => $submission->form_id,
			'form'    => $form ? $form->post_title : '',
			'created' => mysql_to_rfc3339( $submission->created_at ),
			'email'   => $submission->email,
			'status'  => $submission->status,
		);
	}

	/**
	 * List entry.
	 *
	 * @param object $submission Submission.
	 * @return array
	 */
	protected function summary( $submission ) {
		$first = '';
		foreach ( Formatter::rows( $submission ) as $row ) {
			if ( in_array( $row['field']['type'], array( 'text', 'textarea' ), true ) && '' !== acme_forms_value_to_string( $row['value'] ) ) {
				$first = acme_forms_excerpt( acme_forms_value_to_string( $row['value'] ), 60 );
				break;
			}
		}
		return $this->base( $submission ) + array( 'summary' => $first );
	}

	/**
	 * Full entry.
	 *
	 * @param object $submission Submission.
	 * @return array
	 */
	protected function detail( $submission ) {
		$fields = array();
		foreach ( Formatter::rows( $submission ) as $row ) {
			$fields[] = array(
				'label' => $row['field']['label'],
				'value' => Formatter::value_text( $row['field'], $row['value'], $row['file'] ),
			);
		}
		return $this->base( $submission ) + array(
			'ip'     => $submission->ip,
			'fields' => $fields,
		);
	}
}
