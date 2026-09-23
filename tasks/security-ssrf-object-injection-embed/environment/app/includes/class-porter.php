<?php
/**
 * Import / export of the preview cache.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Import / export.
 */
class Porter {

	/**
	 * Export the whole preview cache as a portable blob.
	 *
	 * @return string
	 */
	public static function export() {
		return base64_encode( serialize( Cache::all() ) );
	}

	/**
	 * Import a previously exported blob.
	 *
	 * @param string $blob Exported blob.
	 * @return int|\WP_Error Number of previews imported.
	 */
	public static function import( $blob ) {
		$decoded = base64_decode( (string) $blob, true );
		if ( false === $decoded ) {
			return new \WP_Error( 'acme_lp_bad_import', __( 'The import file is not valid.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}

		$data = @unserialize( $decoded );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'acme_lp_bad_import', __( 'The import file is not valid.', 'acme-link-previews' ), array( 'status' => 400 ) );
		}

		$clean = array();
		foreach ( $data as $key => $preview ) {
			if ( ! is_array( $preview ) ) {
				continue;
			}
			$clean[ (string) $key ] = self::sanitize_preview( $preview );
		}

		Cache::replace( $clean );
		return count( $clean );
	}

	/**
	 * Keep only the known preview fields.
	 *
	 * @param array $preview Raw preview.
	 * @return array<string, string>
	 */
	public static function sanitize_preview( array $preview ) {
		return array(
			'url'         => isset( $preview['url'] ) ? (string) $preview['url'] : '',
			'title'       => isset( $preview['title'] ) ? (string) $preview['title'] : '',
			'description' => isset( $preview['description'] ) ? (string) $preview['description'] : '',
			'image'       => isset( $preview['image'] ) ? (string) $preview['image'] : '',
		);
	}
}
