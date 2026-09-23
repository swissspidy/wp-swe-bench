<?php
/**
 * Plugin Name: Acme network tweaks
 * Description: Network-wide customisations for the Acme local network (maintained by the platform team).
 */

/*
 * Every directory gets the network's "partners" category, and submissions are
 * reported to the site's own directory mailbox.
 */
add_action(
	'acme_directory_installed',
	static function () {
		global $wpdb;
		if ( ! function_exists( 'acme_directory_table' ) ) {
			return;
		}
		$table = acme_directory_table( 'categories' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE slug = %s", 'network-partners' ) );
		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'name'        => 'Network partners',
					'slug'        => 'network-partners',
					'description' => 'Businesses that sponsor the Acme local network.',
					'created_at'  => current_time( 'mysql', true ),
				)
			);
		}
		update_option( 'acme_network_directory_site', get_current_blog_id() );
	}
);

add_filter(
	'acme_directory_default_settings',
	static function ( $defaults ) {
		$defaults['notify_email'] = 'directory+' . get_current_blog_id() . '@acme.example';
		return $defaults;
	}
);
