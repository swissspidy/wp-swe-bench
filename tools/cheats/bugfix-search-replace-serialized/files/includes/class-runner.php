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

	protected $include_guids;

	/**
	 * Constructor.
	 *
	 * @param string $search  Search.
	 * @param string $replace Replace.
	 * @param array  $args    See acme_migrate_run().
	 */
	public function __construct( $search, $replace, $args ) {
		$this->search  = $search;
		$this->replace = $replace;
		$this->tables  = array_filter( array_map( 'trim', (array) $args['tables'] ) );
		$this->dry_run       = ! empty( $args['dry_run'] );
		$this->include_guids = ! empty( $args['include_guids'] );
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
				$this->run_column( $table, $definition['primary'], $column, $report );
			}
		}

		wp_cache_flush();

		return $report;
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

		$replacer = new Replacer( $this->search, $this->replace );
		$likes    = array();
		foreach ( array_keys( $replacer->get_pairs() ) as $form ) {
			$likes[] = $wpdb->prepare( "`$column` LIKE %s", '%' . $wpdb->esc_like( $form ) . '%' ); // phpcs:ignore
		}
		$like_sql = '(' . implode( ' OR ', $likes ) . ')';
		$exclude  = Table_Map::exclusions_sql( $table );
		if ( 'guid' === $column && empty( $this->include_guids ) ) {
			return;
		}
		$last_id = 0;

		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names come from the table map.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `$primary` AS pk, `$column` AS value FROM `$table` WHERE `$primary` > %d AND $like_sql $exclude ORDER BY `$primary` ASC LIMIT %d",
					$last_id,
					self::BATCH_SIZE
				)
			);
			// phpcs:enable

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row->pk;

				$count = 0;
				$value = acme_migrate_replace( $row->value, $this->search, $this->replace, $count );
				if ( $value === $row->value ) {
					continue;
				}
				$report->add( $table, $column, 1, $count );
				if ( $this->dry_run ) {
					continue;
				}
				$wpdb->update( $table, array( $column => $value ), array( $primary => $row->pk ) );
			}
		} while ( is_array( $rows ) && count( $rows ) === self::BATCH_SIZE );
	}
}
