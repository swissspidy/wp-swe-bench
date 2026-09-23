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
		$this->dry_run = ! empty( $args['dry_run'] );
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

		$like    = '%' . $wpdb->esc_like( $this->search ) . '%';
		$exclude = Table_Map::exclusions_sql( $table );
		$last_id = 0;

		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names come from the table map.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT `$primary` AS pk, `$column` AS value FROM `$table` WHERE `$primary` > %d AND `$column` LIKE %s $exclude ORDER BY `$primary` ASC LIMIT %d",
					$last_id,
					$like,
					self::BATCH_SIZE
				)
			);
			// phpcs:enable

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row->pk;

				if ( $this->dry_run ) {
					$report->add( $table, $column, 1, substr_count( (string) $row->value, $this->search ) );
					continue;
				}

				$count = 0;
				$value = acme_migrate_replace( $row->value, $this->search, $this->replace, $count );
				if ( $value === $row->value ) {
					continue;
				}

				// Values are escaped like everything else that goes into the database.
				$wpdb->update( $table, array( $column => wp_slash( $value ) ), array( $primary => $row->pk ) );
				$report->add( $table, $column, 1, $count );
			}
		} while ( is_array( $rows ) && count( $rows ) === self::BATCH_SIZE );
	}
}
