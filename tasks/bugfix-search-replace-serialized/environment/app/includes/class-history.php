<?php
/**
 * Run history (Tools → Acme Migrate → Recent runs).
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the last runs in the `acme_migrate_history` option.
 */
class History {

	const OPTION = 'acme_migrate_history';
	const LIMIT  = 20;

	/**
	 * Record a run.
	 *
	 * @param string $search  Search.
	 * @param string $replace Replace.
	 * @param Report $report  Report.
	 * @param bool   $dry_run Dry run.
	 */
	public static function record( $search, $replace, Report $report, $dry_run ) {
		$history = self::get();
		array_unshift(
			$history,
			array(
				'time'         => time(),
				'user'         => get_current_user_id(),
				'search'       => $search,
				'replace'      => $replace,
				'dry_run'      => (bool) $dry_run,
				'rows'         => $report->total_rows(),
				'replacements' => $report->total_replacements(),
			)
		);
		update_option( self::OPTION, array_slice( $history, 0, self::LIMIT ), false );
	}

	/**
	 * All recorded runs, newest first.
	 *
	 * @return array[]
	 */
	public static function get() {
		$history = get_option( self::OPTION, array() );
		return is_array( $history ) ? $history : array();
	}
}
