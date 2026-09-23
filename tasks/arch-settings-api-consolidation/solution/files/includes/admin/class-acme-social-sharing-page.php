<?php
/**
 * Acme Social → Sharing screen.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sharing settings.
 */
class Acme_Social_Sharing_Page extends Acme_Social_Settings_Page {

	/**
	 * Menu slug (also the option group).
	 *
	 * @var string
	 */
	protected $slug = 'acme-social';

	/**
	 * Section key.
	 *
	 * @var string
	 */
	protected $section = 'sharing';

	/**
	 * Screen title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Sharing', 'acme-social' );
	}
}
