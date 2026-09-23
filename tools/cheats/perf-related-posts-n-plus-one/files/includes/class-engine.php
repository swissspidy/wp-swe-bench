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
 *
 * Performance (2.4): lists are computed in batches. Every post fetched by a query
 * (main query, REST collections, query loops) is queued; the first time a list is
 * needed, the lists of all queued posts are computed together with a constant number
 * of queries, and everything the items need (posts, meta, terms, authors, images,
 * views) is primed at once. Computed lists live in the object cache, keyed by the
 * posts/terms "last changed" stamps, so any change to posts, post meta or terms
 * invalidates them.
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

	/** Object cache group. */
	const CACHE_GROUP = 'acme_related';

	/** @var Settings */
	private $settings;

	/** @var array<int, bool> Posts whose list will probably be needed (queued by queries). */
	private $queue = array();

	/** @var array<int, int[]> Filtered, ordered lists (not cut to size) computed in this request. */
	private $lists = array();

	/** @var string Cache salt the lists in $lists were computed with. */
	private $lists_salt = '';

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_filter( 'the_posts', array( $this, 'queue_from_query' ), 20 );
	}

	/**
	 * Queue the posts a query returned: their lists are computed together when the first one is needed.
	 *
	 * @param \WP_Post[]|int[] $posts Posts.
	 * @return \WP_Post[]|int[]
	 */
	public function queue_from_query( $posts ) {
		if ( ! is_array( $posts ) || ! $posts || ( is_admin() && ! wp_doing_ajax() && ! $this->is_rest() ) ) {
			return $posts;
		}
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status ) {
				$this->queue[ (int) $post->ID ] = true;
			}
		}
		return $posts;
	}

	/**
	 * Queue posts explicitly (e.g. the posts of several blocks on one page).
	 *
	 * @param int[] $post_ids Post IDs.
	 */
	public function queue( array $post_ids ) {
		foreach ( $post_ids as $id ) {
			$this->queue[ (int) $id ] = true;
		}
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

		$this->refresh_salt();
		if ( ! isset( $this->lists[ $post_id ] ) ) {
			$this->compute_batch( $post_id, $count );
		}

		return array_slice( $this->lists[ $post_id ], 0, $count );
	}

	/**
	 * Related items (data) for a post, with everything primed.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   How many (0 = setting).
	 * @return array[]
	 */
	public function get_items( $post_id, $count = 0 ) {
		$ids = $this->get_related_ids( $post_id, $count );
		Item::prime( $ids );
		$items = array();
		foreach ( $ids as $id ) {
			$item = Item::from_post( $id );
			if ( $item ) {
				$items[] = $item;
			}
		}
		return $items;
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
		$raw = self::parse_manual( get_post_meta( $post_id, self::META_MANUAL, true ) );
		_prime_post_caches( $raw, false, false );

		$picks = array();
		foreach ( $raw as $pick ) {
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
	 * Forget lists computed in this request (tests, long-running CLI commands).
	 */
	public function reset() {
		$this->lists      = array();
		$this->lists_salt = '';
	}

	/**
	 * The lists depend on posts (status, dates, passwords), post meta (picks, flags) and
	 * term relationships. WordPress bumps these "last changed" stamps on every such change.
	 *
	 * @return string
	 */
	private function salt() {
		return 'v1';
	}

	/**
	 * Drop the lists of this request when something changed since they were computed.
	 */
	private function refresh_salt() {
		$salt = $this->salt();
		if ( $salt !== $this->lists_salt ) {
			$this->lists      = array();
			$this->lists_salt = $salt;
		}
	}

	/**
	 * Compute the lists of a post and of all queued posts that don't have one yet.
	 *
	 * @param int $post_id Post that needs its list now.
	 * @param int $count   Items that will be shown for it.
	 */
	private function compute_batch( $post_id, $count ) {
		$batch = array( $post_id );
		foreach ( array_keys( $this->queue ) as $queued ) {
			if ( ! isset( $this->lists[ $queued ] ) && $queued !== $post_id ) {
				$batch[] = $queued;
			}
		}
		$this->queue = array();

		// Persistent object caches keep lists between requests.
		$missing = array();
		foreach ( $batch as $id ) {
			$cached = get_transient( 'acme_related_' . $id );
			if ( is_array( $cached ) ) {
				$this->lists[ $id ] = $cached;
			} else {
				$missing[] = $id;
			}
		}

		if ( $missing ) {
			_prime_post_caches( $missing, true, true );
			$scored = $this->score_candidates( $missing );

			// Editor picks of all posts in one go.
			$all_picks = array();
			foreach ( $missing as $id ) {
				$all_picks = array_merge( $all_picks, self::parse_manual( get_post_meta( $id, self::META_MANUAL, true ) ) );
			}
			_prime_post_caches( array_unique( $all_picks ), true, true );

			foreach ( $missing as $id ) {
				$ids = $this->get_manual_picks( $id );
				foreach ( isset( $scored[ $id ] ) ? $scored[ $id ] : array() as $candidate_id ) {
					if ( ! in_array( $candidate_id, $ids, true ) ) {
						$ids[] = $candidate_id;
					}
				}

				/** This filter is documented in the 2.3 engine: see readme.txt. */
				$ids = (array) apply_filters( 'acme_related_post_ids', $ids, $id );
				$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

				$this->lists[ $id ] = $ids;
				set_transient( 'acme_related_' . $id, $ids, HOUR_IN_SECONDS );
			}
		}

		// Prime everything the items of the whole batch need.
		$show    = max( $count, $this->settings->count() );
		$display = array();
		foreach ( $batch as $id ) {
			if ( isset( $this->lists[ $id ] ) ) {
				$display = array_merge( $display, array_slice( $this->lists[ $id ], 0, $show ) );
			}
		}
		Item::prime( array_unique( $display ) );
	}

	/**
	 * Cache key of a post's list.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function cache_key( $post_id ) {
		return 'list:' . $post_id . ':' . md5( $this->lists_salt );
	}

	/**
	 * Scored candidates of several posts, best first (same rules as always: at most
	 * MAX_CANDIDATES newest posts sharing a tag or category are looked at).
	 *
	 * @param int[] $post_ids Posts.
	 * @return array<int, int[]>
	 */
	private function score_candidates( array $post_ids ) {
		global $wpdb;

		// Terms of the posts (already primed by the query that fetched them, or just now).
		$source_terms = array();
		$all_tt_ids   = array();
		foreach ( $post_ids as $id ) {
			$tags = get_the_terms( $id, 'post_tag' );
			$cats = get_the_terms( $id, 'category' );
			$source_terms[ $id ] = array(
				'tags' => is_array( $tags ) ? wp_list_pluck( $tags, 'term_taxonomy_id' ) : array(),
				'cats' => is_array( $cats ) ? wp_list_pluck( $cats, 'term_taxonomy_id' ) : array(),
			);
			$all_tt_ids = array_merge( $all_tt_ids, $source_terms[ $id ]['tags'], $source_terms[ $id ]['cats'] );
		}
		$all_tt_ids = array_values( array_unique( array_map( 'intval', $all_tt_ids ) ) );
		if ( ! $all_tt_ids ) {
			return array();
		}

		// Every published post sharing any of these terms, with its matching terms.
		$placeholders = implode( ',', array_fill( 0, count( $all_tt_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tr.object_id, tr.term_taxonomy_id, p.post_date, p.post_date_gmt
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				WHERE tr.term_taxonomy_id IN ($placeholders)
				AND p.post_type = 'post' AND p.post_status = 'publish' AND p.post_password = ''",
				$all_tt_ids
			)
		);
		// phpcs:enable

		$candidates = array();
		foreach ( $rows as $row ) {
			$cid = (int) $row->object_id;
			if ( ! isset( $candidates[ $cid ] ) ) {
				$candidates[ $cid ] = array(
					'date'     => $row->post_date,
					'date_gmt' => $row->post_date_gmt,
					'tt'       => array(),
				);
			}
			$candidates[ $cid ]['tt'][] = (int) $row->term_taxonomy_id;
		}

		// All candidates with their meta (the "never show" flag) and terms: filters on
		// `acme_related_post_ids` typically call get_post()/has_category() for every ID.
		_prime_post_caches( array_keys( $candidates ), true, true );

		$result = array();
		foreach ( $post_ids as $id ) {
			$tags = array_map( 'intval', $source_terms[ $id ]['tags'] );
			$cats = array_map( 'intval', $source_terms[ $id ]['cats'] );
			$mine = array_merge( $tags, $cats );
			if ( ! $mine ) {
				$result[ $id ] = array();
				continue;
			}

			// Newest MAX_CANDIDATES posts sharing a term (like the WP_Query of 2.3: date DESC).
			$pool = array();
			foreach ( $candidates as $cid => $c ) {
				if ( $cid !== $id && array_intersect( $c['tt'], $mine ) ) {
					$pool[ $cid ] = $c;
				}
			}
			uksort(
				$pool,
				static function ( $a, $b ) use ( $pool ) {
					$cmp = strcmp( $pool[ $b ]['date'], $pool[ $a ]['date'] );
					return 0 !== $cmp ? $cmp : $b - $a;
				}
			);
			$pool = array_slice( $pool, 0, self::MAX_CANDIDATES, true );

			$scored = array();
			foreach ( $pool as $cid => $c ) {
				if ( self::is_excluded( $cid ) ) {
					continue;
				}
				$score = 2 * count( array_intersect( $tags, $c['tt'] ) ) + count( array_intersect( $cats, $c['tt'] ) );
				if ( $score < 1 ) {
					continue;
				}
				$scored[] = array(
					'id'    => $cid,
					'score' => $score,
					'date'  => $c['date_gmt'],
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
			$result[ $id ] = wp_list_pluck( $scored, 'id' );
		}
		return $result;
	}

	/**
	 * Whether this is a REST API request.
	 *
	 * @return bool
	 */
	private function is_rest() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
