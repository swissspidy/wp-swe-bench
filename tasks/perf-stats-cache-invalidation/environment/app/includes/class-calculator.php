<?php
/**
 * Computes the numbers. This is the expensive part: it walks every published post.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Stats calculator.
 *
 * What is counted (unchanged since 1.0):
 * - published posts of type `post` (pages, drafts, scheduled, private and trashed posts don't count);
 * - for an author scope, only that author's published posts;
 * - comments of any type on those posts: approved, pending (awaiting moderation) and spam;
 * - words: str_word_count() of the content without HTML;
 * - months: the post's local publication date (site time zone), as YYYY-MM.
 */
class Calculator {

	/**
	 * Compute the stats of a scope.
	 *
	 * @param Scope $scope Scope.
	 * @return array
	 */
	public function compute( Scope $scope ) {
		/**
		 * Fires before the stats of a scope are computed (our monitoring counts these).
		 *
		 * @param string $scope Scope key ('site' or 'author:<id>').
		 */
		do_action( 'acme_stats_before_compute', $scope->key() );

		$args = array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		);
		if ( ! $scope->is_site() ) {
			$args['author'] = $scope->author_id;
		}
		$posts = get_posts( $args );

		$by_author   = array();
		$by_category = array();
		$by_month    = array();
		$words_total = 0;
		$longest     = null;

		foreach ( $posts as $post ) {
			$author_id               = (int) $post->post_author;
			$by_author[ $author_id ] = ( isset( $by_author[ $author_id ] ) ? $by_author[ $author_id ] : 0 ) + 1;

			$month              = mysql2date( 'Y-m', $post->post_date, false );
			$by_month[ $month ] = ( isset( $by_month[ $month ] ) ? $by_month[ $month ] : 0 ) + 1;

			foreach ( get_the_category( $post->ID ) as $category ) {
				$term_id = (int) $category->term_id;
				if ( ! isset( $by_category[ $term_id ] ) ) {
					$by_category[ $term_id ] = array(
						'id'    => $term_id,
						'name'  => $category->name,
						'slug'  => $category->slug,
						'count' => 0,
					);
				}
				++$by_category[ $term_id ]['count'];
			}

			$words        = self::count_words( $post->post_content );
			$words_total += $words;
			if ( null === $longest || $words > $longest['words'] ) {
				$longest = array(
					'id'    => (int) $post->ID,
					'title' => get_the_title( $post ),
					'words' => $words,
				);
			}
		}

		$authors = array();
		foreach ( $by_author as $author_id => $count ) {
			$authors[] = array(
				'id'    => (int) $author_id,
				'name'  => (string) get_the_author_meta( 'display_name', $author_id ),
				'count' => $count,
			);
		}
		usort( $authors, array( __CLASS__, 'by_count_then_name' ) );

		$categories = array_values( $by_category );
		usort( $categories, array( __CLASS__, 'by_count_then_name' ) );

		ksort( $by_month );

		$stats = array(
			'posts'    => array(
				'total'       => count( $posts ),
				'by_author'   => $authors,
				'by_category' => $categories,
				'by_month'    => $by_month,
			),
			'words'    => array(
				'total'   => $words_total,
				'average' => $posts ? (int) round( $words_total / count( $posts ) ) : 0,
				'longest' => $longest,
			),
			'comments' => $this->comments( $posts ),
		);

		/**
		 * Filters freshly computed stats.
		 *
		 * @param array  $stats Stats.
		 * @param string $scope Scope key.
		 */
		return apply_filters( 'acme_stats_computed', $stats, $scope->key() );
	}

	/**
	 * Comment numbers for a set of posts.
	 *
	 * @param \WP_Post[] $posts Posts.
	 * @return array
	 */
	private function comments( array $posts ) {
		$out = array(
			'approved'  => 0,
			'pending'   => 0,
			'spam'      => 0,
			'top_posts' => array(),
		);
		if ( ! $posts ) {
			return $out;
		}
		$ids = wp_list_pluck( $posts, 'ID' );
		foreach ( array(
			'approved' => 'approve',
			'pending'  => 'hold',
			'spam'     => 'spam',
		) as $key => $status ) {
			$out[ $key ] = (int) get_comments(
				array(
					'post__in' => $ids,
					'status'   => $status,
					'count'    => true,
				)
			);
		}

		$commented = array_filter(
			$posts,
			static function ( $post ) {
				return (int) $post->comment_count > 0;
			}
		);
		usort(
			$commented,
			static function ( $a, $b ) {
				if ( (int) $a->comment_count !== (int) $b->comment_count ) {
					return (int) $b->comment_count - (int) $a->comment_count;
				}
				return (int) $b->ID - (int) $a->ID;
			}
		);
		$settings = Plugin::settings();
		foreach ( array_slice( $commented, 0, max( 1, (int) $settings['top_posts'] ) ) as $post ) {
			$out['top_posts'][] = array(
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ),
				'count' => (int) $post->comment_count,
			);
		}
		return $out;
	}

	/**
	 * Words in a piece of content.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public static function count_words( $content ) {
		return str_word_count( wp_strip_all_tags( (string) $content ) );
	}

	/**
	 * Sort callback: count descending, then name ascending.
	 *
	 * @param array $a Row.
	 * @param array $b Row.
	 * @return int
	 */
	public static function by_count_then_name( $a, $b ) {
		if ( $a['count'] !== $b['count'] ) {
			return $b['count'] - $a['count'];
		}
		return strcasecmp( $a['name'], $b['name'] );
	}
}
