<?php
/**
 * The [acme_events] shortcode.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Renders event listings: `[acme_events limit="5" category="workshops" show_past="no" layout="list" title="" show_venue="yes"]`.
 *
 * Query and markup live in Listing, shared with the block and the widget.
 */
class Shortcode {

	const TAG = 'acme_events';

	/**
	 * Hard cap on the number of events in one listing.
	 */
	const MAX_LIMIT = Listing::MAX_LIMIT;

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the shortcode.
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Default shortcode attributes.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'limit'      => 5,
			'category'   => '',
			'show_past'  => 'no',
			'layout'     => 'list',
			'title'      => '',
			'show_venue' => 'yes',
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string       $content Unused.
	 * @param string       $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts, $content = '', $tag = self::TAG ) {
		return Listing::render( Listing::from_shortcode_atts( $atts, $tag ? $tag : self::TAG ) );
	}
}
