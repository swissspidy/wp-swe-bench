<?php
/**
 * CSV export.
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
 * admin-post.php?action=acme_forms_export&form_id=ID[&from=Y-m-d][&to=Y-m-d]&_wpnonce=…
 *
 * One row per submission of the form (oldest first). Columns: Submission ID, Submitted (UTC),
 * Status, then one column per field of the form in form order (file fields: the file name).
 */
class Export {

	const NONCE = 'acme_forms_export';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_post_acme_forms_export', array( $this, 'handle' ) );
	}

	/**
	 * Stream the CSV.
	 */
	public function handle() {
		if ( ! current_user_can( Installer::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export submissions.', 'acme-forms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::NONCE );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- dates come from <input type="date">.
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$from    = isset( $_GET['from'] ) ? wp_unslash( $_GET['from'] ) : '';
		$to      = isset( $_GET['to'] ) ? wp_unslash( $_GET['to'] ) : '';
		// phpcs:enable

		$form = get_post( $form_id );
		if ( ! $form || Forms::POST_TYPE !== $form->post_type ) {
			wp_die( esc_html__( 'Please choose a form to export.', 'acme-forms' ), '', array( 'response' => 400 ) );
		}

		$rows = $this->rows( $form_id, $from, $to );
		$this->send( $rows, sprintf( 'acme-form-%d-%s.csv', $form_id, gmdate( 'Y-m-d' ) ) );
	}

	/**
	 * Build the CSV rows (header first).
	 *
	 * @param int    $form_id Form ID.
	 * @param string $from    Start day.
	 * @param string $to      End day.
	 * @return array[]
	 */
	public function rows( $form_id, $from, $to ) {
		$fields = Forms::get_fields( $form_id );
		$header = array( __( 'Submission ID', 'acme-forms' ), __( 'Submitted (UTC)', 'acme-forms' ), __( 'Status', 'acme-forms' ) );
		foreach ( $fields as $field ) {
			$header[] = $field['label'];
		}

		$submissions = Submissions::query(
			array(
				'form_id'   => $form_id,
				'date_from' => $from,
				'date_to'   => $to,
				'orderby'   => 'id',
				'order'     => 'ASC',
				'per_page'  => 0,
			)
		);

		$rows = array( $header );
		foreach ( $submissions as $submission ) {
			$row = array( $submission->id, $submission->created_at, $submission->status );
			foreach ( $fields as $field ) {
				$value = isset( $submission->data[ $field['name'] ] ) ? $submission->data[ $field['name'] ] : '';
				$file  = isset( $submission->files[ $field['name'] ] ) ? $submission->files[ $field['name'] ] : null;
				$row[] = Formatter::value_text( $field, $value, $file );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Send the rows as a CSV download and stop.
	 *
	 * @param array[] $rows     Rows.
	 * @param string  $filename File name.
	 */
	protected function send( array $rows, $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		foreach ( $rows as $row ) {
			fputcsv( $out, $row, ',', '"', '' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
