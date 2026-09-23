<?php
/**
 * Background processing of imports.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use Throwable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Queues uploaded price lists and imports them in batches (WP-Cron or WP-CLI).
 *
 * Flow:
 * - enqueue() checks the file, copies it to private storage and schedules the first batch.
 * - process() (cron) and run_once() (CLI) claim the job and import up to `batch_size`
 *   rows, persisting the counters and the read position after every row.
 * - Rows are validated first; invalid rows are logged and count as failed.
 * - Rows whose save throws or returns an error are retried later (3 attempts in
 *   total, 60 s / 300 s backoff); after the file was read completely, a job only
 *   processes its due retries until none is left.
 * - Before a batch starts, a watchdog event is scheduled for the moment the claim
 *   expires: if the runner dies, the watchdog picks the job up again.
 */
class Queue {

	const HOOK = 'acme_importer_process';

	const MAX_ATTEMPTS = 3;

	/** Results of run_once(). */
	const LOCKED  = 'locked';
	const BATCH   = 'batch';
	const WAITING = 'waiting';
	const ENDED   = 'ended';

	/**
	 * Jobs.
	 *
	 * @var Job_Store
	 */
	private $jobs;

	/**
	 * Row importer.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Row log.
	 *
	 * @var Row_Log
	 */
	private $rows;

