<?php
/**
 * WP-CLI: `wp acme-loyalty …`.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Loyalty maintenance commands.
 */
class CLI {

	/**
	 * Show a member's balance.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, login or email.
	 *
	 * @param array $args Positional args.
	 */
	public function balance( $args ) {
		$user = get_user_by( is_numeric( $args[0] ) ? 'id' : ( is_email( $args[0] ) ? 'email' : 'login' ), $args[0] );
		if ( ! $user ) {
			\WP_CLI::error( 'User not found.' );
		}
		\WP_CLI::line( (string) Ledger::balance( $user->ID ) );
	}

	/**
	 * Add points (manual adjustment).
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID.
	 *
	 * <points>
	 * : Points (negative to deduct).
	 *
	 * [--note=<note>]
	 * : Note for the ledger.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Flags.
	 */
	public function adjust( $args, $assoc_args ) {
		$id = Ledger::add( (int) $args[0], (int) $args[1], 'manual', array( 'note' => $assoc_args['note'] ?? '' ) );
		if ( ! $id ) {
			\WP_CLI::error( 'Could not add the ledger row.' );
		}
		\WP_CLI::success( "Ledger row {$id} added." );
	}

	/**
	 * Import orders from the POS CSV export (number,status,email,total,date,note).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file.
	 *
	 * @param array $args Positional args.
	 */
	public function import_orders( $args ) {
		$fh = fopen( $args[0], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fh ) {
			\WP_CLI::error( 'Cannot read file.' );
		}
		$count = 0;
		fgetcsv( $fh ); // Header.
		while ( ( $row = fgetcsv( $fh ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			list( $number, $status, $email, $total, $date, $note ) = array_pad( $row, 6, '' );
			$user = get_user_by( 'email', $email );
			Orders::create(
				array(
					'number'      => $number,
					'status'      => $status,
					'customer_id' => $user ? $user->ID : 0,
					'email'       => $email,
					'total'       => $total,
					'date'        => $date,
					'note'        => $note,
				)
			);
			++$count;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		\WP_CLI::success( "Imported {$count} orders." );
	}
}

\WP_CLI::add_command( 'acme-loyalty', CLI::class );
