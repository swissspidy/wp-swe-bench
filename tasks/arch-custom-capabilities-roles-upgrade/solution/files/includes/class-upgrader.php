<?php
/**
 * Versioned upgrade routines.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Runs pending upgrades on the first request after a deploy (and on activation).
 */
class Upgrader {

	const VERSION_OPTION = 'acme_newsroom_version';

	/**
	 * Records which one-time role changes were applied: they must never be
	 * applied again, even if the version option is reset, because the site
	 * owner may have adjusted the roles since.
	 */
	const APPLIED_OPTION = 'acme_newsroom_applied_upgrades';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 1 );
	}

	/**
	 * Runs the upgrade if the stored version is older than the code.
	 */
	public function maybe_upgrade() {
		$installed = (string) get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, VERSION, '>=' ) ) {
			return;
		}
		if ( version_compare( $installed, '3.0.0', '<' ) ) {
			$this->upgrade_300();
		}
		update_option( self::VERSION_OPTION, VERSION );
	}

	/**
	 * Whether a one-time step was applied already.
	 *
	 * @param string $step Step.
	 * @return bool
	 */
	private function applied( $step ) {
		return in_array( $step, (array) get_option( self::APPLIED_OPTION, array() ), true );
	}

	/**
	 * Records a one-time step.
	 *
	 * @param string $step Step.
	 */
	private function mark_applied( $step ) {
		$applied   = (array) get_option( self::APPLIED_OPTION, array() );
		$applied[] = $step;
		update_option( self::APPLIED_OPTION, array_values( array_unique( $applied ) ), false );
	}

	/**
	 * 3.0.0: story capabilities, Freelancer role, freelancers move to it.
	 */
	public function upgrade_300() {
		if ( ! $this->applied( 'story_capabilities' ) ) {
			Capabilities::grant_to_roles();
			Capabilities::add_freelancer_role();
			$this->mark_applied( 'story_capabilities' );
		}
		$this->migrate_freelancers();
	}

	/**
	 * Contributors flagged as freelancers become Freelancers; the flag goes away.
	 *
	 * Flagged users with other roles (e.g. hired as staff meanwhile) keep them.
	 */
	public function migrate_freelancers() {
		$users = get_users(
			array(
				'meta_key' => Freelancers::LEGACY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'   => 'all',
			)
		);
		foreach ( $users as $user ) {
			$flag = strtolower( trim( (string) get_user_meta( $user->ID, Freelancers::LEGACY_META, true ) ) );
			if ( in_array( $flag, array( '1', 'yes', 'true', 'on' ), true ) && in_array( 'contributor', (array) $user->roles, true ) && get_role( Capabilities::FREELANCER_ROLE ) ) {
				$user->remove_role( 'contributor' );
				$user->add_role( Capabilities::FREELANCER_ROLE );
			}
			delete_user_meta( $user->ID, Freelancers::LEGACY_META );
		}
	}
}
