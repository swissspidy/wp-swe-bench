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
 * Whether a user is one of our freelancers (has the Freelancer role).
 *
 * Before 3.0 freelancers were Contributors flagged with the `acme_freelancer`
 * user meta; the 3.0 upgrade moved them to the role.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function is_freelancer( $user_id ) {
	$user = get_userdata( (int) $user_id );
	return $user && in_array( Capabilities::FREELANCER_ROLE, (array) $user->roles, true );
}
