<?php
/**
 * Run report.
 *
 * @package Acme\Migrate
 */

namespace Acme\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Per table/column counts of a run.
 */
class Report {

	/**
	 * Rows keyed by "table.column".
	 *
	 * @var array<string, array{table: string, column: string, rows: int, replacements: int}>
	 */
	protected $items = array();

	/**
	 * Warnings (e.g. values that could not be processed).
	 *
	 * @var string[]
	 */
	protected $warnings = array();

	/**
	 * Record a changed row.
	 *
	 * @param string $table        Table.
	 * @param string $column       Column.
	 * @param int    $rows         Rows changed.
	 * @param int    $replacements Replacements made.
	 */
	public function add( $table, $column, $rows, $replacements ) {
		$key = $table . '.' . $column;
		if ( ! isset( $this->items[ $key ] ) ) {
			$this->items[ $key ] = array(
				'table'        => $table,
				'column'       => $column,
				'rows'         => 0,
				'replacements' => 0,
			);
		}
		$this->items[ $key ]['rows']         += (int) $rows;
		$this->items[ $key ]['replacements'] += (int) $replacements;
	}

	/**
	 * Add a warning.
	 *
	 * @param string $message Message.
	 */
	public function warn( $message ) {
		$this->warnings[] = $message;
	}

	/**
	 * Warnings.
	 *
	 * @return string[]
	 */
	public function get_warnings() {
		return $this->warnings;
	}

	/**
	 * Report rows (only columns with changes).
	 *
	 * @return array<int, array{table: string, column: string, rows: int, replacements: int}>
	 */
	public function get_items() {
		return array_values(
			array_filter(
				$this->items,
				static function ( $item ) {
					return $item['rows'] > 0;
				}
			)
		);
	}

	/**
	 * Total replacements.
	 *
	 * @return int
	 */
	public function total_replacements() {
		return (int) array_sum( wp_list_pluck( $this->items, 'replacements' ) );
	}

	/**
	 * Total changed rows (a row changed in two columns counts twice).
	 *
	 * @return int
	 */
	public function total_rows() {
		return (int) array_sum( wp_list_pluck( $this->items, 'rows' ) );
	}

	/**
	 * Array form (stored in the history and in the admin notice transient).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'items'    => $this->get_items(),
			'warnings' => $this->warnings,
		);
	}

	/**
	 * Restore from to_array().
	 *
	 * @param array $data Data.
	 * @return Report
	 */
	public static function from_array( $data ) {
		$report = new self();
		foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
			$report->add( $item['table'], $item['column'], $item['rows'], $item['replacements'] );
		}
		foreach ( (array) ( $data['warnings'] ?? array() ) as $warning ) {
			$report->warn( $warning );
		}
		return $report;
	}
}
