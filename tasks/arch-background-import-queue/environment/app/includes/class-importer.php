<?php
/**
 * CSV import.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Imports a price list.
 *
 * @todo Large files (5000+ rows) run into the PHP/proxy timeouts of our hosts.
 */
class Importer {

	/**
	 * Option holding the summary of the last import.
	 */
	const LAST_RUN_OPTION = 'acme_importer_last_run';

	/**
	 * Products.
	 *
	 * @var Product_Repository
	 */
	private $products;

	/**
	 * Row mapper.
	 *
	 * @var Row_Mapper
	 */
	private $mapper;

	/**
	 * Constructor.
	 *
	 * @param Product_Repository $products Products.
	 * @param Row_Mapper         $mapper   Mapper.
	 */
	public function __construct( Product_Repository $products, Row_Mapper $mapper ) {
		$this->products = $products;
		$this->mapper   = $mapper;
	}

	/**
	 * Imports a file.
	 *
	 * @param string $path      File path.
	 * @param string $file_name Original file name (for the log).
	 * @return array{created:int, updated:int, skipped:int, failed:int, errors:array}|WP_Error
	 */
	public function import_file( $path, $file_name = '' ) {
		$reader = new Csv_Reader( $path );
		$opened = $reader->open();
		if ( is_wp_error( $opened ) ) {
			return $opened;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- big files.
		}
		wp_defer_term_counting( true );

		$result = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $reader->rows() as $row_number => $row ) {
			$outcome = $this->import_row( $row, $row_number );
			++$result[ $outcome['result'] ];
			if ( 'failed' === $outcome['result'] ) {
				$result['errors'][] = array(
					'row'   => $row_number,
					'sku'   => $row['sku'] ?? '',
					'error' => $outcome['error'],
				);
			}
		}
		$reader->close();
		wp_defer_term_counting( false );

		update_option(
			self::LAST_RUN_OPTION,
			array(
				'file'     => $file_name,
				'finished' => time(),
				'user'     => get_current_user_id(),
				'created'  => $result['created'],
				'updated'  => $result['updated'],
				'skipped'  => $result['skipped'],
				'failed'   => $result['failed'],
				'errors'   => array_slice( $result['errors'], 0, 50 ),
			),
			false
		);

		/**
		 * Fires after an import finished.
		 *
		 * @param array  $result    Counts.
		 * @param string $file_name File name.
		 */
		do_action( 'acme_importer_finished', $result, $file_name );

		return $result;
	}

	/**
	 * Imports one row.
	 *
	 * @param array $row        CSV row.
	 * @param int   $row_number Row number.
	 * @return array{result: string, error: string, id: int}
	 */
	public function import_row( array $row, $row_number ) {
		$data = $this->mapper->map( $row, $row_number );
		if ( false === $data ) {
			return array(
				'result' => 'skipped',
				'error'  => '',
				'id'     => 0,
			);
		}

		$saved = $this->products->upsert( $data );
		if ( is_wp_error( $saved ) ) {
			return array(
				'result' => 'failed',
				'error'  => $saved->get_error_message(),
				'id'     => 0,
			);
		}

		return array(
			'result' => $saved['created'] ? 'created' : 'updated',
			'error'  => '',
			'id'     => $saved['id'],
		);
	}
}
