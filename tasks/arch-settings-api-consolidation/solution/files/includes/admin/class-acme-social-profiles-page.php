<?php
/**
 * Acme Social → Social profiles screen.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Social profiles settings.
 */
class Acme_Social_Profiles_Page extends Acme_Social_Settings_Page {

	/**
	 * Menu slug (also the option group).
	 *
	 * @var string
	 */
	protected $slug = 'acme-social-profiles';

	/**
	 * Section key.
	 *
	 * @var string
	 */
	protected $section = 'profiles';

	/**
	 * Screen title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Social profiles', 'acme-social' );
	}

	/**
	 * Intro text.
	 *
	 * @return string
	 */
	public function description() {
		return __( 'Shown by the [acme_social_profiles] shortcode and used for the Twitter card tags.', 'acme-social' );
	}
}
