<?php
/**
 * CSV export.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * CRM → Export (the finance team imports this file every month; keep the columns stable,
 * new columns are only ever added).
 */
class Export {

	const ACTION = 'acme_crm_export';

	/**
	 * CSV columns: header => contact array key.
	 *
	 * @return array<string,string>
	 */
	public static function columns() {
		return array(
			'id'         => 'id',
			'full_name'  => 'full_name',
			'first_name' => 'first_name',
			'last_name'  => 'last_name',
			'email'      => 'email',
			'phone'      => 'phone',
			'company'    => 'company',
			'stage'      => 'stage',
			'created_at' => 'created_at',
		);
	}

	/**
	 * Register the handler.
	 */
	public static function register() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Export URL (with nonce).
	 *
	 * @return string
	 */
	public static function url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
	}

	/**
	 * Stream the CSV.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export contacts.', 'acme-crm' ), 403 );
		}
		check_admin_referer( self::ACTION );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=acme-crm-contacts-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array_keys( self::columns() ), ',', '"', '\\' );
		$page = 1;
		do {
			$result = Contacts::query(
				array(
					'per_page' => 100,
					'page'     => $page,
				)
			);
			foreach ( $result['items'] as $row ) {
				$contact = Contacts::to_array( $row );
				$line    = array();
				foreach ( self::columns() as $key ) {
					$line[] = $contact[ $key ];
				}
				fputcsv( $out, $line, ',', '"', '\\' );
			}
			++$page;
		} while ( count( $result['items'] ) === 100 );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