	/**
	 * Row validator.
	 *
	 * @var Row_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param Job_Store     $jobs      Jobs.
	 * @param Importer      $importer  Importer.
	 * @param Row_Log       $rows      Row log.
	 * @param Row_Validator $validator Validator.
	 */
	public function __construct( Job_Store $jobs, Importer $importer, Row_Log $rows, Row_Validator $validator ) {
		$this->jobs      = $jobs;
		$this->importer  = $importer;
		$this->rows      = $rows;
		$this->validator = $validator;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'process' ) );
	}

	/**
	 * Jobs.
	 *
	 * @return Job_Store
	 */
	public function jobs() {
		return $this->jobs;
	}

	/**
	 * Row log.
	 *
	 * @return Row_Log
	 */
	public function rows() {
		return $this->rows;
	}

	/**
	 * Public representation of a job.
	 *
	 * @param object $job Job row.
	 * @return array
	 */
	public function to_array( $job ) {
		$data             = Job_Store::to_array( $job );
		$data['retrying'] = $this->rows->count_retries( (int) $job->id );
		return $data;
	}

	/**
	 * Seconds after which the claim of a runner that stopped reporting expires.
	 *
	 * @return int
	 */
	public static function lock_timeout() {
		/**
		 * Filters how long (in seconds) a runner's claim on an import stays valid
		 * without progress before another runner may take over.
		 *
		 * @param int $seconds Default 300.
		 */
		return max( 1, (int) apply_filters( 'acme_importer_lock_timeout', 5 * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Checks a file and queues it.
	 *
	 * @param string $tmp_path  Path of the file.
	 * @param string $file_name Original file name.
	 * @param int    $user_id   Uploader.
	 * @return object|WP_Error The job.
	 */
	public function enqueue( $tmp_path, $file_name, $user_id ) {
		$file_name = sanitize_file_name( wp_basename( (string) $file_name ) );
		if ( 'csv' !== strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'acme_importer_invalid_file', __( 'Please upload a CSV file.', 'acme-importer' ), array( 'status' => 400 ) );
		}

		$reader = new Csv_Reader( $tmp_path );
		$opened = $reader->open();
		if ( is_wp_error( $opened ) ) {
			return new WP_Error( 'acme_importer_invalid_file', $opened->get_error_message(), array( 'status' => 400 ) );
		}
		$total = $reader->count_rows();
		$reader->close();

		$dir = self::storage_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$path = $dir . '/import-' . wp_generate_password( 24, false ) . '.csv';
		if ( ! copy( $tmp_path, $path ) ) {
			return new WP_Error( 'acme_importer_storage', __( 'The file could not be stored.', 'acme-importer' ), array( 'status' => 500 ) );
		}

		$id = $this->jobs->insert(
			array(
				'file_name' => $file_name,
				'file_path' => $path,
				'user_id'   => $user_id,
				'total'     => $total,
			)
		);
		if ( ! $id ) {
			wp_delete_file( $path );
			return new WP_Error( 'acme_importer_storage', __( 'The import could not be queued.', 'acme-importer' ), array( 'status' => 500 ) );
		}

		$this->schedule( $id, time() );
		return $this->jobs->get( $id );
	}

	/**
	 * Cron callback: processes one batch of a job.
	 *
	 * @param int $job_id Job ID.
	 */
	public function process( $job_id ) {
		$this->run_once( (int) $job_id );
	}

	/**
	 * Processes one batch of a job, if nobody else is processing it.
	 *
	 * @param int      $job_id     Job ID.
	 * @param int|null $batch_size Rows (default: setting).
	 * @return array{state: string, next: int, rows: int} state: locked|batch|waiting|ended; next: when the
	 *         next batch/retry is due; rows: rows handled in this run.
	 */
	public function run_once( $job_id, $batch_size = null ) {
		$job = $this->jobs->get( $job_id );
		if ( ! $job || ! in_array( $job->status, array( Job_Store::QUEUED, Job_Store::RUNNING ), true ) ) {
			return array(
				'state' => self::ENDED,
				'next'  => 0,
				'rows'  => 0,
			);
		}

		$timeout = self::lock_timeout();
		$token   = wp_generate_password( 32, false );
		if ( ! $this->jobs->claim( $job_id, $token, $timeout ) ) {
			// Another runner is busy with it. Make sure the job is picked up again
			// if that runner dies (its claim expires).
			$this->schedule( $job_id, (int) $job->locked_at + $timeout + 1, false );
			return array(
				'state' => self::LOCKED,
				'next'  => 0,
				'rows'  => 0,
			);
		}

		try {
			return $this->run_batch( $job_id, $token, $timeout, $batch_size ? (int) $batch_size : batch_size() );
		} finally {
			$this->jobs->release( $job_id, $token );
		}
	}

	/**
	 * Processes a batch while holding the claim.
	 *
	 * @param int    $job_id     Job ID.
	 * @param string $token      Claim token.
	 * @param int    $timeout    Claim timeout.
	 * @param int    $batch_size Rows.
	 * @return array{state: string, next: int, rows: int}
	 */
	private function run_batch( $job_id, $token, $timeout, $batch_size ) {
		// Watchdog: if this process dies, continue once the claim expired.
		$this->schedule( $job_id, time() + $timeout + 1 );

		$this->jobs->start( $job_id );
		$job = $this->jobs->get( $job_id );

		wp_defer_term_counting( true );
		$done    = 0;
		$more    = false;
		$stopped = false;

		if ( ! $job->file_done ) {
			$reader = new Csv_Reader( (string) $job->file_path );
			if ( is_wp_error( $reader->open() ) ) {
				wp_defer_term_counting( false );
				$this->jobs->finish( $job_id, Job_Store::FAILED );
				$this->rows->drop_retries( $job_id );
				$this->unschedule( $job_id );
				return array(
					'state' => self::ENDED,
					'next'  => 0,
					'rows'  => 0,
				);
			}
			$reader->seek( (int) $job->file_offset, (int) $job->last_row );

			foreach ( $reader->rows() as $row_number => $row ) {
				if ( Job_Store::CANCELLED === $this->jobs->status( $job_id ) ) {
					$stopped = true;
					break;
				}
				if ( $done >= $batch_size ) {
					$more = true;
					break;
				}

				$result = $this->import_row( $job_id, $row, $row_number, 0 );
				++$done;
				if ( ! $this->jobs->record_row( $job_id, $result, $reader->tell(), $row_number, $token ) ) {
					$stopped = true;
					break;
				}
			}
			if ( ! $more && ! $stopped ) {
				$this->jobs->file_read( $job_id, $reader->tell(), $reader->row_number(), $token );
			}
			$reader->close();
		}

		// Retries that are due.
		if ( ! $more && ! $stopped && $done < $batch_size ) {
			foreach ( $this->rows->due_retries( $job_id, $batch_size - $done ) as $entry ) {
				if ( Job_Store::CANCELLED === $this->jobs->status( $job_id ) ) {
					$stopped = true;
					break;
				}
				$row    = json_decode( (string) $entry->data, true );
				$result = $this->import_row( $job_id, is_array( $row ) ? $row : array(), (int) $entry->line, (int) $entry->attempts );
				++$done;
				if ( ! $this->jobs->count_row( $job_id, $result, $token ) ) {
					$stopped = true;
					break;
				}
			}
			if ( ! $stopped && $this->rows->due_retries( $job_id, 1 ) ) {
				$more = true;
			}
		}
		wp_defer_term_counting( false );

		if ( $stopped ) {
			if ( Job_Store::CANCELLED === $this->jobs->status( $job_id ) ) {
				$this->rows->drop_retries( $job_id );
				$this->unschedule( $job_id );
			}
			return array(
				'state' => self::ENDED,
				'next'  => 0,
				'rows'  => $done,
			);
		}

		if ( $more ) {
			$this->schedule( $job_id, time() );
			return array(
				'state' => self::BATCH,
				'next'  => time(),
				'rows'  => $done,
			);
		}

		$next = $this->rows->next_retry_at( $job_id );
		if ( $next ) {
			$this->schedule( $job_id, $next );
			return array(
				'state' => $done ? self::BATCH : self::WAITING,
				'next'  => $next,
				'rows'  => $done,
			);
		}

		$this->complete( $job_id );
		return array(
			'state' => self::ENDED,
			'next'  => 0,
			'rows'  => $done,
		);
	}

	/**
	 * Validates and imports a row; logs invalid and failing rows.
	 *
	 * @param int   $job_id     Job ID.
	 * @param array $row        CSV row.
	 * @param int   $row_number Row number.
	 * @param int   $attempts   Failed attempts so far.
	 * @return string created|updated|skipped|failed|retry
	 */
	private function import_row( $job_id, array $row, $row_number, $attempts ) {
		if ( '' === normalize_sku( $row['sku'] ?? '' ) ) {
			return 'skipped';
		}

		if ( 0 === $attempts ) {
			$errors = $this->validator->validate( $row, $job_id );
			if ( $errors ) {
				$this->rows->put( $job_id, $row_number, $row, Row_Log::INVALID, 0, implode( ' ', $errors ) );
				return 'failed';
			}
		}

		try {
			$outcome = $this->importer->import_row( $row, $row_number );
			$error   = 'failed' === $outcome['result'] ? $outcome['error'] : '';
		} catch ( Throwable $e ) {
			$outcome = array( 'result' => 'failed' );
			$error   = $e->getMessage();
		}

		if ( 'failed' !== $outcome['result'] ) {
			if ( $attempts ) {
				$this->rows->delete( $job_id, $row_number );
			}
			return $outcome['result'];
		}

		++$attempts;
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->rows->put( $job_id, $row_number, $row, Row_Log::FAILED, $attempts, $error );
			return 'failed';
		}

		/**
		 * Filters the delay before the next attempt of a failed row.
		 *
		 * @param int   $seconds   Default: 60 after the first failed attempt, 300 after the second.
		 * @param int   $attempt   Failed attempts so far.
		 * @param array $row       CSV row (column => value).
		 * @param int   $import_id Import ID.
		 */
		$delay = (int) apply_filters( 'acme_importer_retry_delay', 60 * ( 5 ** ( $attempts - 1 ) ), $attempts, $row, (int) $job_id );
		$this->rows->put( $job_id, $row_number, $row, Row_Log::RETRY, $attempts, $error, time() + max( 0, $delay ) );
		return 'retry';
	}

	/**
	 * Marks a job as completed.
	 *
	 * @param int $job_id Job ID.
	 */
	protected function complete( $job_id ) {
		$this->unschedule( $job_id );
		if ( $this->jobs->finish( $job_id, Job_Store::COMPLETED ) ) {
			$job = $this->jobs->get( $job_id );
			$this->cleanup( $job );

			/** This action is documented in includes/class-importer.php */
			do_action(
				'acme_importer_finished',
				array(
					'created' => (int) $job->created,
					'updated' => (int) $job->updated,
					'skipped' => (int) $job->skipped,
					'failed'  => (int) $job->failed,
				),
				$job->file_name
			);
		}
	}

	/**
	 * Cancels a job.
	 *
	 * @param int $job_id Job ID.
	 * @return object|WP_Error The job.
	 */
	public function cancel( $job_id ) {
		$job = $this->jobs->get( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'acme_importer_not_found', __( 'Import not found.', 'acme-importer' ), array( 'status' => 404 ) );
		}
		if ( ! $this->jobs->finish( $job_id, Job_Store::CANCELLED ) ) {
			return new WP_Error( 'acme_importer_not_cancellable', __( 'This import has already ended.', 'acme-importer' ), array( 'status' => 409 ) );
		}
		$this->rows->drop_retries( $job_id );
		$this->unschedule( $job_id );
		$job = $this->jobs->get( $job_id );
		$this->cleanup( $job );
		return $job;
	}

	/**
	 * Removes the stored file of a finished job.
	 *
	 * @param object $job Job.
	 */
	protected function cleanup( $job ) {
		if ( $job && ! empty( $job->file_path ) && is_file( $job->file_path ) ) {
			wp_delete_file( $job->file_path );
		}
	}

	/**
	 * (Re)schedules the job's cron event.
	 *
	 * @param int  $job_id  Job ID.
	 * @param int  $when    Timestamp.
	 * @param bool $replace Replace an existing event (otherwise keep the earlier one).
	 */
	public function schedule( $job_id, $when, $replace = true ) {
		$args = array( (int) $job_id );
		$next = wp_next_scheduled( self::HOOK, $args );
		if ( $next ) {
			if ( ! $replace && $next <= $when ) {
				return;
			}
			wp_clear_scheduled_hook( self::HOOK, $args );
		}
		wp_schedule_single_event( max( time(), (int) $when ), self::HOOK, $args );
	}

	/**
	 * Removes the job's cron events.
	 *
	 * @param int $job_id Job ID.
	 */
	public function unschedule( $job_id ) {
		wp_clear_scheduled_hook( self::HOOK, array( (int) $job_id ) );
	}

	/**
	 * Private storage for queued files.
	 *
	 * @return string|WP_Error
	 */
	public static function storage_dir() {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'acme-importer';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'acme_importer_storage', __( 'The upload directory is not writable.', 'acme-importer' ), array( 'status' => 500 ) );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}
}
