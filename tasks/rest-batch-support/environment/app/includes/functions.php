<?php
/**
 * Helper functions.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

defined( 'ABSPATH' ) || exit;

const DEFAULT_COLOR  = '#3858e9';
const TASK_STATUSES  = array( 'open', 'done' );
const MAX_TITLE_LEN  = 200;

/**
 * Is this a `#rrggbb` color?
 *
 * @param mixed $color Value.
 * @return bool
 */
function is_valid_color( $color ) {
	return is_string( $color ) && (bool) preg_match( '/^#[0-9a-fA-F]{6}$/', $color );
}

/**
 * Lower-case `#rrggbb` color, or the default color.
 *
 * @param mixed $color Value.
 * @return string
 */
function sanitize_color( $color ) {
	return is_valid_color( $color ) ? strtolower( $color ) : DEFAULT_COLOR;
}

/**
 * Is this a real calendar date in `YYYY-MM-DD` format?
 *
 * @param mixed $date Value.
 * @return bool
 */
function is_valid_date( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return false;
	}
	list( $y, $m, $d ) = array_map( 'intval', explode( '-', $date ) );
	return checkdate( $m, $d, $y );
}

/**
 * Convert a GMT MySQL datetime to RFC 3339 (or null).
 *
 * @param string|null $datetime MySQL datetime.
 * @return string|null
 */
function to_rfc3339( $datetime ) {
	if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
		return null;
	}
	return mysql_to_rfc3339( $datetime );
}

/**
 * Parse a boolean-ish value the way 1.x clients send it ("1", "true", "yes", "on", 1, true ...).
 *
 * @param mixed $value Value.
 * @return bool|null Null when the value is not recognisable.
 */
function parse_bool( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_int( $value ) ) {
		return 0 !== $value;
	}
	return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
}

/**
 * Is this a non-negative integer (or a string that is one)?
 *
 * @param mixed $value Value.
 * @return bool
 */
function is_non_negative_int( $value ) {
	if ( is_int( $value ) ) {
		return $value >= 0;
	}
	return is_string( $value ) && ctype_digit( $value );
}

/**
 * Can this user be assigned tasks of the list?
 *
 * @param int $user_id User ID.
 * @param int $list_id List ID.
 * @return bool
 */
function can_be_assigned( $user_id, $list_id ) {
	$user = get_userdata( (int) $user_id );
	return $user && Access::can_view_list( $list_id, $user->ID );
}
