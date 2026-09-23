<?php
/**
 * Public API. Other plugins and our themes call these; keep them stable.
 *
 * @package Acme\ActivityLog
 */

use Acme\ActivityLog\Plugin;
use Acme\ActivityLog\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Log an event.
 *
 * @param string $action Action key, e.g. 'post_published'. Built-in actions are only
 *                       logged when enabled in the settings; custom actions always are.
 * @param array  $args {
 *     @type int    $user_id     Acting user. Default: current user.
 *     @type string $object_type Object type ('post', 'user', 'plugin', …).
 *     @type int    $object_id   Object ID.
 *     @type string $message     Human-readable message.
 *     @type array  $context     Extra data.
 *     @type int    $time        Unix timestamp. Default: now.
 * }
 * @return int|false New entry ID, false if not logged.
 */
function acme_activity_log( $action, array $args = array() ) {
	$action = sanitize_key( $action );
	if ( '' === $action ) {
		return false;
	}
	if ( isset( Settings::events()[ $action ] ) && ! Settings::is_tracked( $action ) ) {
		return false;
	}

	$settings = Settings::get();
	$entry    = array(
		'time'        => isset( $args['time'] ) ? (int) $args['time'] : time(),
		'user_id'     => isset( $args['user_id'] ) ? (int) $args['user_id'] : get_current_user_id(),
		'action'      => $action,
		'object_type' => isset( $args['object_type'] ) ? sanitize_key( $args['object_type'] ) : '',
		'object_id'   => isset( $args['object_id'] ) ? (int) $args['object_id'] : 0,
		'message'     => isset( $args['message'] ) ? wp_strip_all_tags( (string) $args['message'] ) : '',
		'ip'          => ! empty( $settings['log_ip'] ) ? acme_activity_client_ip() : '',
		'context'     => isset( $args['context'] ) && is_array( $args['context'] ) ? $args['context'] : array(),
	);

	/**
	 * Filters an entry before it is stored. Return false to skip logging.
	 *
	 * @param array|false $entry Entry (without ID).
	 */
	$entry = apply_filters( 'acme_activity_entry_data', $entry );
	if ( ! is_array( $entry ) ) {
		return false;
	}

	$id = Plugin::instance()->store->insert( $entry );
	if ( ! $id ) {
		return false;
	}
	$entry['id'] = $id;

	/**
	 * Fires after an entry was logged.
	 *
	 * @param array $entry The stored entry (normalized, with ID).
	 */
	do_action( 'acme_activity_logged', $entry );

	return $id;
}

/**
 * Query log entries, newest first.
 *
 * @param array $args {
 *     @type string|string[] $action      Only these actions.
 *     @type int             $user_id     Only this user.
 *     @type string          $object_type Only this object type.
 *     @type int             $object_id   Only this object ID.
 *     @type int             $since       Unix timestamp, inclusive.
 *     @type int             $until       Unix timestamp, inclusive.
 *     @type string          $search      Case-insensitive substring of the message.
 *     @type string          $order       'DESC' (default, newest first) or 'ASC'.
 *     @type int             $per_page    Default 20, -1 for all.
 *     @type int             $page        1-based page.
 * }
 * @return array[] Entries: id, time, user_id, action, object_type, object_id, message, ip, context.
 */
function acme_activity_get_entries( array $args = array() ) {
	return Plugin::instance()->store->query( $args, false )['entries'];
}

/**
 * Count entries matching the same args as acme_activity_get_entries() (paging ignored).
 *
 * @param array $args Query args.
 * @return int
 */
function acme_activity_count_entries( array $args = array() ) {
	return Plugin::instance()->store->count( $args );
}

/**
 * One entry.
 *
 * @param int $id Entry ID.
 * @return array|null
 */
function acme_activity_get_entry( $id ) {
	return Plugin::instance()->store->get( $id );
}

/**
 * Delete entries.
 *
 * @param int[] $ids Entry IDs.
 * @return int Number deleted.
 */
function acme_activity_delete_entries( array $ids ) {
	return Plugin::instance()->store->delete( $ids );
}

/**
 * UI state of the log screen for a user.
 *
 * @param int $user_id User ID (default: current user).
 * @return array{per_page:int, hidden_columns:string[], last_seen:int}
 */
function acme_activity_get_user_state( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return Plugin::instance()->state->get( $user_id );
}

/**
 * Change the UI state of a user.
 *
 * @param int   $user_id User ID.
 * @param array $changes per_page, hidden_columns and/or last_seen.
 * @return array New state.
 */
function acme_activity_update_user_state( $user_id, array $changes ) {
	return Plugin::instance()->state->update( (int) $user_id, $changes );
}

/**
 * Client IP of the current request.
 *
 * @return string
 */
function acme_activity_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}
