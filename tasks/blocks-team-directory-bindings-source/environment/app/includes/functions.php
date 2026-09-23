<?php
/**
 * Public API of the plugin (used by themes).
 *
 * @package Acme\Team
 */

defined( 'ABSPATH' ) || exit;

/**
 * The member fields, key => definition.
 *
 * Definition keys:
 * - `label`    Human-readable label.
 * - `type`     text | email | phone | url | image.
 * - `meta_key` Post meta key the value is stored in (not set for `name`,
 *              which is the post title).
 * - `legacy`   Key in the 1.x `acme_member_meta` array, if the field existed in 1.x.
 *
 * Themes can add fields (e.g. pronouns) with the `acme_team_fields` filter;
 * such fields are stored in their `meta_key` and edited in the meta box.
 *
 * @return array<string, array{label:string, type:string, meta_key?:string, legacy?:string}>
 */
function acme_team_fields() {
	$fields = array(
		'name'        => array(
			'label' => __( 'Name', 'acme-team' ),
			'type'  => 'text',
		),
		'role'        => array(
			'label'    => __( 'Role', 'acme-team' ),
			'type'     => 'text',
			'meta_key' => '_acme_role', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'legacy'   => 'role',
		),
		'email'       => array(
			'label'    => __( 'Email', 'acme-team' ),
			'type'     => 'email',
			'meta_key' => '_acme_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'legacy'   => 'email',
		),
		'phone'       => array(
			'label'    => __( 'Phone', 'acme-team' ),
			'type'     => 'phone',
			'meta_key' => '_acme_phone', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'legacy'   => 'phone',
		),
		'photo'       => array(
			'label'    => __( 'Photo', 'acme-team' ),
			'type'     => 'image',
			'meta_key' => '_acme_photo_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		),
		'profile_url' => array(
			'label'    => __( 'Profile URL', 'acme-team' ),
			'type'     => 'url',
			'meta_key' => '_acme_profile_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		),
	);

	/**
	 * Filters the member fields.
	 *
	 * @param array $fields Field key => definition.
	 */
	$fields = (array) apply_filters( 'acme_team_fields', $fields );

	foreach ( $fields as $key => $field ) {
		if ( ! is_array( $field ) || ( 'name' !== $key && empty( $field['meta_key'] ) ) ) {
			unset( $fields[ $key ] );
			continue;
		}
		$fields[ $key ] = wp_parse_args(
			$field,
			array(
				'label' => $key,
				'type'  => 'text',
			)
		);
	}
	return $fields;
}

/**
 * Get a team member.
 *
 * Note: this does not check the member's status; callers that display
 * members to visitors must check {@see Acme\Team\Member::is_public()}.
 *
 * @param int|WP_Post $member Post ID or object.
 * @return Acme\Team\Member|null
 */
function acme_team_get_member( $member ) {
	return Acme\Team\Member::get( $member );
}

/**
 * Normalize a phone number for a tel: link ("+1 (555) 010-1001" → "+15550101001").
 *
 * @param string $phone Phone number as entered.
 * @return string Digits with an optional leading "+", or '' if there are no digits.
 */
function acme_team_phone_digits( $phone ) {
	$phone  = trim( (string) $phone );
	$digits = preg_replace( '/\D+/', '', $phone );
	if ( '' === $digits ) {
		return '';
	}
	return ( 0 === strpos( $phone, '+' ) ? '+' : '' ) . $digits;
}
