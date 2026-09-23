<?php
/**
 * WP-CLI commands.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * `wp acme-crm …`
 */
class CLI {

	/**
	 * Register commands.
	 */
	public static function register() {
		\WP_CLI::add_command( 'acme-crm stats', array( __CLASS__, 'stats' ) );
		\WP_CLI::add_command( 'acme-crm migrate', Migrate_Command::class );
	}

	/**
	 * Show the number of contacts per stage.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public static function stats( $args, $assoc_args ) {
		$counts = Contacts::stage_counts();
		if ( is_wp_error( $counts ) ) {
			\WP_CLI::error( $counts->get_error_message() );
		}
		$rows = array();
		foreach ( $counts as $stage => $count ) {
			$rows[] = array(
				'stage'    => $stage,
				'contacts' => $count,
			);
		}
		\WP_CLI\Utils\format_items( isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table', $rows, array( 'stage', 'contacts' ) );
	}
}
