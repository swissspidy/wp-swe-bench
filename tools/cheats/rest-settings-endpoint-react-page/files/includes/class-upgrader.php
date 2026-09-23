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
			'2.0.0' => array( __CLASS__, 'upgrade_200' ),
		);
	}

	/**
	 * Run pending routines. Called on every request (front end, admin, REST, cron,
	 * WP-CLI) because sites are updated by deploys, not in wp-admin. Runs late on `init`
	 * so that custom post types (part of the settings) are registered.
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
				$archives[ $archive ] = Legacy_Options::truthy( $value ) ? '1' : '';
				delete_option( 'acme_seo_noindex_' . $archive );
			}
		}
		if ( $archives && null === get_option( 'acme_seo_noindex_archives', null ) ) {
			update_option( 'acme_seo_noindex_archives', $archives );
		}
	}

	/**
	 * 2.0.0: the twelve `acme_seo_*` options become the single `acme_seo_settings` option.
	 *
	 * Values are read exactly like 1.9 read them (Legacy_Options). If `acme_seo_settings`
	 * already exists it wins (e.g. the routine runs again after a partial restore) and the
	 * old options are only removed. Safe to run any number of times.
	 */
	public static function upgrade_200() {
		if ( null !== get_option( Settings::OPTION, null ) ) {
			return;
		}
		$archives = (array) get_option( 'acme_seo_noindex_archives', array() );
		update_option(
			Settings::OPTION,
			Settings::normalize(
				array(
					'titles'       => array(
						'separator'        => get_option( 'acme_seo_title_separator', '-' ),
						'home_title'       => get_option( 'acme_seo_home_title', '%%sitename%% %%sep%% %%tagline%%' ),
						'home_description' => get_option( 'acme_seo_home_description', '' ),
					),
					'indexing'     => array(
						'noindex_post_types'      => (array) get_option( 'acme_seo_noindex_post_types', array() ),
						'noindex_author_archives' => ! empty( $archives['author'] ),
						'noindex_date_archives'   => ! empty( $archives['date'] ),
						'noindex_tag_archives'    => ! empty( $archives['tag'] ),
					),
					'social'       => array(
						'og_enabled'     => 'no' !== get_option( 'acme_seo_og_enabled', 'yes' ),
						'default_image'  => (int) get_option( 'acme_seo_og_default_image', 0 ),
						'twitter_handle' => (string) get_option( 'acme_seo_twitter_handle', '' ),
						'profiles'       => (array) get_option( 'acme_seo_social_profiles', array() ),
					),
					'sitemap'      => array(
						'enabled' => '0' !== get_option( 'acme_seo_sitemap_enabled', '1' ),
						'exclude' => array_map( 'intval', explode( ',', (string) get_option( 'acme_seo_sitemap_exclude', '' ) ) ),
					),
					'verification' => (array) get_option( 'acme_seo_verification', array() ),
				)
			)
		);
		foreach ( Legacy_Options::MAP as $option ) {
			delete_option( $option );
		}
		Settings::flush();
	}
}
