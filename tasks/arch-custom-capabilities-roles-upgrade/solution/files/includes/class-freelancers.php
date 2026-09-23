<?php
/**
 * Freelancers.
 *
 * Since 3.0 freelancers have their own role (see Capabilities); the role
 * gives them exactly what they need, so no capability tweaks are needed.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Freelancer specific admin behaviour.
 */
class Freelancers {

	/**
	 * User meta that flagged freelancers before 3.0 (migrated and removed by the upgrade).
	 */
	const LEGACY_META = 'acme_freelancer';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'pre_get_posts', array( $this, 'only_own_stories' ) );
	}

	/**
	 * Freelancers only see their own stories in wp-admin.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function only_own_stories( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || Story_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( is_freelancer( get_current_user_id() ) && ! current_user_can( 'edit_others_stories' ) ) {
			$query->set( 'author', get_current_user_id() );
		}
	}
}
