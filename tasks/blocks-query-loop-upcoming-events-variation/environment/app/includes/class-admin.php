<?php
/**
 * Events list screen tweaks.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Admin columns + sorting by start date.
 */
class Admin {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'manage_' . POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . POST_TYPE . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'sort' ) );
	}

	/**
	 * Columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['acme_event_start']  = __( 'Starts', 'acme-events-lite' );
				$new['acme_event_status'] = __( 'Status', 'acme-events-lite' );
			}
		}
		unset( $new['date'] );
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public function column( $column, $post_id ) {
		$event = get_event( $post_id );
		if ( ! $event ) {
			return;
		}
		if ( 'acme_event_start' === $column ) {
			echo esc_html( format_event_date( $post_id ) );
			if ( $event->has_ended() ) {
				echo ' <span class="post-state">(' . esc_html__( 'past', 'acme-events-lite' ) . ')</span>';
			}
		} elseif ( 'acme_event_status' === $column ) {
			$labels = array(
				'scheduled' => __( 'Scheduled', 'acme-events-lite' ),
				'postponed' => __( 'Postponed', 'acme-events-lite' ),
				'cancelled' => __( 'Cancelled', 'acme-events-lite' ),
			);
			echo esc_html( $labels[ $event->get_status() ] );
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function sortable( $columns ) {
		$columns['acme_event_start'] = 'acme_event_start';
		return $columns;
	}

	/**
	 * Sort by start in the admin list.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function sort( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'acme_event_start' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', META_START );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
