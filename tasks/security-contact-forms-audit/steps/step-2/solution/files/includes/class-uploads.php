<?php
/**
 * Upload policy and storage for file fields.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which files a file field accepts, stores them under unguessable names and resolves
 * stored files (including the ones 2.1–2.4 stored under their original names) for downloads.
 */
class Uploads {

	/**
	 * Accepted when a file field has no `allowed` list of its own.
	 */
	const DEFAULT_ALLOWED = array( 'pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png' );

	/**
	 * Types we know how to verify: extension => detected content types that match it.
	 */
	const TYPES = array(
		'pdf'  => array( 'application/pdf' ),
		'doc'  => array( 'application/msword', 'application/cdfv2', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/vnd.ms-word' ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
		'odt'  => array( 'application/vnd.oasis.opendocument.text', 'application/zip' ),
		'rtf'  => array( 'text/rtf', 'application/rtf' ),
		'txt'  => array( 'text/plain' ),
		'csv'  => array( 'text/plain', 'text/csv' ),
		'jpg'  => array( 'image/jpeg' ),
		'jpeg' => array( 'image/jpeg' ),
		'png'  => array( 'image/png' ),
		'gif'  => array( 'image/gif' ),
		'webp' => array( 'image/webp' ),
		'zip'  => array( 'application/zip' ),
	);

	/**
	 * Never accepted, whatever a form says: pages a browser renders (and runs scripts in),
	 * and anything the server could execute.
	 */
	const NEVER = array(
		'htm',
		'html',
		'shtml',
		'xhtml',
		'xht',
		'mht',
		'mhtml',
		'svg',
		'svgz',
		'xml',
		'xsl',
		'xslt',
		'js',
		'mjs',
		'swf',
		'php',
		'php3',
		'php4',
		'php5',
		'php7',
		'php8',
		'phtml',
		'phar',
		'phps',
		'pht',
		'cgi',
		'pl',
		'py',
		'sh',
		'asp',
		'aspx',
		'jsp',
		'exe',
		'bat',
		'htaccess',
	);

	/**
	 * Extensions a field accepts (lower case).
	 *
	 * @param array $field File field.
	 * @return string[]
	 */
	public static function allowed_extensions( array $field ) {
		$list = array();
		if ( ! empty( $field['allowed'] ) ) {
			$list = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $field['allowed'] ) ) );
			$list = array_map(
				static function ( $ext ) {
					return ltrim( $ext, '.' );
				},
				$list
			);
		}
		if ( ! $list ) {
			$list = self::DEFAULT_ALLOWED;
		}
		$list = array_diff( array_filter( $list ), self::NEVER );
		return array_values( array_intersect( array_unique( $list ), array_keys( self::TYPES ) ) );
	}

	/**
	 * Check an uploaded file against a field's policy.
	 *
	 * @param array $file  $_FILES entry (name, tmp_name).
	 * @param array $field File field.
	 * @return string|\WP_Error The (lower-case) extension, or an error.
	 */
	public static function check( array $file, array $field ) {
		$name  = strtolower( wp_basename( (string) $file['name'] ) );
		$parts = explode( '.', $name );
		if ( count( $parts ) < 2 ) {
			return new \WP_Error( 'type', __( 'This file type is not allowed.', 'acme-forms' ) );
		}
		$ext = array_pop( $parts );

		// No dangerous extension anywhere in the name (cv.php.pdf, page.html.png, …).
		array_shift( $parts );
		foreach ( array_merge( $parts, array( $ext ) ) as $segment ) {
			if ( in_array( $segment, self::NEVER, true ) ) {
				return new \WP_Error( 'type', __( 'This file type is not allowed.', 'acme-forms' ) );
			}
		}
		if ( ! in_array( $ext, self::allowed_extensions( $field ), true ) ) {
			return new \WP_Error( 'type', __( 'This file type is not allowed.', 'acme-forms' ) );
		}

		// The content must be what the name claims.
		$detected = self::detect( (string) $file['tmp_name'] );
		if ( ! $detected || ! in_array( $detected, self::TYPES[ $ext ], true ) ) {
			return new \WP_Error( 'content', __( 'The file content does not match its type.', 'acme-forms' ) );
		}
		return $ext;
	}

	/**
	 * Detected content type of a file (lower case), '' if unknown.
	 *
	 * @param string $path File.
	 * @return string
	 */
	protected static function detect( $path ) {
		if ( ! is_file( $path ) || ! function_exists( 'finfo_open' ) ) {
			return '';
		}
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$type  = $finfo ? finfo_file( $finfo, $path ) : '';
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		return strtolower( (string) $type );
	}

	/**
	 * Move a checked upload into wp-content/uploads/acme-forms/YYYY/MM/ under a random name.
	 *
	 * @param array  $file $_FILES entry.
	 * @param string $ext  Checked extension.
	 * @return array|null File info (name = original name, path relative to acme-forms, type, size).
	 */
	public static function store( array $file, $ext ) {
		$base   = acme_forms_upload_base();
		$subdir = gmdate( 'Y/m' );
		$dir    = $base['dir'] . '/' . $subdir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		self::protect( $base['dir'] );

		$stored = bin2hex( random_bytes( 16 ) ) . '.' . $ext;
		if ( ! move_uploaded_file( $file['tmp_name'], $dir . '/' . $stored ) ) {
			return null;
		}
		$original = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		return array(
			'name' => '' !== $original ? $original : 'file.' . $ext,
			'path' => $subdir . '/' . $stored,
			'type' => isset( self::TYPES[ $ext ] ) ? self::TYPES[ $ext ][0] : 'application/octet-stream',
			'size' => (int) $file['size'],
		);
	}

	/**
	 * Keep web servers that honour these files from listing or serving the folder directly.
	 *
	 * @param string $dir Base folder.
	 */
	protected static function protect( $dir ) {
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Absolute path of a stored file, only if it really lies inside the uploads folder.
	 *
	 * @param array $file Stored file info.
	 * @return string|null
	 */
	public static function path( array $file ) {
		if ( empty( $file['path'] ) ) {
			return null;
		}
		$base = realpath( acme_forms_upload_base()['dir'] );
		$path = realpath( acme_forms_upload_base()['dir'] . '/' . ltrim( (string) $file['path'], '/' ) );
		if ( ! $base || ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return null;
		}
		return $path;
	}
}
