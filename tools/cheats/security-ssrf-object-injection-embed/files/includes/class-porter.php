<?php
/**
 * Import / export of the preview cache.
 *
 * Exports are JSON. Files exported by older versions used PHP's serialize(), so
 * they are still importable — but never in a way that instantiates an object
 * (which would be a PHP object-injection vector).
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
		return base64_encode( wp_json_encode( Cache::all() ) );
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

		$data = self::decode( $decoded );
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
	 * Decode an export payload as data only (JSON, or legacy serialized without
	 * instantiating objects).
	 *
	 * @param string $payload Raw (base64-decoded) payload.
	 * @return array|null
	 */
	private static function decode( $payload ) {
		$json = json_decode( $payload, true );
		if ( is_array( $json ) ) {
			return $json;
		}
		$legacy = @unserialize( $payload, array( 'allowed_classes' => false ) );
		return is_array( $legacy ) ? $legacy : null;
	}

	/**
	 * Keep only the known preview fields, as strings.
	 *
	 * @param array $preview Raw preview.
	 * @return array<string, string>
	 */
	public static function sanitize_preview( array $preview ) {
		return array(
			'url'         => isset( $preview['url'] ) && is_scalar( $preview['url'] ) ? (string) $preview['url'] : '',
			'title'       => isset( $preview['title'] ) && is_scalar( $preview['title'] ) ? (string) $preview['title'] : '',
			'description' => isset( $preview['description'] ) && is_scalar( $preview['description'] ) ? (string) $preview['description'] : '',
			'image'       => isset( $preview['image'] ) && is_scalar( $preview['image'] ) ? (string) $preview['image'] : '',
		);
	}
}
