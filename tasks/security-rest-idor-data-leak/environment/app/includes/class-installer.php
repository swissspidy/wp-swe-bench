<?php
/**
 * Install / upgrade: roles and capabilities.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Installer {

	const VERSION_OPTION = 'acme_support_version';

	/**
	 * Capability held by agents: see and work every ticket, incl. private notes.
	 */
	const CAP_AGENT = 'acme_support_agent';

	/**
	 * Capability held by managers: everything an agent can do, plus deleting.
	 */
	const CAP_MANAGE = 'acme_support_manage';

	/**
	 * Activation.
	 */
	public static function activate() {
		self::add_roles();
		Post_Types::register();
		flush_rewrite_rules();
		update_option( self::VERSION_OPTION, ACME_SUPPORT_VERSION );
	}

	/**
	 * Idempotent upgrade after an in-place update.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === ACME_SUPPORT_VERSION ) {
			return;
		}
		self::add_roles();
		update_option( self::VERSION_OPTION, ACME_SUPPORT_VERSION );
	}

	/**
	 * Roles and capabilities.
	 *
	 * - `acme_support_customer`: an end user who can open tickets and read their own.
	 * - `acme_support_agent`: a support agent who works all tickets.
	 */
	public static function add_roles() {
		add_role(
			'acme_support_customer',
			__( 'Support Customer', 'acme-support' ),
			array(
				'read' => true,
			)
		);

		add_role(
			'acme_support_agent',
			__( 'Support Agent', 'acme-support' ),
			array(
				'read'             => true,
				self::CAP_AGENT    => true,
				'upload_files'     => true,
			)
		);

		add_role(
			'acme_support_manager',
			__( 'Support Manager', 'acme-support' ),
			array(
				'read'          => true,
				self::CAP_AGENT => true,
				self::CAP_MANAGE => true,
				'upload_files'  => true,
			)
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_AGENT );
			$admin->add_cap( self::CAP_MANAGE );
		}
	}
}
