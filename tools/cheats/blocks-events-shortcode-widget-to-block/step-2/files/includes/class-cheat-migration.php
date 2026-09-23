<?php
/**
 * Acme Events 3.0 migrations.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Widget + shortcode migrations.
 */
class Cheat_Migration {

	/**
	 * Convert classic widgets to block widgets.
	 */
	public static function widgets() {
		$sidebars = get_option( 'sidebars_widgets', array() );
		$legacy   = get_option( 'widget_acme_upcoming_events', array() );
		if ( ! $legacy ) {
			return;
		}
		$blocks = get_option( 'widget_block', array() );
		$n      = 2;
		foreach ( $sidebars as $sidebar => $ids ) {
			if ( ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $i => $id ) {
				if ( 0 === strpos( $id, 'acme_upcoming_events-' ) ) {
					$inst             = $legacy[ (int) substr( $id, 21 ) ] ?? array();
					$attrs            = array(
						'title'     => $inst['title'] ?? '',
						'limit'     => (int) ( $inst['count'] ?? 5 ),
						'category'  => (string) ( $inst['category'] ?? '' ),
						'showVenue' => ! empty( $inst['show_venue'] ),
					);
					$blocks[ $n ]     = array( 'content' => '<!-- wp:acme/upcoming-events ' . wp_json_encode( $attrs ) . ' /-->' );
					$sidebars[ $sidebar ][ $i ] = 'block-' . $n;
					++$n;
				}
			}
		}
		update_option( 'widget_block', $blocks );
		update_option( 'sidebars_widgets', $sidebars );
		delete_option( 'widget_acme_upcoming_events' );
	}

	/**
	 * Replace shortcode blocks.
	 *
	 * @param string $content Content.
	 * @param int    $count   Replacements.
	 * @return string
	 */
	public static function content( $content, &$count ) {
		return preg_replace_callback(
			'#<!-- wp:shortcode -->\s*\[acme_events([^\]]*)\]\s*<!-- /wp:shortcode -->#',
			static function ( $m ) {
				$atts = shortcode_parse_atts( trim( $m[1] ) );
				return '<!-- wp:acme/upcoming-events ' . wp_json_encode( is_array( $atts ) ? $atts : new \stdClass() ) . ' /-->';
			},
			$content,
			-1,
			$count
		);
	}
}
