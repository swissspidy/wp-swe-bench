<?php
/**
 * WP-CLI command.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Site migration helpers.
 */
class CLI_Command {

	/**
	 * Search & replace across the database.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : String to look for, e.g. the old site URL.
	 *
	 * <replace>
	 * : Replacement, e.g. the new site URL.
	 *
	 * [--tables=<tables>]
	 * : Comma-separated list of tables (with prefix) to limit the run to.
	 *
	 * [--dry-run]
	 * : Report what would change without changing anything.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-migrate search-replace http://staging.example.com https://example.com --dry-run
	 *
	 * @subcommand search-replace
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function search_replace( $args, $assoc_args ) {
		list( $search, $replace ) = $args;

		$format  = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$dry_run = ! empty( $assoc_args['dry_run'] );
		$tables  = isset( $assoc_args['tables'] ) ? explode( ',', $assoc_args['tables'] ) : array();

		$report = acme_migrate_run(
			$search,
			$replace,
			array(
				'tables'  => $tables,
				'dry_run' => $dry_run,
			)
		);

		if ( is_wp_error( $report ) ) {
			WP_CLI::error( $report->get_error_message() );
		}

		foreach ( $report->get_warnings() as $warning ) {
			WP_CLI::warning( $warning );
		}

		if ( 'count' === $format ) {
			WP_CLI::line( (string) $report->total_replacements() );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::line( (string) wp_json_encode( $report->get_items() ) );
			return;
		}

		$items = $report->get_items();
		if ( $items ) {
			Utils\format_items( 'table', $items, array( 'table', 'column', 'rows', 'replacements' ) );
		}
		WP_CLI::success( acme_migrate_summary( $report, $dry_run ) );
	}

	/**
	 * List recent runs.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function history( $args, $assoc_args ) {
		$items = array_map(
			static function ( $run ) {
				$run['time']    = gmdate( 'Y-m-d H:i:s', (int) $run['time'] );
				$run['dry_run'] = $run['dry_run'] ? 'yes' : 'no';
				return $run;
			},
			History::get()
		);
		Utils\format_items( Utils\get_flag_value( $assoc_args, 'format', 'table' ), $items, array( 'time', 'search', 'replace', 'dry_run', 'rows', 'replacements' ) );
	}
}
