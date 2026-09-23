<?php
/**
 * CSV import of redirect rules.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Imports rules from a CSV file in the export format (see Rule::csv_columns()).
 *
 * The file is streamed row by row, every row is validated like a rule saved on
 * Tools → Redirects, and all writes go through the repository.
 */
class Csv_Importer {

	const CREATED = 'created';
	const UPDATED = 'updated';
	const SKIPPED = 'skipped';
	const INVALID = 'invalid';

	/**
	 * Repository.
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Validator.
	 *
	 * @var Rule_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules     Repository.
	 * @param Rule_Validator  $validator Validator.
	 */
	public function __construct( Rule_Repository $rules, Rule_Validator $validator ) {
		$this->rules     = $rules;
		$this->validator = $validator;
	}

	/**
	 * Imports a file.
	 *
	 * @param string        $file    Path to the CSV file.
	 * @param array         $options {
	 *     @type bool $dry_run Validate and count only, change nothing.
	 *     @type bool $update  Update rules whose source (and match type) already exists instead of skipping them.
	 * }
	 * @param callable|null $on_row  Called for every row: ( int $row, string $source, string $result, string $message ).
	 * @return array{created:int, updated:int, skipped:int, invalid:int}|WP_Error
	 */
	public function import( $file, array $options = array(), $on_row = null ) {
		$options = wp_parse_args(
			$options,
			array(
				'dry_run' => false,
				'update'  => false,
			)
		);

		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			/* translators: %s: file name */
			return new WP_Error( 'acme_redirects_csv_file', sprintf( __( 'Cannot read %s.', 'acme-redirects' ), $file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			/* translators: %s: file name */
			return new WP_Error( 'acme_redirects_csv_file', sprintf( __( 'Cannot read %s.', 'acme-redirects' ), $file ) );
		}

		$header = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $header ) || array( null ) === $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'acme_redirects_csv_header', __( 'The file is empty.', 'acme-redirects' ) );
		}
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$header    = array_map(
			static function ( $name ) {
				return strtolower( trim( (string) $name ) );
			},
			$header
		);
		if ( ! in_array( 'source', $header, true ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			/* translators: %s: list of column names */
			return new WP_Error( 'acme_redirects_csv_header', sprintf( __( 'The first row must name the columns (%s) and include "source".', 'acme-redirects' ), implode( ', ', Rule::csv_columns() ) ) );
		}

		$counts = array(
			self::CREATED => 0,
			self::UPDATED => 0,
			self::SKIPPED => 0,
			self::INVALID => 0,
		);
		// Rules this run created (or would create in a dry run): key => ID (0 in a dry run).
		$seen   = array();
		$row_no = 1;

		while ( false !== ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$row_no;
			if ( array( null ) === $cells ) {
				continue; // Blank line.
			}

			$input = array();
			foreach ( $header as $i => $name ) {
				if ( in_array( $name, Rule::csv_columns(), true ) ) {
					$input[ $name ] = isset( $cells[ $i ] ) ? (string) $cells[ $i ] : '';
				}
			}
			$source = trim( $input['source'] ?? '' );

			$data = $this->validator->validate( $input, array( 'check_duplicate' => false ) );
			if ( is_wp_error( $data ) ) {
				$this->report( $on_row, $counts, $row_no, $source, self::INVALID, implode( ' ', $data->get_error_messages() ) );
				continue;
			}

			$key      = $data['match_type'] . '|' . $data['source'];
			$existing = $this->rules->find_by_source( $data['source'], $data['match_type'] );
			$id       = $existing ? $existing->id : ( array_key_exists( $key, $seen ) ? $seen[ $key ] : null );

			if ( null !== $id && ! $options['update'] ) {
				$this->report(
					$on_row,
					$counts,
					$row_no,
					$source,
					self::SKIPPED,
					$id
						/* translators: %d: rule ID */
						? sprintf( __( 'A rule with this source already exists (#%d).', 'acme-redirects' ), $id )
						: __( 'A rule with this source appears earlier in the file.', 'acme-redirects' )
				);
				continue;
			}

			if ( null !== $id ) {
				if ( ! $options['dry_run'] ) {
					$data['id'] = $id;
					$result     = $this->rules->update( Rule::from_array( $data ) );
					if ( is_wp_error( $result ) ) {
						$this->report( $on_row, $counts, $row_no, $source, self::INVALID, $result->get_error_message() );
						continue;
					}
				}
				/* translators: %d: rule ID */
				$this->report( $on_row, $counts, $row_no, $source, self::UPDATED, $id ? sprintf( __( 'Updated rule #%d.', 'acme-redirects' ), $id ) : '' );
				continue;
			}

			$new_id = 0;
			if ( ! $options['dry_run'] ) {
				$new_id = $this->rules->insert( Rule::from_array( $data ) );
				if ( is_wp_error( $new_id ) ) {
					$this->report( $on_row, $counts, $row_no, $source, self::INVALID, $new_id->get_error_message() );
					continue;
				}
			}
			$seen[ $key ] = $new_id;
			/* translators: %d: rule ID */
			$this->report( $on_row, $counts, $row_no, $source, self::CREATED, $new_id ? sprintf( __( 'Created rule #%d.', 'acme-redirects' ), $new_id ) : '' );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $counts;
	}

	/**
	 * Counts a row and tells the caller.
	 *
	 * @param callable|null $on_row  Callback.
	 * @param array         $counts  Counters.
	 * @param int           $row     Row number (the header is row 1).
	 * @param string        $source  Source as given.
	 * @param string        $result  created|updated|skipped|invalid.
	 * @param string        $message Message.
	 */
	private function report( $on_row, array &$counts, $row, $source, $result, $message ) {
		++$counts[ $result ];
		if ( $on_row ) {
			call_user_func( $on_row, $row, $source, $result, $message );
		}
	}
}
