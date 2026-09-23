<?php
/**
 * Background processing of imports.
 *
 * @package Acme\Importer
 */

namespace Acme\Importer;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Queues uploaded price lists and imports them in batches with WP-Cron.
 *
 * Flow:
 * - enqueue() checks the file, copies it to private storage and schedules the first batch.
 * - process() (cron) claims the job, imports up to `batch_size` rows, persisting
 *   the counters and the read position after every row, then schedules the next batch.
 * - Before a batch starts, a watchdog event is scheduled for the moment the claim
 *   expires: if the runner dies, the watchdog picks the job up again.
 */
class Queue {

	const HOOK = 'acme_importer_process';

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
	 * Constructor.
	 *
	 * @param Job_Store $jobs     Jobs.
	 * @param Importer  $importer Importer.
	 */
	public function __construct( Job_Store $jobs, Importer $importer ) {
		$this->jobs     = $jobs;
		$this->importer = $importer;
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
	 * Checks an uploaded file and queues it.
	 *
	 * @param string $tmp_path  Path of the uploaded file.
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
		$job_id = (int) $job_id;
		$job    = $this->jobs->get( $job_id );
		if ( ! $job || ! in_array( $job->status, array( Job_Store::QUEUED, Job_Store::RUNNING ), true ) ) {
			return;
		}

		$timeout = self::lock_timeout();
		$token   = wp_generate_password( 32, false );
		if ( ! $this->jobs->claim( $job_id, $token, $timeout ) ) {
			// Another runner is busy with it. Make sure the job is picked up again
			// if that runner dies (its claim expires).
			$this->schedule( $job_id, (int) $job->locked_at + $timeout + 1, false );
			return;
		}

		try {
			$this->run_batch( $job_id, $token, $timeout );
		} finally {
			$this->jobs->release( $job_id, $token );
		}
	}

	/**
	 * Processes a batch while holding the claim.
	 *
	 * @param int    $job_id  Job ID.
	 * @param string $token   Claim token.
	 * @param int    $timeout Claim timeout.
	 */
	private function run_batch( $job_id, $token, $timeout ) {
		// Watchdog: if this process dies, continue once the claim expired.
		$this->schedule( $job_id, time() + $timeout + 1 );

		$this->jobs->start( $job_id );
		$job = $this->jobs->get( $job_id );

		$reader = new Csv_Reader( (string) $job->file_path );
		if ( is_wp_error( $reader->open() ) ) {
			$this->jobs->finish( $job_id, Job_Store::FAILED );
			$this->unschedule( $job_id );
			return;
		}
		$reader->seek( (int) $job->file_offset, (int) $job->last_row );

		wp_defer_term_counting( true );
		$batch_size = batch_size();
		$done       = 0;
		$more       = false;
		$stopped    = false;

		foreach ( $reader->rows() as $row_number => $row ) {
			if ( Job_Store::CANCELLED === $this->jobs->status( $job_id ) ) {
				$stopped = true;
				break;
			}
			if ( $done >= $batch_size ) {
				$more = true;
				break;
			}

			$outcome = $this->importer->import_row( $row, $row_number );
			++$done;
			if ( ! $this->jobs->record_row( $job_id, $outcome['result'], $reader->tell(), $row_number, $token ) ) {
				// Lost the claim (we were too slow): the new holder continues.
				$stopped = true;
				break;
			}
		}
		if ( ! $more && ! $stopped ) {
			// Trailing blank lines.
			$this->jobs->advance( $job_id, $reader->tell(), $reader->row_number(), $token );
		}
		$reader->close();
		wp_defer_term_counting( false );

		if ( $stopped ) {
			if ( Job_Store::CANCELLED === $this->jobs->status( $job_id ) ) {
				$this->unschedule( $job_id );
			}
			return;
		}

		if ( $more ) {
			$this->schedule( $job_id, time() );
			return;
		}

		$this->complete( $job_id );
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
