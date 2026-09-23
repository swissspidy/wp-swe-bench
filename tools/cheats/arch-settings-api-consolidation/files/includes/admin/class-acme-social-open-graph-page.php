<?php
/**
 * Acme Social → Open Graph screen.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * Open Graph settings.
 */
class Acme_Social_Open_Graph_Page extends Acme_Social_Settings_Page {

	/**
	 * Menu slug (also the option group).
	 *
	 * @var string
	 */
	protected $slug = 'acme-social-og';

	/**
	 * Section key.
	 *
	 * @var string
	 */
	protected $section = 'og';

	/**
	 * Screen title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Open Graph', 'acme-social' );
	}
}
