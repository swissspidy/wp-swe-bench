<?php
/**
 * [acme_stats] shortcode.
 *
 * - `[acme_stats]`: public site totals ("1,234 published posts, …"), used on the About page.
 *   Can be switched off (setting `public_totals`).
 * - `[acme_stats scope="me"]`: the logged-in author's own report (used on the intranet page).
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode.
 */
class Shortcode {

	/** @var Stats */
	private $stats;

	/**
	 * Constructor.
	 *
	 * @param Stats $stats Stats.
	 */
	public function __construct( Stats $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_shortcode( 'acme_stats', array( $this, 'render' ) );
	}

	/**
	 * Render.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts( array( 'scope' => 'site' ), $atts, 'acme_stats' );

		if ( 'me' === $atts['scope'] ) {
			$user_id = get_current_user_id();
			if ( ! $user_id || ! user_can( $user_id, 'edit_posts' ) ) {
				return '';
			}
			$scope = Scope::author( $user_id );
			return '<div class="acme-stats-shortcode acme-stats-shortcode--me">' . Format::report( $this->stats->get( $scope ) ) . '</div>';
		}

		$settings = Plugin::settings();
		if ( empty( $settings['public_totals'] ) ) {
			return '';
		}
		return '<div class="acme-stats-shortcode">' . Format::totals( $this->stats->get( Scope::site() ) ) . '</div>';
	}
}
