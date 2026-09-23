<?php
/**
 * WP-CLI: `wp acme-orders …`.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage the Acme Shop order sync.
 */
class CLI_Command {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Processor.
	 *
	 * @var Order_Processor
	 */
	private $processor;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings  Settings.
	 * @param Order_Processor $processor Processor.
	 */
	public function __construct( Settings $settings, Order_Processor $processor ) {
		$this->settings  = $settings;
		$this->processor = $processor;
	}

	/**
	 * Applies an event from a JSON file (e.g. exported from the shop's webhook log) right away.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the event JSON.
	 *
	 * [--source=<source>]
	 * : Source (storefront) ID.
	 * ---
	 * default: default
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-orders replay evt_01J8.json --source=shop-eu
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public function replay( array $args, array $assoc_args ): void {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read $file." );
		}
		$source = (string) ( $assoc_args['source'] ?? 'default' );
		if ( null === $this->settings->source( $source ) ) {
			WP_CLI::error( "Unknown source $source." );
		}
		$event  = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$result = $this->processor->process( is_array( $event ) ? $event : array(), $source );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( "Event applied to order post $result." );
	}

	/**
	 * Lists the configured sources.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table|json|csv
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand sources
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public function sources( array $args, array $assoc_args ): void {
		$rows = array();
		foreach ( $this->settings->sources() as $id => $source ) {
			$rows[] = array(
				'id'     => $id,
				'label'  => $source['label'],
				'secret' => '' === $source['secret'] ? '' : substr( $source['secret'], 0, 4 ) . '…',
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'label', 'secret' ) );
	}
}
