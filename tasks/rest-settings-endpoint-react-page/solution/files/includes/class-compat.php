<?php
/**
 * Back-compat for code that still reads the 1.x options directly.
 *
 * @package Acme\SEO
 */

namespace Acme\SEO;

defined( 'ABSPATH' ) || exit;

/**
 * Answers get_option() for the 1.x option names that other plugins on our sites read
 * (the Acme Social Share plugin): `acme_seo_twitter_handle` ("@handle" or ""),
 * `acme_seo_og_default_image` (attachment ID) and `acme_seo_social_profiles`
 * (network => URL). Values come from the current `acme_seo_settings`.
 */
class Compat {

	/**
	 * Hooks. Registered after the upgrade routines ran (they must see the real options).
	 */
	public static function init() {
		add_filter( 'pre_option_acme_seo_twitter_handle', array( __CLASS__, 'twitter_handle' ) );
		add_filter( 'pre_option_acme_seo_og_default_image', array( __CLASS__, 'og_default_image' ) );
		add_filter( 'pre_option_acme_seo_social_profiles', array( __CLASS__, 'social_profiles' ) );
	}

	/**
	 * `acme_seo_twitter_handle`.
	 *
	 * @return string
	 */
	public static function twitter_handle() {
		$handle = Settings::get()['social']['twitter_handle'];
		return '' === $handle ? '' : '@' . $handle;
	}

	/**
	 * `acme_seo_og_default_image`.
	 *
	 * @return int
	 */
	public static function og_default_image() {
		return Settings::get()['social']['default_image'];
	}

	/**
	 * `acme_seo_social_profiles`.
	 *
	 * @return array
	 */
	public static function social_profiles() {
		return Settings::get()['social']['profiles'];
	}
}
