<?php
/**
 * WP-CLI commands.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Acme events.
 */
class CLI {

	/**
	 * List events.
	 *
	 * ## OPTIONS
	 *
	 * [--past]
	 * : Include past events.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-events list --past --format=csv
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$past  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'past', false );
		$query = array(
			'post_type'      => Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => ACME_EVENTS_META_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
		);
		if ( ! $past ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => ACME_EVENTS_META_START,
					'value'   => current_time( 'Y-m-d H:i' ),
					'compare' => '>=',
				),
			);
		}
		$events = get_posts( $query );
		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		if ( 'ids' === $format ) {
			\WP_CLI::line( implode( ' ', wp_list_pluck( $events, 'ID' ) ) );
			return;
		}
		$rows = array();
		foreach ( $events as $event ) {
			$terms  = get_the_terms( $event, Post_Type::TAXONOMY );
			$rows[] = array(
				'ID'         => $event->ID,
				'title'      => get_the_title( $event ),
				'start'      => get_post_meta( $event->ID, ACME_EVENTS_META_START, true ),
				'venue'      => acme_events_get_venue( $event ),
				'categories' => $terms && ! is_wp_error( $terms ) ? implode( ',', wp_list_pluck( $terms, 'slug' ) ) : '',
				'status'     => $event->post_status,
			);
		}
		\WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'title', 'start', 'venue', 'categories', 'status' ) );
	}

	/**
	 * Convert [acme_events] Shortcode blocks into Upcoming Events blocks.
	 *
	 * [--dry-run]
	 * : Don't save.
	 *
	 * @subcommand migrate-shortcodes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_shortcodes( $args, $assoc_args ) {
		$dry   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				's'              => '[acme_events',
			)
		);
		$total = 0;
		$n     = 0;
		foreach ( $posts as $post ) {
			$count   = 0;
			$content = Cheat_Migration::content( $post->post_content, $count );
			if ( ! $count ) {
				continue;
			}
			$total += $count;
			++$n;
			\WP_CLI::log( sprintf( 'Post %d: %d shortcode(s)', $post->ID, $count ) );
			if ( ! $dry ) {
				wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $content ) ) );
			}
		}
		if ( $dry ) {
			\WP_CLI::success( sprintf( 'Dry run: %d shortcode(s) in %d post(s) would be migrated.', $total, $n ) );
		} else {
			\WP_CLI::success( sprintf( 'Migrated %d shortcode(s) in %d post(s).', $total, $n ) );
		}
	}
}
