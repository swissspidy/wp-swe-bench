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
	 * Convert [acme_events] shortcodes in Shortcode blocks into Upcoming Events blocks.
	 *
	 * Only Shortcode blocks that contain nothing but [acme_events] shortcodes are converted.
	 * Inline shortcodes and classic content are left alone (the shortcode keeps working there).
	 * Revisions are never changed. Running the command again converts nothing new.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would be converted without saving anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-events migrate-shortcodes --dry-run
	 *     wp acme-events migrate-shortcodes
	 *
	 * @subcommand migrate-shortcodes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_shortcodes( $args, $assoc_args ) {
		global $wpdb;

		$dry_run  = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$migrator = new Shortcode_Migrator();
		$like     = '%' . $wpdb->esc_like( '[' . Shortcode::TAG ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status <> 'auto-draft' AND post_content LIKE %s ORDER BY ID", $like ) );

		$total_shortcodes = 0;
		$total_posts      = 0;
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			list( $content, $count ) = $migrator->migrate_content( $post->post_content );
			if ( ! $count || $content === $post->post_content ) {
				continue;
			}
			$total_shortcodes += $count;
			++$total_posts;
			\WP_CLI::log( sprintf( 'Post %d (%s): %d shortcode(s)', $post->ID, $post->post_type, $count ) );
			if ( $dry_run ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keep content byte-exact (no kses/filters).
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post->ID ) );
			clean_post_cache( $post->ID );
		}

		if ( $dry_run ) {
			\WP_CLI::success( sprintf( 'Dry run: %d shortcode(s) in %d post(s) would be migrated.', $total_shortcodes, $total_posts ) );
		} else {
			\WP_CLI::success( sprintf( 'Migrated %d shortcode(s) in %d post(s).', $total_shortcodes, $total_posts ) );
		}
	}
}
