<?php
/**
 * Starts a new cache generation whenever something the stats count changes, and only then.
 *
 * Counted: published posts of type `post` (their author, date, content, title,
 * categories), comments on them, category names, author names, the plugin settings.
 * Not counted (and therefore ignored here): drafts, pending/scheduled/private posts while
 * they stay unpublished, revisions and autosaves, pages, post meta such as the edit lock.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Cache invalidation.
 */
class Invalidator {

	/** @var Cache */
	private $cache;

	/** @var bool Invalidated during this request already. */
	private $done = false;

	/**
	 * Constructor.
	 *
	 * @param Cache $cache Cache.
	 */
	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		// Posts.
		add_action( 'transition_post_status', array( $this, 'post_status' ), 10, 3 );
		add_action( 'post_updated', array( $this, 'post_updated' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'deleted_post' ), 10, 2 );

		// Comments.
		add_action( 'wp_insert_comment', array( $this, 'comment_changed' ), 10, 2 );
		add_action( 'transition_comment_status', array( $this, 'comment_status' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'comment_changed' ), 10, 2 );
		add_action( 'deleted_comment', array( $this, 'comment_changed' ), 10, 2 );

		// Categories.
		add_action( 'set_object_terms', array( $this, 'object_terms' ), 10, 6 );
		add_action( 'edited_term', array( $this, 'term_changed' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'term_changed' ), 10, 3 );

		// Authors.
		add_action( 'profile_update', array( $this, 'profile_update' ), 10, 3 );
		add_action( 'deleted_user', array( $this, 'flush' ) );

		// Settings ("most discussed" size).
		add_action( 'update_option_acme_stats_settings', array( $this, 'flush' ) );
	}

	/**
	 * Invalidate (once per request is enough: the new generation covers all later changes
	 * of this request as long as nothing was computed in between).
	 */
	public function flush() {
		$this->cache->invalidate();
	}

	/**
	 * Whether a post counts (or counted) for the stats.
	 *
	 * @param \WP_Post|null $post   Post.
	 * @param string|null   $status Status to check instead of the post's.
	 * @return bool
	 */
	private function counts( $post, $status = null ) {
		return $post instanceof \WP_Post && 'post' === $post->post_type && 'publish' === ( null === $status ? $post->post_status : $status );
	}

	/**
	 * Publishing, unpublishing, trashing, scheduled posts going live.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function post_status( $new_status, $old_status, $post ) {
		if ( ( $this->counts( $post, $new_status ) || $this->counts( $post, $old_status ) ) && $new_status !== $old_status ) {
			$this->flush();
		}
	}

	/**
	 * Edits of a published post (content, title, date, author).
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Post after.
	 * @param \WP_Post $post_before Post before.
	 */
	public function post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->counts( $post_after ) && ! $this->counts( $post_before ) ) {
			return;
		}
		foreach ( array( 'post_content', 'post_title', 'post_date', 'post_author', 'post_status' ) as $field ) {
			if ( (string) $post_after->$field !== (string) $post_before->$field ) {
				$this->flush();
				return;
			}
		}
	}

	/**
	 * Deleting a published post without trashing it first.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post.
	 */
	public function deleted_post( $post_id, $post = null ) {
		if ( $this->counts( $post ) ) {
			$this->flush();
		}
	}

	/**
	 * Comment added, edited or deleted.
	 *
	 * @param int                   $comment_id Comment ID.
	 * @param \WP_Comment|array|null $comment    Comment.
	 */
	public function comment_changed( $comment_id, $comment = null ) {
		$comment = get_comment( $comment instanceof \WP_Comment ? $comment : $comment_id );
		if ( ! $comment || $this->counts( get_post( (int) $comment->comment_post_ID ) ) ) {
			$this->flush();
		}
	}

	/**
	 * Comment approved, unapproved, spammed, trashed…
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    Comment.
	 */
	public function comment_status( $new_status, $old_status, $comment ) {
		if ( $new_status !== $old_status && $this->counts( get_post( (int) $comment->comment_post_ID ) ) ) {
			$this->flush();
		}
	}

	/**
	 * Categories of a published post changed.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Terms.
	 * @param array  $tt_ids     New term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Append.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 */
	public function object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( 'category' !== $taxonomy || ! $this->counts( get_post( $object_id ) ) ) {
			return;
		}
		$new = array_map( 'intval', (array) $tt_ids );
		$old = array_map( 'intval', (array) $old_tt_ids );
		sort( $new );
		sort( $old );
		if ( $new !== $old ) {
			$this->flush();
		}
	}

	/**
	 * Category renamed or deleted.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public function term_changed( $term_id, $tt_id, $taxonomy ) {
		if ( 'category' === $taxonomy ) {
			$this->flush();
		}
	}

	/**
	 * Author renamed.
	 *
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data User before the update.
	 * @param array    $userdata      New data.
	 */
	public function profile_update( $user_id, $old_user_data, $userdata = array() ) {
		$user = get_userdata( $user_id );
		if ( $user && $old_user_data instanceof \WP_User && $user->display_name !== $old_user_data->display_name ) {
			$this->flush();
		}
	}
}
