<?php
/**
 * Migration runner.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Applies pending migrations on the first request after a deploy (and on activation),
 * one runner at a time, logging every result and stopping at the first failure.
 */
class Migrator {

	const VERSION_OPTION = 'acme_crm_db_version';
	const LOCK_OPTION    = 'acme_crm_migration_lock';
	const LOG_OPTION     = 'acme_crm_migration_log';
	const FAILURE_OPTION = 'acme_crm_migration_failure';
	const RETRY_ACTION   = 'acme_crm_retry_migrations';
	const PAUSE_OPTION   = 'acme_crm_migrations_paused';

	/**
	 * Returned by a migration that did part of its work (one batch) and must be called again.
	 */
	const INCOMPLETE = 'acme_crm_migration_incomplete';

	/**
	 * A lock older than this (seconds) is considered abandoned.
	 */
	const LOCK_TTL = 600;

	/**
	 * Number of log entries kept.
	 */
	const LOG_LIMIT = 100;

	/**
	 * Hook up automatic runs, the admin notice and the retry action.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'maybe_run' ), 1 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_post_' . self::RETRY_ACTION, array( __CLASS__, 'handle_retry' ) );
	}

	/**
	 * The schema version of this site. Sites from before the migration system (no option)
	 * are at version 1 if their tables exist.
	 *
	 * @return int
	 */
	public static function current_version() {
		$version = get_option( self::VERSION_OPTION, false );
		if ( false !== $version && '' !== $version ) {
			return (int) $version;
		}
		return Schema::table_exists( Installer::table( 'contacts' ) ) ? 1 : 0;
	}

