<?php
/**
 * WP-CLI: `wp acme-importer …`.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Queue and run product imports from the shell.
 *
 * ## EXAMPLES
 *
 *     wp acme-importer queue supplier.csv --user=admin
 *     wp acme-importer run 12
 *     wp acme-importer status 12 --format=json
 */
class CLI_Command {

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * Constructor.
	 *
	 * @param Queue $queue Queue.
	 */
	public function __construct( Queue $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Queues a CSV file for import.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file.
	 *
	 * [--porcelain]
	 * : Only print the import ID.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function queue( $args, $assoc_args ) {
		$file = $args[0];
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $file ) );
		}
		$job = $this->queue->enqueue( $file, wp_basename( $file ), get_current_user_id() );
		if ( is_wp_error( $job ) ) {
			WP_CLI::error( $job->get_error_message() );
		}
		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
			WP_CLI::line( (string) $job->id );
			return;
		}
		WP_CLI::success( sprintf( 'Queued import %d (%d rows).', $job->id, $job->total ) );
	}

	/**
	 * Runs imports now, in this process, until they have ended (retries included).
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Import ID. Default: all queued and running imports, oldest first.
	 *
	 * [--batch-size=<n>]
	 * : Rows per batch (default: the "Rows per batch" setting).
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function run( $args, $assoc_args ) {
		$batch_size = isset( $assoc_args['batch-size'] ) ? max( 1, (int) $assoc_args['batch-size'] ) : null;

		if ( isset( $args[0] ) ) {
			$job = ctype_digit( (string) $args[0] ) ? $this->queue->jobs()->get( (int) $args[0] ) : null;
			if ( ! $job ) {
				WP_CLI::error( sprintf( 'Import %s not found.', $args[0] ) );
			}
			if ( ! in_array( $job->status, array( Job_Store::QUEUED, Job_Store::RUNNING ), true ) ) {
				WP_CLI::error( sprintf( 'Import %d is %s.', $job->id, $job->status ) );
			}
			$ids = array( (int) $job->id );
		} else {
			$ids = array_map( 'intval', wp_list_pluck( $this->queue->jobs()->unfinished(), 'id' ) );
			if ( ! $ids ) {
				WP_CLI::success( 'No imports to run.' );
				return;
			}
		}

		foreach ( $ids as $id ) {
			$this->run_job( $id, $batch_size );
		}
	}

	/**
	 * Runs one import to the end.
	 *
	 * @param int      $id         Import ID.
	 * @param int|null $batch_size Rows per batch.
	 */
	private function run_job( $id, $batch_size ) {
		while ( true ) {
			$result = $this->queue->run_once( $id, $batch_size );
			$job    = $this->queue->jobs()->get( $id );
			$data   = $this->queue->to_array( $job );

			if ( Queue::LOCKED === $result['state'] ) {
				WP_CLI::error( sprintf( 'Import %d is being processed by another process.', $id ) );
			}
			if ( $result['rows'] > 0 ) {
				WP_CLI::line( sprintf( 'Processed %d/%d rows (%d%%).', $data['processed'], $data['total'], $data['progress'] ) );
			}
			if ( Queue::ENDED === $result['state'] || ! in_array( $data['status'], array( Job_Store::QUEUED, Job_Store::RUNNING ), true ) ) {
				break;
			}
			if ( $result['next'] > time() ) {
				$wait = $result['next'] - time();
				WP_CLI::log( sprintf( 'Waiting %d s for %d row(s) to be retried…', $wait, $data['retrying'] ) );
				sleep( $wait );
			}
		}

		if ( Job_Store::COMPLETED !== $data['status'] ) {
			WP_CLI::error( sprintf( 'Import %d is %s.', $id, $data['status'] ) );
		}
		WP_CLI::success( sprintf( 'Import %d completed: %d created, %d updated, %d skipped, %d failed.', $id, $data['created'], $data['updated'], $data['skipped'], $data['failed'] ) );
	}

	/**
	 * Shows an import.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Import ID.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function status( $args, $assoc_args ) {
		$job = ctype_digit( (string) $args[0] ) ? $this->queue->jobs()->get( (int) $args[0] ) : null;
		if ( ! $job ) {
			WP_CLI::error( sprintf( 'Import %s not found.', $args[0] ) );
		}
		$data = $this->queue->to_array( $job );
		if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
			WP_CLI::line( (string) wp_json_encode( $data ) );
			return;
		}
		$rows = array();
		foreach ( $data as $field => $value ) {
			$rows[] = array(
				'Field' => $field,
				'Value' => null === $value ? '' : $value,
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'Field', 'Value' ) );
	}

	/**
	 * Cancels an import.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Import ID.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function cancel( $args, $assoc_args ) {
		$result = $this->queue->cancel( (int) $args[0] );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Cancelled import %d.', $result->id ) );
	}
}
