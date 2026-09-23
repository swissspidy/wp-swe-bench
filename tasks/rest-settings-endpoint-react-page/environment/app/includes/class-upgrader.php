<?php
/**
 * Version upgrades.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Runs upgrade routines when the installed version (option `acme_seo_version`) is older
 * than the code. Routines run in version order, each at most once per upgrade.
 */
class Upgrader {

	const VERSION_OPTION = 'acme_seo_version';

	/**
	 * Version => routine.
	 *
	 * @return array<string, callable>
	 */
	protected static function routines() {
		return array(
			'1.6.0' => array( __CLASS__, 'upgrade_160' ),
			'1.8.0' => array( __CLASS__, 'upgrade_180' ),
		);
	}

	/**
	 * Run pending routines. Called on plugins_loaded for every request (front end,
	 * admin, REST, cron, WP-CLI) because sites are updated by deploys, not in wp-admin.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, VERSION, '>=' ) ) {
			return;
		}

		foreach ( self::routines() as $version => $routine ) {
			if ( version_compare( $installed, $version, '<' ) ) {
				call_user_func( $routine );
			}
		}

		update_option( self::VERSION_OPTION, VERSION );
		Options::flush();

		/**
		 * Fires after Acme SEO was upgraded.
		 *
		 * @since 1.6.0
		 *
		 * @param string $installed Previously installed version.
		 * @param string $version   New version.
		 */
		do_action( 'acme_seo_upgraded', $installed, VERSION );
	}

	/**
	 * 1.6.0: `acme_seo_twitter` was renamed to `acme_seo_twitter_handle`.
	 */
	public static function upgrade_160() {
		$old = get_option( 'acme_seo_twitter', null );
		if ( null !== $old ) {
			if ( null === get_option( 'acme_seo_twitter_handle', null ) ) {
				update_option( 'acme_seo_twitter_handle', $old );
			}
			delete_option( 'acme_seo_twitter' );
		}
	}

	/**
	 * 1.8.0: the three `acme_seo_noindex_{author,date,tag}` options were merged into
	 * `acme_seo_noindex_archives`.
	 */
	public static function upgrade_180() {
		$archives = array();
		foreach ( array( 'author', 'date', 'tag' ) as $archive ) {
			$value = get_option( 'acme_seo_noindex_' . $archive, null );
			if ( null !== $value ) {
				$archives[ $archive ] = Options::truthy( $value ) ? '1' : '';
				delete_option( 'acme_seo_noindex_' . $archive );
			}
		}
		if ( $archives && null === get_option( 'acme_seo_noindex_archives', null ) ) {
			update_option( 'acme_seo_noindex_archives', $archives );
		}
	}
}
