<?php
/**
 * Public helper functions.
 *
 * These are used by Acme Migrate Pro (the importer add-on) and by the agency's deploy scripts,
 * so their signatures must stay stable.
 *
 * @package Acme\Migrate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace a string in a single stored value.
 *
 * `$data` is a value as it is stored in the database (post content, a meta value, an option
 * value…). Serialized PHP values are rewritten without being unserialized (no object is ever
 * instantiated), nested serialized strings included. JSON-escaped (`http:\/\/…`) and URL-encoded
 * (`http%3A%2F%2F…`) occurrences of the search string are replaced with the equally encoded replacement.
 *
 * @since 1.0.0
 * @since 1.2.0 Added `$count`.
 * @since 1.5.0 Serialized data is rewritten in place; escaped and encoded forms are replaced.
 *
 * @param mixed    $data    Stored value.
 * @param string   $search  String to look for.
 * @param string   $replace Replacement.
 * @param int|null $count   Set to the number of replacements that were made.
 * @return mixed The value with all replacements made.
 */
function acme_migrate_replace( $data, $search, $replace, &$count = null ) {
	$replacer = new \Acme\Migrate\Replacer( (string) $search, (string) $replace );
	$result   = $replacer->run( $data );
	$count    = $replacer->get_count();
	return $result;
}

/**
 * Run a search & replace over the database.
 *
 * @since 1.0.0
 * @since 1.5.0 Added `include_guids`; GUIDs are skipped by default.
 *
 * @param string $search  String to look for.
 * @param string $replace Replacement.
 * @param array  $args {
 *     Optional. Run options.
 *
 *     @type string[] $tables  Table names (with prefix) to limit the run to. Default all known tables.
 *     @type bool     $dry_run       Only report what would change; nothing is written. Default false.
 *     @type bool     $include_guids Also replace in the GUID column of the posts table. Default false.
 * }
 * @return \Acme\Migrate\Report|WP_Error
 */
function acme_migrate_run( $search, $replace, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'tables'        => array(),
			'dry_run'       => false,
			'include_guids' => false,
		)
	);

	if ( '' === (string) $search ) {
		return new WP_Error( 'acme_migrate_empty_search', __( 'The search string must not be empty.', 'acme-migrate' ) );
	}
	if ( (string) $search === (string) $replace ) {
		return new WP_Error( 'acme_migrate_same_strings', __( 'The search and replacement strings are identical.', 'acme-migrate' ) );
	}

	$runner = new \Acme\Migrate\Runner( (string) $search, (string) $replace, $args );
	$report = $runner->run();

	\Acme\Migrate\History::record( $search, $replace, $report, (bool) $args['dry_run'] );

	/**
	 * Fires after a search & replace run.
	 *
	 * @since 1.1.0
	 *
	 * @param \Acme\Migrate\Report $report  Run report.
	 * @param string               $search  Search string.
	 * @param string               $replace Replacement string.
	 * @param array                $args    Run options.
	 */
	do_action( 'acme_migrate_after_run', $report, $search, $replace, $args );

	return $report;
}

/**
 * Tables (and their columns) a run walks.
 *
 * @since 1.0.0
 *
 * @return array<string, array{primary: string, columns: string[]}> Keyed by table name with prefix.
 */
function acme_migrate_get_tables() {
	return \Acme\Migrate\Table_Map::get();
}

/**
 * Format the one-line summary shown after a run.
 *
 * @since 1.3.0
 *
 * @param \Acme\Migrate\Report $report Report.
 * @param bool                 $dry_run Whether it was a dry run.
 * @return string
 */
function acme_migrate_summary( $report, $dry_run ) {
	if ( $dry_run ) {
		/* translators: 1: number of replacements, 2: number of rows */
		return sprintf( __( '%1$d replacements in %2$d rows would be made (dry run).', 'acme-migrate' ), $report->total_replacements(), $report->total_rows() );
	}
	/* translators: 1: number of replacements, 2: number of rows */
	return sprintf( __( 'Made %1$d replacements in %2$d rows.', 'acme-migrate' ), $report->total_replacements(), $report->total_rows() );
}
