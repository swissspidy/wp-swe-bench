<?php
/**
 * 3.0 migration: moves the log out of the (autoloaded) option chunks into the
 * log table, and per-user screen state into user meta.
 *
 * - The log is moved in batches (at most BATCH_SIZE entries per batch; the
 *   background job runs at most BATCHES_PER_RUN batches per request).
 * - Progress (chunk + offset) is saved after every batch. Entries keep their
 *   IDs and IDs that are already in the table are skipped, so a batch that was
 *   interrupted after its rows were written but before the progress was saved
 *   is simply repeated without creating duplicates.
 * - The legacy options are only deleted once everything has been copied.
 *
 * @package Acme\ActivityLog
 */

namespace Acme\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Batched, resumable migration of the legacy storage.
 */
class Migration {

	/**
	 * Progress (not autoloaded).
	 */
	const STATE_OPTION = 'acme_activity_migration';

	/**
	 * Background job hook.
	 */
	const CRON_HOOK = 'acme_activity_migrate';

	/**
	 * Entries per batch.
	 */
	const BATCH_SIZE = 500;

	/**
	 * Batches per background request.
	 */
	const BATCHES_PER_RUN = 2;

	/**
	 * Store.
	 *
	 * @var Log_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * @param Log_Store $store Store.
	 */
	public function __construct( Log_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( self::CRON_HOOK, array( $this, 'run_background' ) );
	}

