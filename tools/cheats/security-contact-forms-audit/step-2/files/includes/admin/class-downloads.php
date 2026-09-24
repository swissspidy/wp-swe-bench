<?php
/**
 * Download handler for uploaded files.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms\Admin;

use Acme\Forms\Installer;
use Acme\Forms\Submissions;
use Acme\Forms\Uploads;

defined( 'ABSPATH' ) || exit;

/**
 * admin-post.php?action=acme_forms_download&submission=ID&field=NAME (see Formatter::download_url()).
 *
 * Files are only handed out to inbox users, always as a download (never rendered by the
 * browser), and only files that belong to the submission.
 */
class Downloads {

	const ACTION = 'acme_forms_download';

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Send the file.
	 */
	public function handle() {
		if ( ! current_user_can( Installer::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to download this file.', 'acme-forms' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, capability checked.
		$id    = isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0;
		$field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
		// phpcs:enable

		$submission = $id ? Submissions::get( $id ) : null;
		$file       = $submission && isset( $submission->files[ $field ] ) && is_array( $submission->files[ $field ] ) ? $submission->files[ $field ] : null;
		$path       = $file ? Uploads::path( $file ) : null;
		if ( ! $path ) {
			wp_die( esc_html__( 'File not found.', 'acme-forms' ), '', array( 'response' => 404 ) );
		}

		$name = sanitize_file_name( (string) $file['name'] );
		if ( '' === $name ) {
			$name = 'download';
		}

		nocache_headers();
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: sandbox' );
		header( 'Content-Length: ' . filesize( $path ) );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