	/**
	 * Migrations newer than the current version.
	 *
	 * @return array<int,array>
	 */
	public static function pending() {
		$current = self::current_version();
		return array_filter(
			Migrations::all(),
			static function ( $version ) use ( $current ) {
				return $version > $current;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Automatic run (every request). Cheap when nothing is pending; does nothing after a
	 * failure until an administrator retries.
	 */
	public static function maybe_run() {
		if ( get_option( self::FAILURE_OPTION ) ) {
			return;
		}
		if ( ! self::pending() ) {
			return;
		}
		self::run();
	}

	/**
	 * Apply pending migrations.
	 *
	 * @param bool $to_completion Keep calling batched migrations until they finish (CLI).
	 *                            Web requests do one batch per request.
	 * @return array{status:string, version:int, applied:int[], error?:string, failed?:int}
	 *         status: 'done' | 'incomplete' | 'locked' | 'failed'.
	 */
	public static function run( $to_completion = false ) {
		if ( ! self::acquire_lock() ) {
			return array(
				'status'  => 'locked',
				'version' => self::current_version(),
				'applied' => array(),
			);
		}

		$applied = array();
		$result  = array( 'status' => 'done' );
		try {
			if ( false === get_option( self::VERSION_OPTION, false ) && 1 === self::current_version() ) {
				// Pre-migration install: record the baseline.
				update_option( self::VERSION_OPTION, 1, true );
			}
			foreach ( self::pending() as $version => $migration ) {
				do {
					$error = self::apply( $migration['up'] );
					Contacts::flush_schema_cache();
				} while ( self::INCOMPLETE === $error && $to_completion );
				if ( self::INCOMPLETE === $error ) {
					$result = array( 'status' => 'incomplete' );
					break;
				}
				if ( null !== $error ) {
					self::fail( $version, $migration, $error );
					$result = array(
						'status' => 'failed',
						'failed' => $version,
						'error'  => $error,
					);
					break;
				}
				update_option( self::VERSION_OPTION, $version, true );
				self::log( $version, 'applied' );
				$applied[] = $version;
			}
			if ( 'failed' !== $result['status'] ) {
				delete_option( self::FAILURE_OPTION );
				delete_option( self::PAUSE_OPTION );
			}
			update_option( Installer::VERSION_OPTION, VERSION );
		} finally {
			self::release_lock();
		}

		$result['version'] = self::current_version();
		$result['applied'] = $applied;
		return $result;
	}

	/**
	 * Run one migration step (up or down).
	 *
	 * @param callable $callback Migration callable.
	 * @return string|null Error message, self::INCOMPLETE, or null on success.
	 */
	private static function apply( $callback ) {
		global $wpdb;
		$wpdb->last_error = '';
		try {
			$outcome = call_user_func( $callback );
		} catch ( \Throwable $e ) {
			return $e->getMessage() ? $e->getMessage() : get_class( $e );
		}
		if ( is_wp_error( $outcome ) ) {
			return $outcome->get_error_message();
		}
		if ( self::INCOMPLETE === $outcome ) {
			return '' === (string) $wpdb->last_error ? self::INCOMPLETE : sprintf( /* translators: %s: database error */ __( 'Database error: %s', 'acme-crm' ), trim( wp_strip_all_tags( $wpdb->last_error ) ) );
		}
		if ( false === $outcome ) {
			return __( 'The migration reported a failure.', 'acme-crm' );
		}
		if ( '' !== (string) $wpdb->last_error ) {
			/* translators: %s: database error */
			return sprintf( __( 'Database error: %s', 'acme-crm' ), trim( wp_strip_all_tags( $wpdb->last_error ) ) );
		}
		return null;
	}

	/**
	 * Roll back the most recently applied migration and pause automatic runs (so the next
	 * web request doesn't re-apply it) until migrations are run again explicitly.
	 *
	 * @param bool $dry_run Only report what would happen.
	 * @return array{status:string, version:int, rolled_back?:int, description?:string, error?:string}
	 *         status: 'rolled_back' | 'dry_run' | 'locked' | 'irreversible' | 'failed' | 'nothing'.
	 */
	public static function rollback( $dry_run = false ) {
		$all     = Migrations::all();
		$current = self::current_version();
		$result  = array( 'version' => $current );
		if ( $current < 1 || ! isset( $all[ $current ] ) ) {
			return $result + array( 'status' => 'nothing' );
		}
		$migration             = $all[ $current ];
		$result['rolled_back'] = $current;
		$result['description'] = $migration['description'];
		if ( empty( $migration['down'] ) ) {
			return $result + array( 'status' => 'irreversible' );
		}
		if ( $dry_run ) {
			return $result + array( 'status' => 'dry_run' );
		}
		if ( ! self::acquire_lock() ) {
			return $result + array( 'status' => 'locked' );
		}
		try {
			$error = self::apply( $migration['down'] );
			Contacts::flush_schema_cache();
			if ( null !== $error ) {
				self::log( $current, 'failed', $error );
				return $result + array(
					'status' => 'failed',
					'error'  => $error,
				);
			}
			$previous = 0;
			foreach ( array_keys( $all ) as $version ) {
				if ( $version < $current ) {
					$previous = $version;
				}
			}
			update_option( self::VERSION_OPTION, $previous, true );
			update_option( self::PAUSE_OPTION, time(), true );
			self::log( $current, 'rolled_back' );
		} finally {
			self::release_lock();
		}
		return array(
			'status'      => 'rolled_back',
			'version'     => self::current_version(),
			'rolled_back' => $current,
			'description' => $migration['description'],
		);
	}

	/**
	 * Status of every known migration: 'applied', 'failed' (last attempt failed) or 'pending'.
	 *
	 * @return array<int,array{version:int,description:string,status:string}>
	 */
	public static function status() {
		$current = self::current_version();
		$last    = array();
		$log     = get_option( self::LOG_OPTION, array() );
		foreach ( is_array( $log ) ? $log : array() as $entry ) {
			if ( isset( $entry['version'], $entry['status'] ) ) {
				$last[ (int) $entry['version'] ] = $entry['status'];
			}
		}
		$rows = array();
		foreach ( Migrations::all() as $version => $migration ) {
			if ( $version <= $current ) {
				$status = 'applied';
			} elseif ( isset( $last[ $version ] ) && 'failed' === $last[ $version ] ) {
				$status = 'failed';
			} else {
				$status = 'pending';
			}
			$rows[] = array(
				'version'     => $version,
				'description' => $migration['description'],
				'status'      => $status,
			);
		}
		return $rows;
	}

	/**
	 * Whether automatic runs are paused after a rollback.
	 *
	 * @return bool
	 */
	public static function is_paused() {
		return (bool) get_option( self::PAUSE_OPTION );
	}

	/**
	 * Age of a lock held by another run, in seconds (null when unlocked or stale).
	 *
	 * @return int|null
	 */
	public static function active_lock_age() {
		global $wpdb;
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) );
		if ( ! $since || $since <= time() - self::LOCK_TTL ) {
			return null;
		}
		return max( 0, time() - $since );
	}

	/**
	 * Record a failure: log it and pause automatic runs.
	 *
	 * @param int    $version   Version.
	 * @param array  $migration Migration.
	 * @param string $error     Message.
	 */
	private static function fail( $version, array $migration, $error ) {
		self::log( $version, 'failed', $error );
		update_option(
			self::FAILURE_OPTION,
			array(
				'version'     => (int) $version,
				'description' => $migration['description'],
				'message'     => $error,
				'time'        => time(),
			),
			true
		);
	}

	/**
	 * Append to the migration log.
	 *
	 * @param int    $version Version.
	 * @param string $status  'applied', 'failed' or 'rolled_back'.
	 * @param string $message Message.
	 */
	public static function log( $version, $status, $message = '' ) {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'version' => (int) $version,
			'status'  => $status,
			'time'    => time(),
			'message' => (string) $message,
		);
		update_option( self::LOG_OPTION, array_slice( $log, -self::LOG_LIMIT ), false );
	}

