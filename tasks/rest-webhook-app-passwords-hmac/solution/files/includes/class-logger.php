<?php
/**
 * Sync log.
 *
 * One JSON object per line in wp-content/uploads/acme-orders-sync/sync.log:
 *
 *     {"time":"2026-09-01T10:00:00Z","level":"info","message":"…","context":{…}}
 *
 * Shown under Tools → Order Sync Log. Support asks customers to send us this
 * file when a delivery goes missing, so everything is redacted before it is
 * written (see Redactor).
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * File logger.
 */
class Logger {

	const LEVELS = array(
		'debug' => 0,
		'info'  => 1,
		'error' => 2,
	);

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Redactor for the current secrets.
	 */
	public function redactor(): Redactor {
		return new Redactor( $this->settings->secrets() );
	}

	/**
	 * Directory of the log file.
	 */
	public static function dir(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . 'acme-orders-sync';
	}

	/**
	 * Full path of the log file.
	 */
	public static function file(): string {
		return self::dir() . '/sync.log';
	}

	/**
	 * Debug message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Info message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Error message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Writes an entry.
	 *
	 * @param string $level   debug|info|error.
	 * @param string $message Message.
	 * @param array  $context Context (anything JSON-serializable).
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$min = self::LEVELS[ $this->settings->get( 'log_level', 'info' ) ] ?? 1;
		if ( ( self::LEVELS[ $level ] ?? 1 ) < $min ) {
			return;
		}

		$redactor = $this->redactor();
		$entry    = array(
			'time'    => now_iso(),
			'level'   => $level,
			'message' => $redactor->scrub( $message ),
			'context' => $redactor->redact( $context ),
		);

		$line = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

		if ( ! is_dir( self::dir() ) ) {
			wp_mkdir_p( self::dir() );
			// Keep the log out of reach of browsers on Apache.
			file_put_contents( self::dir() . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( self::dir() . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		file_put_contents( self::file(), $line, FILE_APPEND | LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && 'error' === $level ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Acme Orders Sync: ' . $entry['message'] . ' ' . wp_json_encode( $entry['context'] ) );
		}

		/**
		 * Fires after a log entry was written (used by the monitoring glue code).
		 *
		 * @param array $entry The entry.
		 */
		do_action( 'acme_orders_logged', $entry );
	}

	/**
	 * Last N entries, newest first.
	 *
	 * @param int $limit Max entries.
	 * @return array<int, array>
	 */
	public function tail( int $limit = 200 ): array {
		if ( ! is_readable( self::file() ) ) {
			return array();
		}
		$lines   = file( self::file(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$lines   = array_slice( (array) $lines, -1 * $limit );
		$entries = array();
		foreach ( array_reverse( $lines ) as $line ) {
			$entry = json_decode( $line, true );
			if ( is_array( $entry ) ) {
				$entries[] = $entry;
			}
		}
		return $entries;
	}

	/**
	 * Redacts an existing log file in place (entries written before 1.4.0).
	 */
	public function scrub_file(): void {
		$file = self::file();
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return;
		}
		$redactor = $this->redactor();
		$lines    = (array) file( $file, FILE_IGNORE_NEW_LINES );
		$out      = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( (string) $line ) ) {
				continue;
			}
			$entry = json_decode( (string) $line, true );
			if ( is_array( $entry ) ) {
				$out[] = wp_json_encode( $redactor->redact( $entry ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			} else {
				$out[] = $redactor->scrub( (string) $line );
			}
		}
		$tmp = $file . '.tmp';
		file_put_contents( $tmp, $out ? implode( "\n", $out ) . "\n" : '', LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		rename( $tmp, $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
	}

	/**
	 * Empties the log.
	 */
	public function clear(): void {
		if ( is_file( self::file() ) ) {
			file_put_contents( self::file(), '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
