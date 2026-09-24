<?php
/**
 * Plugin Name: wpsb CRM test migrations
 * Description: Test-only add-on migrations, configured through the wpsb_crm_test_migrations option.
 */

add_filter(
	'acme_crm_migrations',
	static function ( $migrations ) {
		$config = get_option( 'wpsb_crm_test_migrations' );
		if ( ! is_array( $config ) ) {
			return $migrations;
		}
		foreach ( $config as $version => $spec ) {
			$version = (int) $version;
			$mode    = isset( $spec['mode'] ) ? $spec['mode'] : 'ok';
			$entry   = array(
				'description' => isset( $spec['description'] ) ? $spec['description'] : "Test migration $version",
				'up'          => static function () use ( $version, $spec, $mode ) {
					file_put_contents( WP_CONTENT_DIR . '/wpsb-crm-migrations.log', "up $version " . microtime( true ) . "\n", FILE_APPEND | LOCK_EX );
					if ( ! empty( $spec['sleep'] ) ) {
						sleep( (int) $spec['sleep'] );
					}
					switch ( $mode ) {
						case 'throw':
							throw new RuntimeException( isset( $spec['message'] ) ? $spec['message'] : "boom $version" );
						case 'wp_error':
							return new WP_Error( 'wpsb_test', isset( $spec['message'] ) ? $spec['message'] : "wp error $version" );
						case 'false':
							return false;
						case 'dberror':
							global $wpdb;
							$wpdb->query( 'SELECT * FROM wpsb_no_such_table_' . $version );
							return null;
					}
					update_option( 'wpsb_crm_test_marker_' . $version, 'applied' );
					return true;
				},
			);
			if ( ! empty( $spec['down'] ) ) {
				$entry['down'] = static function () use ( $version ) {
					file_put_contents( WP_CONTENT_DIR . '/wpsb-crm-migrations.log', "down $version " . microtime( true ) . "\n", FILE_APPEND | LOCK_EX );
					delete_option( 'wpsb_crm_test_marker_' . $version );
					return true;
				};
			}
			$migrations[ $version ] = $entry;
		}
		return $migrations;
	}
);
