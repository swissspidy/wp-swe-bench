<?php
/**
 * WP-CLI: `wp acme-listings …`.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Import and export listings.
 */
class CLI {

	/**
	 * Export the listings matching a search (all pages), in search order.
	 *
	 * ## OPTIONS
	 *
	 * [--min-price=<usd>]
	 * : Minimum price.
	 *
	 * [--max-price=<usd>]
	 * : Maximum price.
	 *
	 * [--beds=<n>]
	 * : Minimum bedrooms.
	 *
	 * [--city=<city>]
	 * : City.
	 *
	 * [--features=<slugs>]
	 * : Comma separated feature slugs (all required).
	 *
	 * [--status=<status>]
	 * : active, for-sale, pending, sold or all.
	 * ---
	 * default: active
	 * ---
	 *
	 * [--sort=<sort>]
	 * : newest, price_asc, price_desc or beds_desc.
	 * ---
	 * default: newest
	 * ---
	 *
	 * [--format=<format>]
	 * : csv or json.
	 * ---
	 * default: csv
	 * options:
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-listings export --city=Springfield --features=pool,garage --sort=price_asc > springfield.csv
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function export( $args, $assoc ) {
		$search = array(
			'status'   => $assoc['status'],
			'sort'     => $assoc['sort'],
			'per_page' => -1,
		);
		$map    = array(
			'min-price' => 'min_price',
			'max-price' => 'max_price',
			'beds'      => 'beds',
			'city'      => 'city',
			'features'  => 'features',
		);
		foreach ( $map as $flag => $key ) {
			if ( isset( $assoc[ $flag ] ) ) {
				$search[ $key ] = $assoc[ $flag ];
			}
		}

		$result = acme_re_search( $search );
		$rows   = array();
		foreach ( $result['ids'] as $id ) {
			$data = acme_re_get_listing( $id );
			if ( $data ) {
				$rows[] = $data;
			}
		}

		if ( 'json' === $assoc['format'] ) {
			WP_CLI::line( (string) wp_json_encode( $rows ) );
			return;
		}

		$out = fopen( 'php://stdout', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'id', 'slug', 'title', 'price', 'bedrooms', 'city', 'features', 'status' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['slug'],
					$row['title'],
					$row['price'],
					null === $row['bedrooms'] ? '' : $row['bedrooms'],
					$row['city'],
					implode( '|', $row['features'] ),
					$row['status'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Rebuild the search index from the listings' meta.
	 *
	 * Only needed if meta was changed behind WordPress' back (e.g. with SQL);
	 * every change made through WordPress updates the index by itself.
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function reindex( $args, $assoc ) {
		$count = Plugin::instance()->index->rebuild(
			static function ( $done ) {
				WP_CLI::log( sprintf( 'Indexed %d listings…', $done ) );
			}
		);
		WP_CLI::success( sprintf( 'Search index rebuilt: %d listings.', $count ) );
	}

	/**
	 * Import listings from the agency CSV.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file.
	 *
	 * @param array $args  Positional args.
	 * @param array $assoc Assoc args.
	 */
	public function import( $args, $assoc ) {
		$stats = ( new Importer() )->import_file( $args[0] );
		if ( is_wp_error( $stats ) ) {
			WP_CLI::error( $stats->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Created %d, updated %d, skipped %d listings.', $stats['created'], $stats['updated'], $stats['skipped'] ) );
	}
}

WP_CLI::add_command( 'acme-listings', CLI::class );
