<?php
/**
 * Profile read model + validated updates, shared by the REST API and the profile form.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Member profile service.
 */
class Profile_Service {

	/**
	 * Allowed characters in phone numbers.
	 */
	const PHONE_PATTERN = '#^[0-9 +\-()/]{3,30}$#';

	/**
	 * Profile of a member as the viewer may see it.
	 *
	 * @param int $member_id Member.
	 * @param int $viewer_id Viewer (0 = anonymous).
	 * @return array
	 */
	public static function to_array( $member_id, $viewer_id ) {
		$user = get_userdata( $member_id );
		$data = array(
			'id'     => (int) $member_id,
			'name'   => $user ? $user->display_name : '',
			'slug'   => $user ? $user->user_nicename : '',
			'link'   => Members::profile_url( $member_id ),
			'fields' => (object) Visibility::visible_fields( $member_id, $viewer_id ),
		);
		if ( self::can_manage( $member_id, $viewer_id ) ) {
			$data['visibility'] = array(
				'profile' => Visibility::profile_level( $member_id ),
				'fields'  => (object) Visibility::field_levels( $member_id ),
			);
		}
		return $data;
	}

	/**
	 * May the viewer see the visibility settings (the member themself, user editors)?
	 *
	 * @param int $member_id Member.
	 * @param int $viewer_id Viewer.
	 * @return bool
	 */
	public static function can_manage( $member_id, $viewer_id ) {
		return $viewer_id && ( (int) $viewer_id === (int) $member_id || user_can( $viewer_id, 'edit_users' ) );
	}

	/**
	 * Member IDs the viewer may see, optionally filtered.
	 *
	 * @param int    $viewer_id Viewer.
	 * @param string $search    Case-insensitive substring of the name or a visible field.
	 * @param string $city      Case-insensitive exact city (only when the city is visible).
	 * @return int[] Ordered by display name.
	 */
	public static function visible_ids( $viewer_id, $search = '', $city = '' ) {
		$search = self::lower( trim( (string) $search ) );
		$city   = self::lower( trim( (string) $city ) );
		$out    = array();
		foreach ( Members::all_ids() as $id ) {
			if ( ! Visibility::can_view_profile( $id, $viewer_id ) ) {
				continue;
			}
			$visible = Visibility::visible_fields( $id, $viewer_id );
			if ( '' !== $city && ( ! isset( $visible['city'] ) || self::lower( $visible['city'] ) !== $city ) ) {
				continue;
			}
			if ( '' !== $search ) {
				$user     = get_userdata( $id );
				$haystack = self::lower( $user->display_name . "\n" . implode( "\n", $visible ) );
				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}
			$out[] = $id;
		}
		return $out;
	}

	/**
	 * Lower-case (multibyte safe).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function lower( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Length (multibyte safe).
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Validate one field value.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Raw value.
	 * @return string|\WP_Error Sanitized value.
	 */
	public static function validate_field( $key, $value ) {
		$fields = Fields::all();
		if ( ! isset( $fields[ $key ] ) ) {
			return new \WP_Error( 'acme_members_unknown_field', __( 'Unknown field.', 'acme-members' ) );
		}
		if ( ! is_scalar( $value ) && null !== $value ) {
			/* translators: %s: field label */
			return new \WP_Error( 'acme_members_invalid', sprintf( __( '%s must be text.', 'acme-members' ), $fields[ $key ]['label'] ) );
		}
		$field = $fields[ $key ];
		$raw   = trim( (string) $value );

		switch ( $field['type'] ) {
			case 'tel':
				if ( '' !== $raw && ! preg_match( self::PHONE_PATTERN, $raw ) ) {
					/* translators: %s: field label */
					return new \WP_Error( 'acme_members_invalid', sprintf( __( '%s may only contain digits, spaces and + - ( ) / (3 to 30 characters).', 'acme-members' ), $field['label'] ) );
				}
				return $raw;
			case 'url':
				if ( '' === $raw ) {
					return '';
				}
				$parts = wp_parse_url( $raw );
				if ( ! $parts || empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || esc_url_raw( $raw, array( 'http', 'https' ) ) !== $raw ) {
					/* translators: %s: field label */
					return new \WP_Error( 'acme_members_invalid', sprintf( __( '%s must be a web address starting with http:// or https://.', 'acme-members' ), $field['label'] ) );
				}
				if ( self::length( $raw ) > $field['max'] ) {
					/* translators: 1: field label, 2: max length */
					return new \WP_Error( 'acme_members_invalid', sprintf( __( '%1$s must be at most %2$d characters long.', 'acme-members' ), $field['label'], $field['max'] ) );
				}
				return $raw;
			case 'textarea':
				$clean = sanitize_textarea_field( $raw );
				break;
			default:
				$clean = sanitize_text_field( $raw );
		}
		if ( self::length( $clean ) > $field['max'] ) {
			/* translators: 1: field label, 2: max length */
			return new \WP_Error( 'acme_members_invalid', sprintf( __( '%1$s must be at most %2$d characters long.', 'acme-members' ), $field['label'], $field['max'] ) );
		}
		return $clean;
	}

