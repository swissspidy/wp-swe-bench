<?php
/**
 * One-time upgrade: classic "Upcoming events" widgets become Upcoming Events block widgets.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Converts every `acme_upcoming_events-N` widget (in any sidebar, including inactive widgets)
 * into a `block-M` widget holding an acme/upcoming-events block with equivalent settings.
 */
class Widget_Migration {

	const LEGACY_ID_BASE = 'acme_upcoming_events';
	const VERSION_OPTION = 'acme_events_db_version';
	const DB_VERSION     = '3.0.0';
	const LOCK_OPTION    = 'acme_events_upgrade_lock';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		// On every kind of request (front end, admin, REST, WP-CLI), after the event taxonomy
		// is registered (term IDs are mapped to slugs) and before anything renders sidebars.
		add_action( 'init', array( $this, 'maybe_upgrade' ), 20 );
	}

	/**
	 * Run the upgrade once.
	 */
	public function maybe_upgrade() {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::DB_VERSION, '>=' ) ) {
			return;
		}
		// Only one request may run the upgrade (a stale lock expires after a minute).
		$lock = get_option( self::LOCK_OPTION );
		if ( $lock && (int) $lock > time() - MINUTE_IN_SECONDS ) {
			return;
		}
		if ( ! $lock && ! add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return;
		}
		update_option( self::LOCK_OPTION, time(), false );

		$this->convert_widgets();

		update_option( self::VERSION_OPTION, self::DB_VERSION );
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Convert all legacy widget instances.
	 *
	 * @return int Number of converted widgets.
	 */
	public function convert_widgets() {
		$sidebars = get_option( 'sidebars_widgets', array() );
		$legacy   = get_option( 'widget_' . self::LEGACY_ID_BASE, array() );
		$blocks   = get_option( 'widget_block', array() );
		if ( ! is_array( $sidebars ) ) {
			return 0;
		}
		$legacy = is_array( $legacy ) ? $legacy : array();
		$blocks = is_array( $blocks ) ? $blocks : array();

		$next = 2;
		foreach ( array_keys( $blocks ) as $key ) {
			if ( is_int( $key ) || ctype_digit( (string) $key ) ) {
				$next = max( $next, (int) $key + 1 );
			}
		}

		$converted = 0;
		foreach ( $sidebars as $sidebar_id => $widget_ids ) {
			if ( ! is_array( $widget_ids ) ) {
				continue;
			}
			foreach ( $widget_ids as $position => $widget_id ) {
				if ( ! preg_match( '/^' . self::LEGACY_ID_BASE . '-(\d+)$/', (string) $widget_id, $m ) ) {
					continue;
				}
				$instance = isset( $legacy[ (int) $m[1] ] ) && is_array( $legacy[ (int) $m[1] ] ) ? $legacy[ (int) $m[1] ] : array();

				$blocks[ $next ] = array( 'content' => $this->block_markup( $instance ) );

				$sidebars[ $sidebar_id ][ $position ] = 'block-' . $next;
				unset( $legacy[ (int) $m[1] ] );
				++$next;
				++$converted;
			}
		}
		if ( ! $converted ) {
			return 0;
		}

		$blocks['_multiwidget'] = 1;
		update_option( 'widget_block', $blocks );
		update_option( 'sidebars_widgets', $sidebars );
		if ( array_filter( array_keys( $legacy ), 'is_int' ) ) {
			update_option( 'widget_' . self::LEGACY_ID_BASE, $legacy );
		} else {
			delete_option( 'widget_' . self::LEGACY_ID_BASE );
		}
		return $converted;
	}

	/**
	 * Block markup equivalent to a widget instance.
	 *
	 * @param array $instance Widget settings (title, count, category term ID, show_venue).
	 * @return string
	 */
	public function block_markup( array $instance ) {
		$instance = array_merge(
			array(
				'title'      => __( 'Upcoming events', 'acme-events' ),
				'count'      => 5,
				'category'   => 0,
				'show_venue' => true,
			),
			$instance
		);

		$category = '';
		if ( ! empty( $instance['category'] ) ) {
			$term = get_term( (int) $instance['category'], Post_Type::TAXONOMY );
			if ( $term instanceof \WP_Term ) {
				$category = $term->slug;
			}
		}

		$options = Listing::normalize(
			array(
				'limit'      => max( 1, min( 10, (int) $instance['count'] ) ),
				'category'   => $category,
				'show_past'  => false,
				'layout'     => 'list',
				'title'      => (string) $instance['title'],
				'show_venue' => (bool) $instance['show_venue'],
			)
		);

		return serialize_block(
			array(
				'blockName'    => 'acme/upcoming-events',
				'attrs'        => Listing::to_block_attributes( $options ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}
