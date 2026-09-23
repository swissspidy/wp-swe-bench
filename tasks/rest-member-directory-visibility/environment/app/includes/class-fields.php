<?php
/**
 * Profile field registry and storage.
 *
 * Storage (2.x): one user meta per field, `acme_member_{key}`.
 * Storage (1.x): everything in one array, user meta `acme_member_profile`, with the old keys
 * `title` (job_title) and `about` (bio). 1.x profiles are converted when the member is next
 * saved in wp-admin; until then values are read from the array.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Profile fields.
 */
class Fields {

	/**
	 * 1.x array keys that were renamed in 2.0.
	 */
	const LEGACY_KEYS = array(
		'job_title' => 'title',
		'bio'       => 'about',
	);

	/**
	 * Field definitions: key => label, type, default visibility, max length.
	 *
	 * @return array<string, array{label:string, type:string, visibility:string, max:int}>
	 */
	public static function all() {
		$fields = array(
			'job_title' => array(
				'label'      => __( 'Job title', 'acme-members' ),
				'type'       => 'text',
				'visibility' => Visibility::PUBLIC_LEVEL,
				'max'        => 100,
			),
			'company'   => array(
				'label'      => __( 'Company', 'acme-members' ),
				'type'       => 'text',
				'visibility' => Visibility::PUBLIC_LEVEL,
				'max'        => 100,
			),
			'city'      => array(
				'label'      => __( 'City', 'acme-members' ),
				'type'       => 'text',
				'visibility' => Visibility::PUBLIC_LEVEL,
				'max'        => 60,
			),
			'phone'     => array(
				'label'      => __( 'Phone', 'acme-members' ),
				'type'       => 'tel',
				'visibility' => Visibility::MEMBERS_LEVEL,
				'max'        => 30,
			),
			'website'   => array(
				'label'      => __( 'Website', 'acme-members' ),
				'type'       => 'url',
				'visibility' => Visibility::PUBLIC_LEVEL,
				'max'        => 200,
			),
			'bio'       => array(
				'label'      => __( 'About me', 'acme-members' ),
				'type'       => 'textarea',
				'visibility' => Visibility::PUBLIC_LEVEL,
				'max'        => 1000,
			),
		);

		/**
		 * Filters the profile field definitions.
		 *
		 * @param array $fields Field key => definition.
		 */
		return apply_filters( 'acme_members_fields', $fields );
	}

	/**
	 * Keys of all fields.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array_keys( self::all() );
	}

	/**
	 * A field's value (reads 1.x profiles as a fallback).
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Field key.
	 * @return string
	 */
	public static function get( $user_id, $key ) {
		if ( metadata_exists( 'user', $user_id, 'acme_member_' . $key ) ) {
			return (string) get_user_meta( $user_id, 'acme_member_' . $key, true );
		}
		$legacy = get_user_meta( $user_id, 'acme_member_profile', true );
		if ( is_array( $legacy ) ) {
			$legacy_key = isset( self::LEGACY_KEYS[ $key ] ) ? self::LEGACY_KEYS[ $key ] : $key;
			return isset( $legacy[ $legacy_key ] ) ? (string) $legacy[ $legacy_key ] : '';
		}
		return '';
	}

	/**
	 * All field values of a member (no visibility applied!).
	 *
	 * @param int $user_id User ID.
	 * @return array<string, string>
	 */
	public static function get_all( $user_id ) {
		$out = array();
		foreach ( self::keys() as $key ) {
			$out[ $key ] = self::get( $user_id, $key );
		}
		return $out;
	}

	/**
	 * Store a field value (2.x format).
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Field key.
	 * @param string $value   Value (already validated/sanitized).
	 */
	public static function set( $user_id, $key, $value ) {
		update_user_meta( $user_id, 'acme_member_' . $key, $value );
	}

	/**
	 * Convert a 1.x profile to 2.x meta (called when a profile is saved).
	 *
	 * @param int $user_id User ID.
	 */
	public static function migrate_legacy( $user_id ) {
		$legacy = get_user_meta( $user_id, 'acme_member_profile', true );
		if ( ! is_array( $legacy ) ) {
			return;
		}
		foreach ( self::keys() as $key ) {
			if ( ! metadata_exists( 'user', $user_id, 'acme_member_' . $key ) ) {
				self::set( $user_id, $key, self::get( $user_id, $key ) );
			}
		}
		delete_user_meta( $user_id, 'acme_member_profile' );
	}

	/**
	 * Sanitize a value for storage according to the field type (no validation).
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public static function sanitize( $key, $value ) {
		$fields = self::all();
		$type   = isset( $fields[ $key ] ) ? $fields[ $key ]['type'] : 'text';
		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value, array( 'http', 'https' ) );
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
