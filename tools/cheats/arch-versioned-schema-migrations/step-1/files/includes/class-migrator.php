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
		if ( ! self::pending() ) {
			return;
		}
		self::run();
	}

	/**
	 * Apply all pending migrations.
	 *
	 * @return array{status:string, version:int, applied:int[], error?:string, failed?:int}
	 *         status: 'done' | 'locked' | 'failed'.
	 */
	public static function run() {
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
				$error = self::apply( $migration );
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
			if ( 'done' === $result['status'] ) {
				delete_option( self::FAILURE_OPTION );
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
	 * Run one migration.
	 *
	 * @param array $migration Migration.
	 * @return string|null Error message, or null on success.
	 */
	private static function apply( array $migration ) {
		global $wpdb;
		$wpdb->last_error = '';
		try {
			$outcome = call_user_func( $migration['up'] );
		} catch ( \Throwable $e ) {
			return $e->getMessage() ? $e->getMessage() : get_class( $e );
		}
		if ( is_wp_error( $outcome ) ) {
			return $outcome->get_error_message();
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
	 * @param string $status  'applied' or 'failed'.
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
