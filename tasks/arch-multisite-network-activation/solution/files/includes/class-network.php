<?php
/**
 * Multisite support.
 *
 * @package Acme\Directory
 */

namespace Acme\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Network activation, background set-up of large networks, new and deleted sites,
 * network deactivation and network-wide uninstall.
 */
class Network {

	/**
	 * Maximum number of sites set up in one request (activation or one background run).
	 */
	const BATCH_SIZE = 25;

	/**
	 * Background set-up event, scheduled on the network's main site.
	 */
	const SETUP_HOOK = 'acme_directory_network_setup';

	/**
	 * Network option holding the background set-up state: array( 'cursor' => last site ID done ).
	 */
	const STATE_OPTION = 'acme_directory_network_setup';

	/**
	 * Hook up the multisite handlers. Called on plugins_loaded.
	 */
	public static function register() {
		if ( ! is_multisite() ) {
			return;
		}
		add_action( self::SETUP_HOOK, array( __CLASS__, 'run_batch' ) );
		// Core creates the new site's tables at priority 10; set the directory up afterwards.
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_initialize_site' ), 100 );
		add_filter( 'wpmu_drop_tables', array( __CLASS__, 'drop_tables' ), 10, 2 );
		// A site the background set-up has not reached yet sets itself up on its first request.
		add_action( 'init', array( __CLASS__, 'maybe_setup_current_site' ), 1 );
	}

	/**
	 * Whether the plugin is network-activated.
	 *
	 * @return bool
	 */
	public static function is_network_active() {
		if ( ! is_multisite() ) {
			return false;
		}
		$plugins = get_site_option( 'active_sitewide_plugins', array() );
		return is_array( $plugins ) && isset( $plugins[ plugin_basename( ACME_DIRECTORY_FILE ) ] );
	}

	/**
	 * Whether the plugin is activated individually on the current site.
	 *
	 * @return bool
	 */
	public static function is_active_on_current_site() {
		return in_array( plugin_basename( ACME_DIRECTORY_FILE ), (array) get_option( 'active_plugins', array() ), true );
	}

	/**
	 * Network activation: set up the first batch now, the rest in the background.
	 */
	public static function activate() {
		self::stop_background_setup();
		update_network_option( null, self::STATE_OPTION, array( 'cursor' => 0 ) );
		self::run_batch();
	}

	/**
	 * Set up the next batch of sites; schedule another run if sites remain.
	 *
	 * @return int Number of sites handled in this run.
	 */
	public static function run_batch() {
		$state = get_network_option( null, self::STATE_OPTION );
		if ( ! is_array( $state ) ) {
			return 0;
		}
		$cursor = isset( $state['cursor'] ) ? (int) $state['cursor'] : 0;
		$ids    = self::site_ids_after( $cursor, self::BATCH_SIZE );

		foreach ( $ids as $site_id ) {
			Installer::install_site( $site_id );
			$cursor = $site_id;
		}

		if ( count( $ids ) < self::BATCH_SIZE || ! self::site_ids_after( $cursor, 1 ) ) {
			// Done.
			delete_network_option( null, self::STATE_OPTION );
			self::on_main_site(
				static function () {
					wp_clear_scheduled_hook( self::SETUP_HOOK );
				}
			);
			return count( $ids );
		}

		update_network_option( null, self::STATE_OPTION, array( 'cursor' => $cursor ) );
		self::on_main_site(
			static function () {
				if ( ! wp_next_scheduled( self::SETUP_HOOK ) ) {
					wp_schedule_single_event( time(), self::SETUP_HOOK );
				}
			}
		);
		return count( $ids );
	}

	/**
	 * Network deactivation: keep the data, stop scheduled work everywhere the plugin is
	 * no longer active.
	 */
	public static function deactivate() {
		self::stop_background_setup();
		self::each_site(
			static function () {
				if ( ! self::is_active_on_current_site() ) {
					wp_clear_scheduled_hook( Installer::CLEANUP_HOOK );
				}
			},
			get_current_network_id()
		);
	}

	/**
	 * Remove the background set-up state and event.
	 */
	public static function stop_background_setup() {
		delete_network_option( null, self::STATE_OPTION );
		self::on_main_site(
			static function () {
				wp_clear_scheduled_hook( self::SETUP_HOOK );
			}
		);
	}

	/**
	 * A new site was created: set the directory up on it when network-active.
	 *
	 * @param \WP_Site $site New site.
	 */
	public static function on_initialize_site( $site ) {
		if ( self::is_network_active() ) {
			Installer::install_site( (int) $site->blog_id );
		}
	}

	/**
	 * A site is being deleted: drop our tables along with core's.
	 *
	 * @param string[] $tables  Tables to drop.
	 * @param int      $site_id Site ID.
	 * @return string[]
	 */
	public static function drop_tables( $tables, $site_id = 0 ) {
		if ( ! $site_id ) {
			return $tables;
		}
		return array_values( array_unique( array_merge( (array) $tables, Schema::tables_for_site( $site_id ) ) ) );
	}

	/**
	 * First request to a site of a network-activated install that is not set up yet.
	 */
	public static function maybe_setup_current_site() {
		if ( ! self::is_network_active() || wp_installing() ) {
			return;
		}
		if ( (int) get_option( Installer::DB_VERSION_OPTION, 0 ) < DB_VERSION ) {
			Installer::install();
		}
	}

	/**
	 * Run a callback on every site of a network (or of all networks), in that site's context.
	 *
	 * @param callable $callback   Callback, receives the site ID.
	 * @param int|null $network_id Network ID, or null for every network.
	 */
	public static function each_site( callable $callback, $network_id = null ) {
		global $wpdb;
		$cursor = 0;
		do {
			if ( null === $network_id ) {
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id > %d ORDER BY blog_id ASC LIMIT %d", $cursor, 100 ) );
			} else {
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = %d AND blog_id > %d ORDER BY blog_id ASC LIMIT %d", $network_id, $cursor, 100 ) );
			}
			foreach ( $ids as $id ) {
				$id = (int) $id;
				switch_to_blog( $id );
				$callback( $id );
				restore_current_blog();
				$cursor = $id;
			}
			$more = 100 === count( $ids );
		} while ( $more );
	}

	/**
	 * IDs of the current network's sites after a given ID.
	 *
	 * @param int $after Site ID cursor.
	 * @param int $limit Max number of IDs.
	 * @return int[]
	 */
	private static function site_ids_after( $after, $limit ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = %d AND blog_id > %d ORDER BY blog_id ASC LIMIT %d",
				get_current_network_id(),
				$after,
				$limit
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Run a callback in the context of the current network's main site.
	 *
	 * @param callable $callback Callback.
	 */
	private static function on_main_site( callable $callback ) {
		$main     = get_main_site_id();
		$switched = get_current_blog_id() !== $main;
		if ( $switched ) {
			switch_to_blog( $main );
		}
		$callback();
		if ( $switched ) {
			restore_current_blog();
		}
	}
}
