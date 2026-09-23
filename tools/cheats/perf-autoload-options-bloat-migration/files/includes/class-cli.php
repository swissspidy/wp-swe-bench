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

	/**
	 * Finish the move of the pre-3.0 log (options) into the log table now,
	 * batch by batch. Safe to interrupt and to run again.
	 *
	 * ## OPTIONS
	 *
	 * [--batch-size=<n>]
	 * : Entries per batch (max 1000).
	 * ---
	 * default: 500
	 * ---
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function migrate( $args, $assoc ) {
		$plugin    = Plugin::instance();
		$migration = $plugin->migration;

		Schema::install();
		if ( Migration::is_done() ) {
			WP_CLI::success( 'Nothing to migrate: the activity log is already stored in its table.' );
			return;
		}

		$size    = max( 1, min( 1000, (int) $assoc['batch-size'] ) );
		$batches = 0;
		while ( $migration->run_batch( $size ) ) {
			++$batches;
			$state = Migration::state();
			WP_CLI::log( sprintf( 'Batch %d: %d entries moved so far (chunk %d of %d).', $batches, (int) $state['migrated'], (int) $state['chunk'] + 1, count( (array) $state['chunks'] ) ) );
			if ( function_exists( 'wp_cache_flush_runtime' ) && 0 === $batches % 10 ) {
				wp_cache_flush_runtime();
			}
		}

		$state = Migration::state();
		WP_CLI::success( sprintf( 'Migration complete: %d entries moved.', $state ? (int) $state['migrated'] : 0 ) );
	}

	/**
	 * Delete entries older than the retention period.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<n>]
	 * : Keep this many days instead of the "Keep entries for" setting.
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function prune( $args, $assoc ) {
		$days = isset( $assoc['days'] ) ? Settings::sanitize_retention( $assoc['days'] ) : (int) Settings::get()['retention_days'];
		if ( $days <= 0 ) {
			WP_CLI::success( 'Retention is disabled (entries are kept forever); nothing deleted.' );
			return;
		}
		$deleted = Plugin::instance()->retention->prune( $days );
		WP_CLI::success( sprintf( 'Deleted %d entries older than %d days.', $deleted, $days ) );
	}
}

WP_CLI::add_command( 'acme-activity', CLI::class );
