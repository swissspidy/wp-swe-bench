<?php
/**
 * Public helper functions (used by the Acme Daily theme and the Slack bot plugin).
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a story has been approved by the desk.
 *
 * 1.x stored "yes", 2.x stores "1". Anything else means "not approved".
 *
 * @param int|\WP_Post $story Story.
 * @return bool
 */
function is_approved( $story ) {
	$story = get_post( $story );
	if ( ! $story || Story_Post_Type::POST_TYPE !== $story->post_type ) {
		return false;
	}
	$flag = strtolower( trim( (string) get_post_meta( $story->ID, Approval::META_APPROVED, true ) ) );
	return in_array( $flag, array( '1', 'yes' ), true );
}

/**
 * Approval details of a story.
 *
 * @param int|\WP_Post $story Story.
 * @return array{approved: bool, approved_by: int, approved_at: string|null}
 */
function get_approval( $story ) {
	$story = get_post( $story );
	if ( ! $story ) {
		return array(
			'approved'    => false,
			'approved_by' => 0,
			'approved_at' => null,
		);
	}
	$approved = is_approved( $story );
	$at       = (string) get_post_meta( $story->ID, Approval::META_APPROVED_AT, true );
	return array(
		'approved'    => $approved,
		'approved_by' => $approved ? (int) get_post_meta( $story->ID, Approval::META_APPROVED_BY, true ) : 0,
		'approved_at' => $approved && $at ? mysql_to_rfc3339( $at ) : null,
	);
}

/**
 * Whether a user is one of our freelancers.
 *
 * Freelancers are flagged with the `acme_freelancer` user meta ("1" since 2.0,
 * "yes" in 1.x; "0"/"no" or missing = staff).
 *
 * @param int $user_id User ID.
 * @return bool
 */
function is_freelancer( $user_id ) {
	$flag = strtolower( trim( (string) get_user_meta( (int) $user_id, 'acme_freelancer', true ) ) );
	return in_array( $flag, array( '1', 'yes', 'true', 'on' ), true );
}
