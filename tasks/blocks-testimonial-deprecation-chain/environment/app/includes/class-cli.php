<?php
/**
 * WP-CLI commands.
 *
 * @package Acme\Testimonials
 */

namespace Acme\Testimonials;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage testimonials.
 */
class CLI {

	/**
	 * Import testimonials from a CSV file into a new post.
	 *
	 * The CSV needs a header row with the columns `quote`, `name` and optionally
	 * `role`, `rating` and `avatar_id`.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--title=<title>]
	 * : Title of the new post.
	 * ---
	 * default: Testimonials
	 * ---
	 *
	 * [--post_type=<post_type>]
	 * : Post type of the new post.
	 * ---
	 * default: page
	 * ---
	 *
	 * [--status=<status>]
	 * : Post status.
	 * ---
	 * default: draft
	 * ---
	 *
	 * [--slug=<slug>]
	 * : Post slug.
	 *
	 * [--porcelain]
	 * : Only output the new post ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-testimonials import reviews.csv --title="What customers say" --status=publish
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function import( $args, $assoc_args ) {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $file ) );
		}

		$rows = $this->read_csv( $file );
		if ( ! $rows ) {
			WP_CLI::error( 'No testimonials found in the file.' );
		}

		$blocks = array();
		foreach ( $rows as $row ) {
			$blocks[] = Markup::testimonial(
				array(
					'quote'     => $row['quote'] ?? '',
					'name'      => $row['name'] ?? '',
					'role'      => $row['role'] ?? '',
					'rating'    => $row['rating'] ?? '',
					'avatar_id' => isset( $row['avatar_id'] ) ? absint( $row['avatar_id'] ) : 0,
				)
			);
		}

		$postarr = array(
			'post_title'   => $assoc_args['title'] ?? 'Testimonials',
			'post_type'    => $assoc_args['post_type'] ?? 'page',
			'post_status'  => $assoc_args['status'] ?? 'draft',
			'post_content' => implode( "\n\n", $blocks ),
		);
		if ( ! empty( $assoc_args['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $assoc_args['slug'] );
		}
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( $post_id );
		}

		if ( ! empty( $assoc_args['porcelain'] ) ) {
			WP_CLI::line( (string) $post_id );
			return;
		}
		WP_CLI::success( sprintf( 'Created post %d with %d testimonials.', $post_id, count( $blocks ) ) );
	}

	/**
	 * List the testimonials of a post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : Post ID.
	 *
	 * [--format=<format>]
	 * : Output format (table, csv, json).
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$post = get_post( (int) $args[0] );
		if ( ! $post ) {
			WP_CLI::error( 'Post not found.' );
		}
		$items = array();
		foreach ( Plugin::instance()->schema->reviews_for_content( $post->post_content ) as $review ) {
			$items[] = array(
				'name'   => $review['author']['name'] ?? '',
				'rating' => $review['reviewRating']['ratingValue'] ?? '',
				'quote'  => wp_trim_words( $review['reviewBody'], 8 ),
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array( 'name', 'rating', 'quote' ) );
	}

	/**
	 * Read a CSV file with a header row into associative arrays.
	 *
	 * @param string $file Path.
	 * @return array[]
	 */
	private function read_csv( $file ) {
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$header = fgetcsv( $handle, 0, ',', '"', '\\' );
		$rows   = array();
		if ( ! $header ) {
			return $rows;
		}
		$header = array_map( 'sanitize_key', $header );
		while ( ( $line = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( array( null ) === $line ) {
				continue;
			}
			$rows[] = array_combine( $header, array_pad( array_slice( $line, 0, count( $header ) ), count( $header ), '' ) );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array_filter(
			$rows,
			static function ( $row ) {
				return '' !== trim( $row['quote'] ?? '' );
			}
		);
	}
}