	/**
	 * Take the lock atomically (a plain INSERT: the option name is unique).
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		global $wpdb;
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$suppress = $wpdb->suppress_errors( true );
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
					self::LOCK_OPTION,
					(string) time(),
					'off'
				)
			);
			$wpdb->suppress_errors( $suppress );
			$wpdb->last_error = '';
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( self::LOCK_OPTION, 'options' );
			if ( $inserted ) {
				return true;
			}
			$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) );
			if ( $since && $since > time() - self::LOCK_TTL ) {
				return false;
			}
			// Stale (or unreadable) lock: take it over.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK_OPTION, (string) $since ) );
		}
		return false;
	}

	/**
	 * Release the lock.
	 */
	private static function release_lock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Error notice for administrators after a failed migration.
	 */
	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$failure = get_option( self::FAILURE_OPTION );
		if ( ! is_array( $failure ) ) {
			return;
		}
		?>
		<div class="notice notice-error acme-crm-migration-failed">
			<p>
				<strong><?php esc_html_e( 'Acme CRM database update failed.', 'acme-crm' ); ?></strong>
				<?php
				/* translators: 1: migration version, 2: description, 3: error message */
				echo esc_html( sprintf( __( 'Migration %1$d (%2$s) failed: %3$s', 'acme-crm' ), $failure['version'], $failure['description'], $failure['message'] ) );
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RETRY_ACTION ); ?>" />
				<?php wp_nonce_field( self::RETRY_ACTION ); ?>
				<p><button type="submit" class="button"><?php esc_html_e( 'Retry now', 'acme-crm' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Retry after a failure.
	 */
	public static function handle_retry() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run database updates.', 'acme-crm' ), 403 );
		}
		check_admin_referer( self::RETRY_ACTION );
		delete_option( self::FAILURE_OPTION );
		$result = self::run();
		$back   = wp_get_referer() ? wp_get_referer() : admin_url();
		wp_safe_redirect( add_query_arg( 'acme_crm_migrations', $result['status'], $back ) );
		exit;
	}
}
