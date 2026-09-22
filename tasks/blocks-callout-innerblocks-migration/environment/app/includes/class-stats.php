<?php
/**
 * Callout usage statistics (admin column on the posts list).
 *
 * @package Acme\Callouts
 */

namespace Acme\Callouts;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a per-post count of callouts in the `_acme_callout_count` meta key.
 */
class Stats {

	const META_KEY = '_acme_callout_count';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'save_post', array( $this, 'update_count' ), 10, 2 );
		add_filter( 'manage_posts_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Count callout blocks (including nested ones) and [callout] shortcodes in content.
	 *
	 * @param string $content Post content.
	 * @return int
	 */
	public static function count_in_content( $content ) {
		$count = self::count_blocks( parse_blocks( (string) $content ) );

		if ( false !== strpos( (string) $content, '[' . Shortcode::TAG ) ) {
			$pattern = get_shortcode_regex( array( Shortcode::TAG ) );
			if ( preg_match_all( '/' . $pattern . '/', (string) $content, $matches ) ) {
				$count += count( $matches[0] );
			}
		}

		return $count;
	}

	/**
	 * Recursively count acme/callout blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return int
	 */
	private static function count_blocks( array $blocks ) {
		$count = 0;
		foreach ( $blocks as $block ) {
			if ( 'acme/callout' === $block['blockName'] ) {
				++$count;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$count += self::count_blocks( $block['innerBlocks'] );
			}
		}
		return $count;
	}

	/**
	 * Store the count when a post is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function update_count( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, self::META_KEY, self::count_in_content( $post->post_content ) );
	}

	/**
	 * Add the "Callouts" column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['acme_callouts'] = __( 'Callouts', 'acme-callouts' );
		return $columns;
	}

	/**
	 * Render the "Callouts" column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'acme_callouts' !== $column ) {
			return;
		}
		$count = get_post_meta( $post_id, self::META_KEY, true );
		echo '' === $count ? '&mdash;' : esc_html( number_format_i18n( (int) $count ) );
	}
}
