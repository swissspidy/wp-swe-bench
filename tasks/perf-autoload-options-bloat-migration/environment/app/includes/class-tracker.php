<?php
/**
 * Hooks into WordPress events and writes log entries.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Event tracker.
 */
class Tracker {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'wp_login', array( $this, 'on_login' ), 10, 2 );
		add_action( 'user_register', array( $this, 'on_user_register' ) );
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_deactivated' ) );
	}

	/**
	 * Login.
	 *
	 * @param string   $login User login.
	 * @param \WP_User $user  User.
	 */
	public function on_login( $login, $user ) {
		acme_activity_log(
			'user_login',
			array(
				'user_id'     => $user->ID,
				'object_type' => 'user',
				'object_id'   => $user->ID,
				/* translators: %s: user login */
				'message'     => sprintf( __( '%s logged in', 'acme-activity-log' ), $login ),
			)
		);
	}

	/**
	 * Registration.
	 *
	 * @param int $user_id New user.
	 */
	public function on_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		acme_activity_log(
			'user_registered',
			array(
				'object_type' => 'user',
				'object_id'   => $user_id,
				/* translators: %s: user login */
				'message'     => sprintf( __( 'New user %s', 'acme-activity-log' ), $user ? $user->user_login : '#' . $user_id ),
				'context'     => array( 'roles' => $user ? array_values( $user->roles ) : array() ),
			)
		);
	}

	/**
	 * Publishing / trashing.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->public ) {
			return;
		}

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$action = 'post_published';
			/* translators: 1: post type label, 2: post title */
			$message = sprintf( __( '%1$s “%2$s” published', 'acme-activity-log' ), $type->labels->singular_name, $post->post_title );
		} elseif ( 'trash' === $new_status && 'trash' !== $old_status ) {
			$action = 'post_trashed';
			/* translators: 1: post type label, 2: post title */
			$message = sprintf( __( '%1$s “%2$s” moved to the trash', 'acme-activity-log' ), $type->labels->singular_name, $post->post_title );
		} else {
			return;
		}

		acme_activity_log(
			$action,
			array(
				'object_type' => $post->post_type,
				'object_id'   => $post->ID,
				'message'     => $message,
				'context'     => array( 'from' => $old_status ),
			)
		);
	}

	/**
	 * Plugin activated.
	 *
	 * @param string $plugin Plugin file.
	 */
	public function on_plugin_activated( $plugin ) {
		acme_activity_log(
			'plugin_activated',
			array(
				'object_type' => 'plugin',
				/* translators: %s: plugin file */
				'message'     => sprintf( __( 'Plugin %s activated', 'acme-activity-log' ), $plugin ),
				'context'     => array( 'plugin' => $plugin ),
			)
		);
	}

	/**
	 * Plugin deactivated.
	 *
	 * @param string $plugin Plugin file.
	 */
	public function on_plugin_deactivated( $plugin ) {
		acme_activity_log(
			'plugin_deactivated',
			array(
				'object_type' => 'plugin',
				/* translators: %s: plugin file */
				'message'     => sprintf( __( 'Plugin %s deactivated', 'acme-activity-log' ), $plugin ),
				'context'     => array( 'plugin' => $plugin ),
			)
		);
	}
}
