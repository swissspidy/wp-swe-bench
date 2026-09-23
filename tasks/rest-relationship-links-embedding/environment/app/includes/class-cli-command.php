<?php
/**
 * WP-CLI: `wp acme-library …`.
 *
 * @package Acme\Library
 */

namespace Acme\Library;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Catalogue tools.
 */
class CLI_Command {

	/**
	 * Imports books from the old catalogue export (CSV: title,isbn,year,author names separated by "|").
	 *
	 * Unknown authors are created as drafts for the editors to review.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file.
	 *
	 * [--status=<status>]
	 * : Status of the imported books.
	 * ---
	 * default: draft
	 * ---
	 *
	 * @param array $args       Args.
	 * @param array $assoc_args Assoc args.
	 */
	public function import( array $args, array $assoc_args ): void {
		$handle = fopen( $args[0], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			WP_CLI::error( "Cannot read {$args[0]}." );
		}
		$count = 0;
		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( count( $row ) < 4 || 'title' === $row[0] ) {
				continue;
			}
			list( $title, $isbn, $year, $names ) = $row;
			$author_ids = array();
			foreach ( array_filter( array_map( 'trim', explode( '|', $names ) ) ) as $name ) {
				$author_ids[] = $this->find_or_create_author( $name );
			}
			$book_id = wp_insert_post(
				array(
					'post_type'   => Post_Types::BOOK,
					'post_title'  => $title,
					'post_status' => $assoc_args['status'] ?? 'draft',
					'meta_input'  => array(
						'acme_isbn' => Post_Types::sanitize_isbn( $isbn ),
						'acme_year' => (int) $year,
						// @todo Write through Relationships::set_author_ids() (ACME-88). The importer
						// still uses the 1.x format, which the repository reads as a fallback.
						Relationships::LEGACY_META => implode( ',', $author_ids ),
					),
				),
				true
			);
			if ( is_wp_error( $book_id ) ) {
				WP_CLI::warning( $book_id->get_error_message() );
				continue;
			}
			++$count;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		WP_CLI::success( "Imported $count books." );
	}

	/**
	 * Finds an author post by title, creating a draft if needed.
	 *
	 * @param string $name Author name.
	 */
	private function find_or_create_author( string $name ): int {
		$existing = get_posts(
			array(
				'post_type'   => Post_Types::AUTHOR,
				'post_status' => 'any',
				'title'       => $name,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}
		return (int) wp_insert_post(
			array(
				'post_type'   => Post_Types::AUTHOR,
				'post_title'  => $name,
				'post_status' => 'draft',
			)
		);
	}

	/**
	 * Recomputes the book counters of all authors.
	 *
	 * @subcommand recount
	 */
	public function recount(): void {
		$ids = get_posts(
			array(
				'post_type'   => Post_Types::AUTHOR,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			Book_Counts::recount( (int) $id );
		}
		WP_CLI::success( sprintf( 'Recounted %d authors.', count( $ids ) ) );
	}
}
