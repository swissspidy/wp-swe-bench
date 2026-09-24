<?php
/**
 * Formats stored submission values for display.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Shared by the single-submission screen and the notification e-mail.
 */
class Formatter {

	/**
	 * Rows (label + value) of a submission in form order. Values of fields that no longer exist
	 * on the form are appended with their raw name as the label.
	 *
	 * @param object $submission Hydrated submission.
	 * @return array[] Each: field (definition), value (mixed), file (array|null).
	 */
	public static function rows( $submission ) {
		$fields = Forms::get_fields( $submission->form_id );
		$rows   = array();
		$seen   = array();
		foreach ( $fields as $field ) {
			$seen[] = $field['name'];
			if ( 'file' === $field['type'] ) {
				$rows[] = array(
					'field' => $field,
					'value' => '',
					'file'  => isset( $submission->files[ $field['name'] ] ) ? $submission->files[ $field['name'] ] : null,
				);
				continue;
			}
			$rows[] = array(
				'field' => $field,
				'value' => isset( $submission->data[ $field['name'] ] ) ? $submission->data[ $field['name'] ] : '',
				'file'  => null,
			);
		}
		foreach ( $submission->data as $name => $value ) {
			if ( in_array( $name, $seen, true ) ) {
				continue;
			}
			$rows[] = array(
				'field' => array(
					'name'  => (string) $name,
					'label' => (string) $name,
					'type'  => 'text',
				),
				'value' => $value,
				'file'  => null,
			);
		}
		return $rows;
	}

	/**
	 * HTML for one value. Stored values are untrusted visitor input: everything is escaped here,
	 * and only real web addresses become links.
	 *
	 * @param array      $field         Field definition.
	 * @param mixed      $value         Stored value.
	 * @param array|null $file          Stored file info for file fields.
	 * @param int        $submission_id Submission the value belongs to (for file downloads).
	 * @return string
	 */
	public static function value_html( array $field, $value, $file = null, $submission_id = 0 ) {
		switch ( $field['type'] ) {
			case 'file':
				if ( ! $file ) {
					return '&mdash;';
				}
				$name = esc_html( isset( $file['name'] ) ? (string) $file['name'] : '' );
				$size = esc_html( size_format( isset( $file['size'] ) ? (int) $file['size'] : 0 ) );
				if ( ! $submission_id ) {
					return $name . ' (' . $size . ')';
				}
				return sprintf(
					'<a href="%1$s">%2$s</a> (%3$s)',
					esc_url( self::download_url( $submission_id, $field['name'] ) ),
					$name,
					$size
				);
			case 'url':
				$value = acme_forms_value_to_string( $value );
				if ( '' === $value ) {
					return '&mdash;';
				}
				$url = self::web_url( $value );
				if ( ! $url ) {
					return esc_html( $value );
				}
				return '<a href="' . esc_url( $url ) . '" rel="nofollow noopener noreferrer" target="_blank">' . esc_html( $value ) . '</a>';
			case 'email':
				$value = acme_forms_value_to_string( $value );
				if ( '' === $value ) {
					return '&mdash;';
				}
				if ( ! is_email( $value ) ) {
					return esc_html( $value );
				}
				return '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>';
			case 'textarea':
				$value = acme_forms_value_to_string( $value );
				return '' === $value ? '&mdash;' : nl2br( esc_html( $value ) );
			default:
				$value = acme_forms_value_to_string( $value );
				return '' === $value ? '&mdash;' : esc_html( $value );
		}
	}

	/**
	 * The value as a web address if it is an absolute http(s) URL, null otherwise
	 * (javascript:, data:, relative or scheme-less values are shown as text).
	 *
	 * @param string $value Stored value.
	 * @return string|null
	 */
	public static function web_url( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '#^https?://[^\s/?\#]+#i', $value ) ) {
			return null;
		}
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		return '' === $url ? null : $url;
	}

	/**
	 * Download URL of a submission's file (files are never linked directly).
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $field         Field name.
	 * @return string
	 */
	public static function download_url( $submission_id, $field ) {
		return add_query_arg(
			array(
				'action'     => 'acme_forms_download',
				'submission' => (int) $submission_id,
				'field'      => sanitize_key( $field ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Plain-text value (CSV, dashboard widget).
	 *
	 * @param array      $field Field definition.
	 * @param mixed      $value Stored value.
	 * @param array|null $file  Stored file info.
	 * @return string
	 */
	public static function value_text( array $field, $value, $file = null ) {
		if ( 'file' === $field['type'] ) {
			return $file ? (string) $file['name'] : '';
		}
		return acme_forms_value_to_string( $value );
	}
}
