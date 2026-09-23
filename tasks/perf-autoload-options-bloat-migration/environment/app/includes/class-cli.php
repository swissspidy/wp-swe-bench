<?php
/**
 * WP-CLI: `wp acme-activity …`.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Inspect the activity log.
 */
class CLI {

	/**
	 * List entries, newest first.
	 *
	 * ## OPTIONS
	 *
	 * [--action=<action>]
	 * : Only this action.
	 *
	 * [--user=<id>]
	 * : Only this user ID.
	 *
	 * [--search=<text>]
	 * : Message contains this text.
	 *
	 * [--per-page=<n>]
	 * : Entries per page (-1 for all).
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--page=<n>]
	 * : Page.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - ids
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function list_( $args, $assoc ) {
		$query = array(
			'per_page' => (int) $assoc['per-page'],
			'page'     => (int) $assoc['page'],
		);
		if ( isset( $assoc['action'] ) ) {
			$query['action'] = $assoc['action'];
		}
		if ( isset( $assoc['user'] ) ) {
			$query['user_id'] = (int) $assoc['user'];
		}
		if ( isset( $assoc['search'] ) ) {
			$query['search'] = $assoc['search'];
		}

		$entries = acme_activity_get_entries( $query );

		if ( 'ids' === $assoc['format'] ) {
			WP_CLI::line( implode( ' ', wp_list_pluck( $entries, 'id' ) ) );
			return;
		}

		$rows = array_map(
			static function ( $e ) {
				return array(
					'id'      => $e['id'],
					'time'    => gmdate( 'Y-m-d H:i:s', $e['time'] ),
					'user_id' => $e['user_id'],
					'action'  => $e['action'],
					'object'  => trim( $e['object_type'] . ' ' . ( $e['object_id'] ? $e['object_id'] : '' ) ),
					'message' => $e['message'],
				);
			},
			$entries
		);
		Utils\format_items( $assoc['format'], $rows, array( 'id', 'time', 'user_id', 'action', 'object', 'message' ) );
	}

	/**
	 * Count entries.
	 *
	 * ## OPTIONS
	 *
	 * [--action=<action>]
	 * : Only this action.
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function count( $args, $assoc ) {
		$query = array();
		if ( isset( $assoc['action'] ) ) {
			$query['action'] = $assoc['action'];
		}
		WP_CLI::line( (string) acme_activity_count_entries( $query ) );
	}
}

WP_CLI::add_command( 'acme-activity', CLI::class );
