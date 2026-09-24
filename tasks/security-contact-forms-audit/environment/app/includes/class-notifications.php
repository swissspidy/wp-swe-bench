<?php
/**
 * E-mail notifications.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the site owner an HTML e-mail for every new submission.
 */
class Notifications {

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'acme_forms_submission_created', array( $this, 'on_submission' ), 10, 2 );
	}

	/**
	 * Notify about a new submission.
	 *
	 * @param int $id      Submission ID.
	 * @param int $form_id Form ID.
	 */
	public function on_submission( $id, $form_id ) {
		if ( ! Forms::notifications_enabled( $form_id ) ) {
			return;
		}
		$submission = Submissions::get( $id );
		$form       = get_post( $form_id );
		if ( ! $submission || ! $form ) {
			return;
		}

		$prefix  = trim( (string) acme_forms_get_setting( 'subject_prefix', '' ) );
		$subject = trim(
			$prefix . ' ' . sprintf(
				/* translators: %s: form title. */
				__( 'New submission: %s', 'acme-forms' ),
				$form->post_title
			)
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $submission->email && is_email( $submission->email ) ) {
			$headers[] = 'Reply-To: ' . $submission->email;
		}

		/**
		 * Filters the notification e-mail before it is sent.
		 *
		 * @param array  $mail       to, subject, message, headers.
		 * @param object $submission Submission.
		 */
		$mail = apply_filters(
			'acme_forms_notification_email',
			array(
				'to'      => acme_forms_notification_recipient(),
				'subject' => $subject,
				'message' => $this->body( $submission, $form ),
				'headers' => $headers,
			),
			$submission
		);

		wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
	}

	/**
	 * HTML body.
	 *
	 * @param object   $submission Submission.
	 * @param \WP_Post $form       Form.
	 * @return string
	 */
	protected function body( $submission, $form ) {
		$html  = '<html><body style="font-family: sans-serif;">';
		$html .= '<h2>' . esc_html(
			sprintf(
				/* translators: %s: form title. */
				__( 'New submission on "%s"', 'acme-forms' ),
				$form->post_title
			)
		) . '</h2>';
		$html .= '<table cellpadding="6" style="border-collapse: collapse;">';
		foreach ( Formatter::rows( $submission ) as $row ) {
			$html .= '<tr><th align="left" valign="top">' . esc_html( $row['field']['label'] ) . '</th>';
			$html .= '<td>' . Formatter::value_html( $row['field'], $row['value'], $row['file'] ) . '</td></tr>';
		}
		$html .= '</table>';
		$html .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=acme-forms-submissions&action=view&submission=' . $submission->id ) ) . '">' . esc_html__( 'View this submission', 'acme-forms' ) . '</a></p>';
		$html .= '</body></html>';
		return $html;
	}
}