	/**
	 * Current progress.
	 *
	 * @return array{chunks: string[], chunk: int, offset: int, migrated: int, done: bool}|null
	 */
	public static function state() {
		$state = get_option( self::STATE_OPTION );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Has everything been migrated?
	 *
	 * @return bool
	 */
	public static function is_done() {
		$state = self::state();
		return $state && ! empty( $state['done'] );
	}

	/**
	 * Save progress.
	 *
	 * @param array $state Progress.
	 */
	private static function save_state( array $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * Legacy log chunk options that exist, oldest first.
	 *
	 * @return string[]
	 */
	public static function legacy_chunk_names() {
		global $wpdb;
		$like  = $wpdb->esc_like( Log_Store::ARCHIVE_PREFIX ) . '%';
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$names = array_values(
			array_filter(
				$names,
				static function ( $name ) {
					return (bool) preg_match( '/^' . preg_quote( Log_Store::ARCHIVE_PREFIX, '/' ) . '\d+$/', $name );
				}
			)
		);
		usort(
			$names,
			static function ( $a, $b ) {
				return (int) substr( $a, strlen( Log_Store::ARCHIVE_PREFIX ) ) <=> (int) substr( $b, strlen( Log_Store::ARCHIVE_PREFIX ) );
			}
		);

		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name = %s", Log_Store::OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $current ) {
			$names[] = Log_Store::OPTION;
		}
		return $names;
	}

	/**
	 * Legacy per-user state options (1.x), user ID => option name.
	 *
	 * @return array<int, string>
	 */
	private static function legacy_user_state_options() {
		global $wpdb;
		$like  = $wpdb->esc_like( User_State::LEGACY_PREFIX ) . '%';
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out   = array();
		foreach ( $names as $name ) {
			if ( preg_match( '/^' . preg_quote( User_State::LEGACY_PREFIX, '/' ) . '(\d+)$/', $name, $m ) ) {
				$out[ (int) $m[1] ] = $name;
			}
		}
		return $out;
	}

	/**
	 * Start the migration (idempotent). Runs in the request that performs the
	 * plugin upgrade, so it only does cheap work:
	 *
	 * - stops autoloading the legacy options,
	 * - moves per-user screen state to user meta (a handful of rows),
	 * - schedules the background job.
	 */
	public function start() {
		if ( self::state() ) {
			if ( ! self::is_done() ) {
				self::schedule();
			}
			return;
		}

		$chunks = self::legacy_chunk_names();
		$users  = self::legacy_user_state_options();

		$names = $chunks;
		foreach ( $users as $name ) {
			$names[] = $name;
		}
		if ( $names && function_exists( 'wp_set_option_autoload_values' ) ) {
			wp_set_option_autoload_values( array_fill_keys( $names, false ) );
		}

		$this->migrate_user_state( $users );

		$state = array(
			'chunks'   => $chunks,
			'chunk'    => 0,
			'offset'   => 0,
			'migrated' => 0,
			'done'     => false,
		);
		if ( ! $chunks ) {
			$state['done'] = true;
			$this->finish( $state );
			return;
		}
		self::save_state( $state );
		self::schedule();
	}

	/**
	 * Move screen state (2.x option and 1.x per-user options) to user meta.
	 * State of users that no longer exist is dropped.
	 *
	 * @param array<int, string> $legacy_options 1.x options by user ID.
	 */
	private function migrate_user_state( array $legacy_options ) {
		$all = get_option( User_State::OPTION );
		$all = is_array( $all ) ? $all : array();

		foreach ( $all as $user_id => $state ) {
			if ( is_array( $state ) && get_userdata( (int) $user_id ) && '' === get_user_meta( (int) $user_id, User_State::META_KEY, true ) ) {
				update_user_meta( (int) $user_id, User_State::META_KEY, User_State::sanitize( $state ) );
			}
		}

		foreach ( $legacy_options as $user_id => $name ) {
			$legacy = get_option( $name );
			if ( ! isset( $all[ $user_id ] ) && is_array( $legacy ) && get_userdata( $user_id ) && '' === get_user_meta( $user_id, User_State::META_KEY, true ) ) {
				update_user_meta( $user_id, User_State::META_KEY, User_State::sanitize( User_State::from_legacy( $legacy ) ) );
			}
		}

		foreach ( $legacy_options as $name ) {
			delete_option( $name );
		}
		delete_option( User_State::OPTION );
	}

	/**
	 * Schedule the background job.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}

	/**
	 * Background job: a few batches, then reschedule.
	 */
	public function run_background() {
		for ( $i = 0; $i < self::BATCHES_PER_RUN; $i++ ) {
			if ( ! $this->run_batch() ) {
				return;
			}
		}
		if ( ! self::is_done() ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/**
	 * Migrate one batch.
	 *
	 * @param int $size Batch size.
	 * @return bool Whether there is more work.
	 */
	public function run_batch( $size = self::BATCH_SIZE ) {
		$state = self::state();
		if ( ! $state ) {
			$this->start();
			$state = self::state();
		}
		if ( ! $state || ! empty( $state['done'] ) ) {
			return false;
		}

		$size   = max( 1, min( 1000, (int) $size ) );
		$chunks = (array) $state['chunks'];
		$index  = (int) $state['chunk'];

		if ( $index >= count( $chunks ) ) {
			$this->finish( $state );
			return false;
		}

		$chunk = get_option( $chunks[ $index ], array() );
		$chunk = is_array( $chunk ) ? array_values( $chunk ) : array();
		$slice = array_slice( $chunk, (int) $state['offset'], $size );

		if ( $slice ) {
			$state['migrated'] += $this->store->import( array_map( array( Log_Store::class, 'normalize' ), $slice ) );
		}

		$state['offset'] = (int) $state['offset'] + count( $slice );
		if ( $state['offset'] >= count( $chunk ) ) {
			++$state['chunk'];
			$state['offset'] = 0;
		}

		if ( $state['chunk'] >= count( $chunks ) ) {
			$this->finish( $state );
			return false;
		}

		self::save_state( $state );
		return true;
	}

	/**
	 * Everything is copied: remove the legacy options.
	 *
	 * @param array $state Progress.
	 */
	private function finish( array $state ) {
		foreach ( self::legacy_chunk_names() as $name ) {
			delete_option( $name );
		}
		delete_option( Log_Store::ARCHIVE_COUNT_OPTION );
		delete_option( Log_Store::LAST_ID_OPTION );

		$state['done'] = true;
		self::save_state( $state );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
