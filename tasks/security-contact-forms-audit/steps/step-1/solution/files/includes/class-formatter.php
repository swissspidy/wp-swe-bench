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
	 * HTML for one value. Stored values are untrusted visitor input: everything is escaped here.
	 *
	 * @param array      $field Field definition.
	 * @param mixed      $value Stored value.
	 * @param array|null $file  Stored file info for file fields.
	 * @return string
	 */
	public static function value_html( array $field, $value, $file = null ) {
		switch ( $field['type'] ) {
			case 'file':
				if ( ! $file ) {
					return '&mdash;';
				}
				return sprintf(
					'<a href="%1$s">%2$s</a> (%3$s)',
					esc_url( self::file_url( $file ) ),
					esc_html( $file['name'] ),
					esc_html( size_format( (int) $file['size'] ) )
				);
			case 'url':
				$value = acme_forms_value_to_string( $value );
				if ( '' === $value ) {
					return '&mdash;';
				}
				return '<a href="' . esc_attr( $value ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $value ) . '</a>';
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

	/**
	 * Public URL of an uploaded file.
	 *
	 * @param array $file File info.
	 * @return string
	 */
	public static function file_url( array $file ) {
		$base = acme_forms_upload_base();
		return $base['url'] . '/' . ltrim( $file['path'], '/' );
	}
}
