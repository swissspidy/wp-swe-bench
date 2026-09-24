<?php
/**
 * Global helper functions.
 *
 * @package Acme\Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default plugin settings (stored in the `acme_forms_settings` option).
 *
 * @return array
 */
function acme_forms_default_settings() {
	return array(
		'notify_email'   => '',
		'subject_prefix' => '[Acme Forms]',
		'store_ip'       => 1,
		'max_upload_mb'  => 5,
		'per_page'       => 20,
	);
}

/**
 * All settings, merged with the defaults.
 *
 * @return array
 */
function acme_forms_get_settings() {
	$saved = get_option( 'acme_forms_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( acme_forms_default_settings(), $saved );
}

/**
 * A single setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function acme_forms_get_setting( $key, $default = null ) {
	$settings = acme_forms_get_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * The address notifications go to.
 *
 * @return string
 */
function acme_forms_notification_recipient() {
	$email = (string) acme_forms_get_setting( 'notify_email', '' );
	return is_email( $email ) ? $email : get_option( 'admin_email' );
}

/**
 * Field types the plugin knows about.
 *
 * @return array<string,string> type => label.
 */
function acme_forms_field_types() {
	$types = array(
		'text'     => __( 'Text', 'acme-forms' ),
		'email'    => __( 'E-mail', 'acme-forms' ),
		'url'      => __( 'Website', 'acme-forms' ),
		'textarea' => __( 'Paragraph', 'acme-forms' ),
		'select'   => __( 'Drop-down', 'acme-forms' ),
		'file'     => __( 'File upload', 'acme-forms' ),
	);

	/**
	 * Filters the available field types.
	 *
	 * @param array $types type => label.
	 */
	return apply_filters( 'acme_forms_field_types', $types );
}

/**
 * Base directory and URL for uploaded files.
 *
 * @return array{dir:string,url:string}
 */
function acme_forms_upload_base() {
	$uploads = wp_upload_dir( null, false );
	return array(
		'dir' => trailingslashit( $uploads['basedir'] ) . 'acme-forms',
		'url' => trailingslashit( $uploads['baseurl'] ) . 'acme-forms',
	);
}

/**
 * Turn a stored field value into a single line of text (arrays are joined).
 *
 * @param mixed $value Stored value.
 * @return string
 */
function acme_forms_value_to_string( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( ', ', array_map( 'strval', $value ) );
	}
	return (string) $value;
}

/**
 * Shorten a value for list displays.
 *
 * @param string $text   Text.
 * @param int    $length Max characters.
 * @return string
 */
function acme_forms_excerpt( $text, $length = 80 ) {
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $length ) {
		return mb_substr( $text, 0, $length - 1 ) . '…';
	}
	return $text;
}

/**
 * Format a UTC MySQL datetime for display in the site's timezone.
 *
 * @param string $mysql_utc Datetime (UTC).
 * @return string
 */
function acme_forms_format_date( $mysql_utc ) {
	if ( ! $mysql_utc ) {
		return '';
	}
	$ts = strtotime( $mysql_utc . ' UTC' );
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
}
