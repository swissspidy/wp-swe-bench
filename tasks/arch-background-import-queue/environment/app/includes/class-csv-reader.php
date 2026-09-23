<?php
/**
 * Streaming CSV reader for supplier price lists.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a CSV file record by record.
 *
 * - Detects the delimiter (comma, semicolon or tab) from the header line.
 * - Strips a UTF-8 byte order mark (Excel).
 * - Maps header names to our column names (see ALIASES), case-insensitively.
 * - Row numbers are what a spreadsheet shows: the header is row 1, every record
 *   (also blank ones, and records spanning several lines because of quoted line
 *   breaks) is one row.
 */
class Csv_Reader {

	/**
	 * Header aliases used by our suppliers => column.
	 */
	const ALIASES = array(
		'sku'            => 'sku',
		'article number' => 'sku',
		'artikelnummer'  => 'sku',
		'item_no'        => 'sku',
		'name'           => 'name',
		'title'          => 'name',
		'product name'   => 'name',
		'price'          => 'price',
		'price_eur'      => 'price',
		'preis'          => 'price',
		'stock'          => 'stock',
		'qty'            => 'stock',
		'quantity'       => 'stock',
		'status'         => 'status',
		'categories'     => 'categories',
		'category'       => 'categories',
		'description'    => 'description',
	);

	/**
	 * File path.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * File handle.
	 *
	 * @var resource|null
	 */
	private $handle = null;

	/**
	 * Delimiter.
	 *
	 * @var string
	 */
	private $delimiter = ',';

	/**
	 * Column names (null for unknown columns), by position.
	 *
	 * @var array<int,string|null>
	 */
	private $columns = array();

	/**
	 * Constructor.
	 *
	 * @param string $path File path.
	 */
	public function __construct( $path ) {
		$this->path = (string) $path;
	}

	/**
	 * Opens the file and reads the header.
	 *
	 * @return true|WP_Error
	 */
	public function open() {
		if ( ! is_readable( $this->path ) ) {
			return new WP_Error( 'acme_importer_unreadable', __( 'The file could not be read.', 'acme-importer' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$this->handle = fopen( $this->path, 'r' );
		if ( ! $this->handle ) {
			return new WP_Error( 'acme_importer_unreadable', __( 'The file could not be read.', 'acme-importer' ) );
		}

		$first = (string) fgets( $this->handle );
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$this->delimiter = $this->detect_delimiter( $first );
		rewind( $this->handle );
		if ( "\xEF\xBB\xBF" !== fread( $this->handle, 3 ) ) {
			rewind( $this->handle );
		}

		$header = fgetcsv( $this->handle, 0, $this->delimiter, '"', '' );
		if ( ! is_array( $header ) ) {
			return new WP_Error( 'acme_importer_empty', __( 'The file is empty.', 'acme-importer' ) );
		}
		$this->columns = array();
		foreach ( $header as $i => $name ) {
			$key                 = strtolower( trim( (string) $name ) );
			$this->columns[ $i ] = self::ALIASES[ $key ] ?? null;
		}
		if ( ! in_array( 'sku', $this->columns, true ) ) {
			return new WP_Error( 'acme_importer_no_sku_column', __( 'The file has no SKU column.', 'acme-importer' ) );
		}
		return true;
	}

	/**
	 * Known columns found in the header.
	 *
	 * @return string[]
	 */
	public function columns() {
		return array_values( array_filter( $this->columns ) );
	}

	/**
	 * Iterates over the data rows.
	 *
	 * Blank records are counted but not returned.
	 *
	 * @return \Generator<int,array<string,string>> Row number => column => value.
	 */
	public function rows() {
		$row_number = 1;
		while ( $this->handle && false !== ( $cells = fgetcsv( $this->handle, 0, $this->delimiter, '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$row_number;
			if ( array( null ) === $cells || '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}
			$row = array();
			foreach ( $this->columns as $i => $column ) {
				if ( null !== $column ) {
					$row[ $column ] = isset( $cells[ $i ] ) ? trim( (string) $cells[ $i ] ) : '';
				}
			}
			yield $row_number => $row;
		}
	}

	/**
	 * Closes the file.
	 */
	public function close() {
		if ( $this->handle ) {
			fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->handle = null;
		}
	}

	/**
	 * Picks the delimiter that occurs most often in the header line.
	 *
	 * @param string $line Header line.
	 * @return string
	 */
	private function detect_delimiter( $line ) {
		$best  = ',';
		$count = 0;
		foreach ( array( ',', ';', "\t" ) as $candidate ) {
			$n = substr_count( $line, $candidate );
			if ( $n > $count ) {
				$best  = $candidate;
				$count = $n;
			}
		}
		return $best;
	}
}
