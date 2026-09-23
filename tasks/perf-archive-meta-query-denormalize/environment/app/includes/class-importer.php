<?php
/**
 * CSV importer (the agency exports its MLS feed every night).
 *
 * Columns: ref, title, price, bedrooms, bathrooms, sqft, city, features (pipe separated), status.
 * `ref` is stored in `_acme_ref`; an existing listing with the same ref is updated.
 *
 * @package Acme\RealEstate
 */

namespace Acme\RealEstate;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
class Importer {

	const META_REF = '_acme_ref';

	/**
	 * Import a CSV file.
	 *
	 * @param string $file Path.
	 * @return array{created: int, updated: int, skipped: int}|\WP_Error
	 */
	public function import_file( $file ) {
		if ( ! is_readable( $file ) ) {
			return new \WP_Error( 'acme_re_unreadable', __( 'The file cannot be read.', 'acme-real-estate' ) );
		}
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$header = fgetcsv( $handle );
		$stats  = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
		);
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return $stats;
		}
		$header = array_map( 'trim', $header );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( count( $row ) !== count( $header ) ) {
				++$stats['skipped'];
				continue;
			}
			$result = $this->import_row( array_combine( $header, $row ) );
			if ( is_wp_error( $result ) ) {
				++$stats['skipped'];
			} else {
				++$stats[ $result ];
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $stats;
	}

	/**
	 * Import one row.
	 *
	 * @param array $row Row.
	 * @return string|\WP_Error 'created' or 'updated'.
	 */
	public function import_row( array $row ) {
		$ref = isset( $row['ref'] ) ? sanitize_text_field( $row['ref'] ) : '';
		if ( '' === $ref || empty( $row['title'] ) ) {
			return new \WP_Error( 'acme_re_invalid_row', __( 'A row needs a ref and a title.', 'acme-real-estate' ) );
		}

		$existing = get_posts(
			array(
				'post_type'      => Listing::POST_TYPE,
				'post_status'    => 'any',
				'meta_key'       => self::META_REF, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $ref, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);

		if ( $existing ) {
			$post_id = (int) $existing[0];
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $row['title'] ),
				)
			);
			$outcome = 'updated';
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'   => Listing::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => sanitize_text_field( $row['title'] ),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			update_post_meta( $post_id, self::META_REF, $ref );
			$outcome = 'created';
		}

		// Meta is written after the post was saved (the post must exist first).
		update_post_meta( $post_id, Listing::META_PRICE, (int) preg_replace( '/[^\d]/', '', (string) $row['price'] ) );
		if ( isset( $row['bedrooms'] ) && '' !== trim( $row['bedrooms'] ) ) {
			update_post_meta( $post_id, Listing::META_BEDROOMS, (int) $row['bedrooms'] );
		} else {
			delete_post_meta( $post_id, Listing::META_BEDROOMS );
		}
		update_post_meta( $post_id, Listing::META_BATHROOMS, (int) ( $row['bathrooms'] ?? 0 ) );
		update_post_meta( $post_id, Listing::META_SQFT, (int) ( $row['sqft'] ?? 0 ) );
		update_post_meta( $post_id, Listing::META_CITY, sanitize_text_field( $row['city'] ?? '' ) );
		update_post_meta( $post_id, Listing::META_FEATURES, Features::sanitize_list( explode( '|', (string) ( $row['features'] ?? '' ) ) ) );
		$status = sanitize_key( $row['status'] ?? 'for-sale' );
		update_post_meta( $post_id, Listing::META_STATUS, isset( Listing::statuses()[ $status ] ) ? $status : 'for-sale' );

		return $outcome;
	}
}
