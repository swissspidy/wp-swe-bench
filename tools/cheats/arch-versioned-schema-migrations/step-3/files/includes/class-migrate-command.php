<?php
/**
 * `wp acme-crm migrate …`
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Inspect, run and roll back database migrations.
 *
 * ## EXAMPLES
 *
 *     wp acme-crm migrate status
 *     wp acme-crm migrate run --dry-run
 *     wp acme-crm migrate run
 *     wp acme-crm migrate rollback
 */
class Migrate_Command {

	/**
	 * Show every migration and whether it is applied.
	 *
	 * Exits with 0 when the database is up to date and 2 when migrations are pending.
	 *
	 * ## OPTIONS
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
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$rows   = Migrator::status();
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items( $format, $rows, array( 'version', 'description', 'status' ) );

		$pending = count(
			array_filter(
				$rows,
				static function ( $row ) {
					return 'applied' !== $row['status'];
				}
			)
		);
		if ( 'table' === $format ) {
			/* translators: 1: current version, 2: latest version */
			\WP_CLI::log( sprintf( __( 'Database version: %1$d (latest: %2$d)', 'acme-crm' ), Migrator::current_version(), Migrations::latest() ) );
			if ( Migrator::is_paused() ) {
				\WP_CLI::log( __( 'Automatic migrations are paused.', 'acme-crm' ) );
			}
		}
		\WP_CLI::halt( $pending ? 2 : 0 );
	}

	/**
	 * Run all pending migrations to completion.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : List the pending migrations without running them.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function run( $args, $assoc_args ) {
		$pending = Migrator::pending();

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ) {
			foreach ( $pending as $version => $migration ) {
				/* translators: 1: version, 2: description */
				\WP_CLI::log( sprintf( __( 'Would apply migration %1$d: %2$s', 'acme-crm' ), $version, $migration['description'] ) );
			}
			/* translators: %d: number of migrations */
			\WP_CLI::success( sprintf( __( 'Dry run: %d migration(s) pending.', 'acme-crm' ), count( $pending ) ) );
			return;
		}

		if ( ! $pending ) {
			// Nothing to do, but an explicit run also lifts a pause or a recorded failure.
			delete_option( Migrator::FAILURE_OPTION );
			delete_option( Migrator::PAUSE_OPTION );
			/* translators: %d: version */
			\WP_CLI::success( sprintf( __( 'Database is already at version %d.', 'acme-crm' ), Migrator::current_version() ) );
			return;
		}

		$age = Migrator::active_lock_age();
		if ( null !== $age ) {
			/* translators: %d: seconds */
			\WP_CLI::error( sprintf( __( 'Another migration run is in progress (started %d seconds ago).', 'acme-crm' ), $age ) );
		}

		delete_option( Migrator::FAILURE_OPTION );
		$result = Migrator::run( true );
		$all    = Migrations::all();
		foreach ( $result['applied'] as $version ) {
			/* translators: 1: version, 2: description */
			\WP_CLI::log( sprintf( __( 'Applied migration %1$d: %2$s', 'acme-crm' ), $version, $all[ $version ]['description'] ) );
		}
		if ( 'locked' === $result['status'] ) {
			\WP_CLI::error( __( 'Another migration run is in progress.', 'acme-crm' ) );
		}
		if ( 'failed' === $result['status'] ) {
			/* translators: 1: version, 2: error message */
			\WP_CLI::error( sprintf( __( 'Migration %1$d failed: %2$s', 'acme-crm' ), $result['failed'], $result['error'] ) );
		}
		/* translators: %d: version */
		\WP_CLI::success( sprintf( __( 'Database is at version %d.', 'acme-crm' ), $result['version'] ) );
	}

	/**
	 * Roll back the most recently applied migration.
	 *
	 * Automatic migrations on web requests are paused afterwards until `wp acme-crm migrate run`.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show which migration would be rolled back.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function rollback( $args, $assoc_args ) {
		$dry = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		if ( ! $dry && null !== Migrator::active_lock_age() ) {
			\WP_CLI::error( __( 'Another migration run is in progress.', 'acme-crm' ) );
		}
		$result = Migrator::rollback( $dry );
		switch ( $result['status'] ) {
			case 'nothing':
				\WP_CLI::error( __( 'No migration has been applied.', 'acme-crm' ) );
				break;
			case 'irreversible':
				/* translators: %d: version */
				\WP_CLI::error( sprintf( __( 'Migration %d cannot be rolled back.', 'acme-crm' ), $result['rolled_back'] ) );
				break;
			case 'locked':
				\WP_CLI::error( __( 'Another migration run is in progress.', 'acme-crm' ) );
				break;
			case 'failed':
				/* translators: 1: version, 2: error message */
				\WP_CLI::error( sprintf( __( 'Rolling back migration %1$d failed: %2$s', 'acme-crm' ), $result['rolled_back'], $result['error'] ) );
				break;
			case 'dry_run':
				/* translators: 1: version, 2: description */
				\WP_CLI::success( sprintf( __( 'Dry run: would roll back migration %1$d (%2$s).', 'acme-crm' ), $result['rolled_back'], $result['description'] ) );
				break;
			default:
				/* translators: 1: rolled back version, 2: new version */
				\WP_CLI::success( sprintf( __( 'Rolled back migration %1$d; database is at version %2$d.', 'acme-crm' ), $result['rolled_back'], $result['version'] ) );
		}
	}
}
