<?php
/**
 * Versioned upgrade routines.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs pending upgrades on the first request after a deploy.
 */
class Acme_Social_Upgrader {

	const VERSION_OPTION = 'acme_social_version';

	/**
	 * Upgrade routines: version => method.
	 */
	const ROUTINES = array(
		'2.0.0' => 'upgrade_200',
	);

	/**
	 * Hooks. Late on init, so post types of other plugins are registered.
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 99 );
	}

	/**
	 * Runs every routine newer than the installed version.
	 */
	public function maybe_upgrade() {
		$installed = (string) get_option( self::VERSION_OPTION, '0' );
		if ( version_compare( $installed, ACME_SOCIAL_VERSION, '>=' ) ) {
			return;
		}
		foreach ( self::ROUTINES as $version => $method ) {
			if ( version_compare( $installed, $version, '<' ) ) {
				$this->{$method}();
			}
		}
		update_option( self::VERSION_OPTION, ACME_SOCIAL_VERSION );
	}

	/**
	 * 2.0.0: fourteen options → acme_social_settings.
	 *
	 * Never overwrites settings that already exist (e.g. when the version
	 * option was reset by restoring an old backup).
	 */
	public function upgrade_200() {
		if ( false === get_option( Acme_Social_Settings::OPTION, false ) ) {
			$settings = Acme_Social_Legacy::to_settings( Acme_Social_Legacy::raw_values() );
			add_option( Acme_Social_Settings::OPTION, $settings );
		}
		Acme_Social_Legacy::delete_all();
		acme_social_flush_og_cache();
	}
}
