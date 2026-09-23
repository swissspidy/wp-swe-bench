<?php
/**
 * Finds related posts: editor picks first, then posts that share tags/categories.
 *
 * @package Acme\Related
 */

namespace Acme\Related;

defined( 'ABSPATH' ) || exit;

/**
 * Related posts engine.
 *
 * Scoring (unchanged since 1.2):
 * - every shared tag counts 2 points, every shared category 1 point;
 * - ties are broken by the newer post first, then by the higher ID;
 * - editor picks ("manual picks") always come first, in the order the editor chose them;
 * - posts flagged "never show as related" are skipped unless an editor picked them.
 */
class Engine {

	/** Meta key with the editor picks (1.x: comma separated string, 2.x: array of IDs). */
	const META_MANUAL = '_acme_related_manual';

	/** Meta key: "never show this post as a related post" ('1'; 1.x stored 'yes'). */
	const META_EXCLUDE = '_acme_related_exclude';

	/** Meta key: "don't show a related list under this post". */
	const META_HIDE = '_acme_related_hide';

	/** Maximum number of candidates looked at per post. */
	const MAX_CANDIDATES = 100;

	/** @var Settings */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Related post IDs for a post, in display order.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   How many (0 = setting).
	 * @return int[]
	 */
	public function get_related_ids( $post_id, $count = 0 ) {
		$post_id = (int) $post_id;
		$count   = $count > 0 ? min( 12, (int) $count ) : $this->settings->count();

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$ids = $this->get_manual_picks( $post_id );
		foreach ( $this->get_scored_candidates( $post ) as $candidate_id ) {
			if ( ! in_array( $candidate_id, $ids, true ) ) {
				$ids[] = $candidate_id;
			}
		}

		/**
		 * Filters the ordered list of related post IDs before it is cut to size.
		 *
		 * @param int[] $ids     Related post IDs, best first.
		 * @param int   $post_id The post the list is for.
		 */
		$ids = (array) apply_filters( 'acme_related_post_ids', $ids, $post_id );
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

		return array_slice( $ids, 0, $count );
	}

	/**
	 * Whether the list is switched off for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_hidden_for( $post_id ) {
		return (bool) get_post_meta( $post_id, self::META_HIDE, true );
	}

	/**
	 * Editor picks of a post that can be shown (published, not the post itself).
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	public function get_manual_picks( $post_id ) {
		$picks = array();
		foreach ( self::parse_manual( get_post_meta( $post_id, self::META_MANUAL, true ) ) as $pick ) {
			if ( $pick === $post_id || in_array( $pick, $picks, true ) ) {
				continue;
			}
			$pick_post = get_post( $pick );
			if ( ! $pick_post || 'publish' !== $pick_post->post_status || ! empty( $pick_post->post_password ) ) {
				continue;
			}
			if ( ! is_post_type_viewable( $pick_post->post_type ) ) {
				continue;
			}
			$picks[] = $pick;
		}
		return $picks;
	}

	/**
	 * Parse stored editor picks (both storage formats).
	 *
	 * @param mixed $raw Stored meta value.
	 * @return int[]
	 */
	public static function parse_manual( $raw ) {
		if ( is_string( $raw ) ) {
			// 1.x: "12, 45,7".
			$raw = '' === trim( $raw ) ? array() : explode( ',', $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $raw ) ) );
	}

	/**
	 * Whether a post is flagged "never show as related".
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_excluded( $post_id ) {
		$flag = get_post_meta( $post_id, self::META_EXCLUDE, true );
		return in_array( strtolower( (string) $flag ), array( '1', 'yes', 'on', 'true' ), true );
	}

	/**
	 * Candidates sharing at least one tag or category, best first.
	 *
	 * @param \WP_Post $post Post.
	 * @return int[]
	 */
	private function get_scored_candidates( \WP_Post $post ) {
		$tags = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'ids' ) );
		$cats = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $tags ) ) {
			$tags = array();
		}
		if ( is_wp_error( $cats ) ) {
			$cats = array();
		}
		if ( ! $tags && ! $cats ) {
			return array();
		}

		$tax_query = array( 'relation' => 'OR' );
		if ( $tags ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tags,
			);
		}
		if ( $cats ) {
			$tax_query[] = array(
				'taxonomy'         => 'category',
				'field'            => 'term_id',
				'terms'            => $cats,
				'include_children' => false,
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'has_password'        => false,
				'post__not_in'        => array( $post->ID ),
				'posts_per_page'      => self::MAX_CANDIDATES,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'fields'              => 'ids',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
				'tax_query'           => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		$scored = array();
		foreach ( $query->posts as $candidate_id ) {
			$candidate_id = (int) $candidate_id;
			if ( self::is_excluded( $candidate_id ) ) {
				continue;
			}
			$candidate_tags = wp_get_post_terms( $candidate_id, 'post_tag', array( 'fields' => 'ids' ) );
			$candidate_cats = wp_get_post_terms( $candidate_id, 'category', array( 'fields' => 'ids' ) );
			$score          = 2 * count( array_intersect( $tags, is_array( $candidate_tags ) ? $candidate_tags : array() ) )
				+ count( array_intersect( $cats, is_array( $candidate_cats ) ? $candidate_cats : array() ) );
			if ( $score < 1 ) {
				continue;
			}
			$candidate = get_post( $candidate_id );
			$scored[]  = array(
				'id'    => $candidate_id,
				'score' => $score,
				'date'  => $candidate->post_date_gmt,
			);
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				if ( $a['score'] !== $b['score'] ) {
					return $b['score'] - $a['score'];
				}
				if ( $a['date'] !== $b['date'] ) {
					return strcmp( $b['date'], $a['date'] );
				}
				return $b['id'] - $a['id'];
			}
		);

		return wp_list_pluck( $scored, 'id' );
	}
}
