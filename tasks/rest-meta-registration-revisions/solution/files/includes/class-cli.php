<?php
/**
 * WP-CLI commands.
 *
 * @package Acme\Specs
 */

namespace Acme\Specs;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Acme Specs data.
 */
class CLI {

	/**
	 * Convert products that still have 1.x specs to the structured format.
	 *
	 * Safe to run repeatedly (e.g. after importing old products).
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-specs migrate
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Assoc args.
	 */
	public function migrate( $args, $assoc_args ) {
		$count = Migration::migrate_all();
		/* translators: %d: number of products */
		WP_CLI::success( sprintf( _n( 'Migrated %d product.', 'Migrated %d products.', $count, 'acme-specs' ), $count ) );
	}
}
