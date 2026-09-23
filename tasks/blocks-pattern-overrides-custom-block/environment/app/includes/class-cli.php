<?php
/**
 * WP-CLI commands.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Acme CTAs.
 */
class CLI {

	/**
	 * Inventory.
	 *
	 * @var Inventory
	 */
	private $inventory;

	/**
	 * Constructor.
	 *
	 * @param Inventory $inventory Inventory.
	 */
	public function __construct( Inventory $inventory ) {
		$this->inventory = $inventory;
	}

	/**
	 * List all CTAs on the site.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<post-type>]
	 * : Only list CTAs in this post type.
	 *
	 * [--format=<format>]
	 * : table, json, csv or count.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-cta list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public function list_( $args, $assoc_args ) {
		$this->inventory->flush();
		$rows = $this->inventory->rows();
		if ( ! empty( $assoc_args['post_type'] ) ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $assoc_args ) {
						return $row['post_type'] === $assoc_args['post_type'];
					}
				)
			);
		}
		\WP_CLI\Utils\format_items(
			$assoc_args['format'] ?? 'table',
			$rows,
			array( 'post_id', 'post_type', 'post_title', 'heading', 'button_text', 'url', 'variant', 'campaign' )
		);
	}
}
