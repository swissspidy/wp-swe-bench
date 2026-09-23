<?php
/**
 * Walks the database and replaces.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * One search & replace run.
 */
class Runner {

	/**
	 * Rows fetched per query.
	 */
	const BATCH_SIZE = 200;

	/**
	 * Search.
	 *
	 * @var string
	 */
	protected $search;

	/**
	 * Replace.
	 *
	 * @var string
	 */
	protected $replace;

	/**
	 * Limit to these tables (empty: all).
	 *
	 * @var string[]
	 */
	protected $tables;

	/**
	 * Only report.
	 *
	 * @var bool
	 */
	protected $dry_run;

	/**
	 * Also replace in the posts table's GUID column.
	 *
	 * @var bool
	 */
	protected $include_guids;

	/**
	 * Replacer (one per run; counts are reset per value).
	 *
	 * @var Replacer
	 */
	protected $replacer;

	/**
	 * Constructor.
	 *
	 * @param string $search  Search.
	 * @param string $replace Replace.
	 * @param array  $args    See acme_migrate_run().
	 */
	public function __construct( $search, $replace, $args ) {
		$this->search        = $search;
		$this->replace       = $replace;
		$this->tables        = array_filter( array_map( 'trim', (array) $args['tables'] ) );
		$this->dry_run       = ! empty( $args['dry_run'] );
		$this->include_guids = ! empty( $args['include_guids'] );
		$this->replacer      = new Replacer( $search, $replace );
	}

	/**
	 * Run.
	 *
	 * @return Report
	 */
	public function run() {
		$report = new Report();

		foreach ( Table_Map::get() as $table => $definition ) {
			if ( $this->tables && ! in_array( $table, $this->tables, true ) ) {
				continue;
			}
			foreach ( $definition['columns'] as $column ) {
				if ( ! $this->include_guids && $this->is_guid_column( $table, $column ) ) {
					// GUIDs identify posts in feeds and must not change when a site moves.
					continue;
				}
				$this->run_column( $table, $definition['primary'], $column, $report );
			}
		}

		if ( ! $this->dry_run ) {
			wp_cache_flush();
		}

		return $report;
	}

	/**
	 * Whether a column is the posts table's GUID column.
	 *
	 * @param string $table  Table.
	 * @param string $column Column.
	 * @return bool
	 */
	protected function is_guid_column( $table, $column ) {
		global $wpdb;
		return $table === $wpdb->posts && 'guid' === $column;
	}

	/**
	 * Replace in one column.
	 *
	 * @param string $table   Table.
	 * @param string $primary Primary key column.
	 * @param string $column  Column.
	 * @param Report $report  Report.
	 */
	protected function run_column( $table, $primary, $column, Report $report ) {
		global $wpdb;

		$exclude = Table_Map::exclusions_sql( $table );
		$last_id = 0;

		/*
		 * Every row is inspected in PHP: the search string may be stored JSON-escaped or URL-encoded,
		 * and databases don't agree on how LIKE treats backslashes and binary data.
		 */
		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names come from the table map.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `$primary` AS pk, `$column` AS value FROM `$table` WHERE `$primary` > %d $exclude ORDER BY `$primary` ASC LIMIT %d",
					$last_id,
					self::BATCH_SIZE
				)
			);
			// phpcs:enable

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row->pk;

				if ( null === $row->value || ! $this->replacer->might_match( $row->value ) ) {
					continue;
				}

				$count = 0;
				$value = acme_migrate_replace( $row->value, $this->search, $this->replace, $count );
				if ( $value === $row->value || 0 === $count ) {
					continue;
				}

				$report->add( $table, $column, 1, $count );

				if ( $this->dry_run ) {
					continue;
				}

				// $wpdb->update() escapes the value itself: pass it exactly as it must be stored.
				$updated = $wpdb->update( $table, array( $column => $value ), array( $primary => $row->pk ), array( '%s' ), array( '%d' ) );
				if ( false === $updated ) {
					$report->warn(
						sprintf(
							/* translators: 1: table, 2: column, 3: primary key value */
							__( 'Could not update %1$s.%2$s for row %3$d.', 'acme-migrate' ),
							$table,
							$column,
							$row->pk
						)
					);
				}
			}
			$fetched = is_array( $rows ) ? count( $rows ) : 0;
		} while ( self::BATCH_SIZE === $fetched );
	}
}