	/**
	 * Validate a whole update.
	 *
	 * @param array $input Keys: field keys, 'visibility', 'field_visibility'.
	 * @return array{changes: array, errors: array<string, string>}
	 */
	public static function validate( array $input ) {
		$changes = array(
			'fields'           => array(),
			'visibility'       => null,
			'field_visibility' => array(),
		);
		$errors  = array();

		foreach ( Fields::keys() as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = self::validate_field( $key, $input[ $key ] );
			if ( is_wp_error( $value ) ) {
				$errors[ $key ] = $value->get_error_message();
			} else {
				$changes['fields'][ $key ] = $value;
			}
		}

		if ( array_key_exists( 'visibility', $input ) && null !== $input['visibility'] ) {
			if ( Visibility::is_level( $input['visibility'] ) ) {
				$changes['visibility'] = $input['visibility'];
			} else {
				$errors['visibility'] = __( 'Profile visibility must be one of: public, members, private.', 'acme-members' );
			}
		}

		if ( array_key_exists( 'field_visibility', $input ) && null !== $input['field_visibility'] ) {
			if ( ! is_array( $input['field_visibility'] ) ) {
				$errors['field_visibility'] = __( 'Field visibility must be an object.', 'acme-members' );
			} else {
				foreach ( $input['field_visibility'] as $key => $level ) {
					if ( ! in_array( (string) $key, Fields::keys(), true ) ) {
						/* translators: %s: field key */
						$errors['field_visibility'] = sprintf( __( 'Unknown field "%s".', 'acme-members' ), sanitize_text_field( (string) $key ) );
						continue;
					}
					if ( ! Visibility::is_level( $level ) ) {
						$errors['field_visibility'] = __( 'Field visibility must be one of: public, members, private.', 'acme-members' );
						continue;
					}
					$changes['field_visibility'][ $key ] = $level;
				}
			}
		}

		return array(
			'changes' => $changes,
			'errors'  => $errors,
		);
	}

	/**
	 * Apply validated changes (converts 1.x profiles, keeps the effective levels).
	 *
	 * @param int   $user_id Member.
	 * @param array $changes From validate().
	 */
	public static function save( $user_id, array $changes ) {
		// Effective levels before touching anything (includes the 1.x flags).
		$profile_level = Visibility::profile_level( $user_id );
		$field_levels  = Visibility::field_levels( $user_id );

		Fields::migrate_legacy( $user_id );
		foreach ( $changes['fields'] as $key => $value ) {
			Fields::set( $user_id, $key, $value );
		}

		update_user_meta( $user_id, 'acme_member_visibility', null !== $changes['visibility'] ? $changes['visibility'] : $profile_level );
		update_user_meta( $user_id, 'acme_member_field_visibility', array_merge( $field_levels, $changes['field_visibility'] ) );
		delete_user_meta( $user_id, 'acme_member_hide_profile' );
		delete_user_meta( $user_id, 'acme_member_hide_phone' );

		/** This action is documented in includes/class-admin.php */
		do_action( 'acme_members_profile_saved', $user_id );
	}
}
