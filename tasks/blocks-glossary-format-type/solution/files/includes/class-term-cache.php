<?php
/**
 * Lookup map of all published glossary terms.
 *
 * Building the map needs one query for all terms, so it is cached in a
 * transient. It is rebuilt whenever a term (or its short definition) changes,
 * is trashed, deleted or changes its status.
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Cached glossary lookup map.
 */
class Term_Cache {

	const TRANSIENT = 'acme_glossary_map';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'save_post_' . ACME_GLOSSARY_POST_TYPE, array( __CLASS__, 'flush' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_change' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_change' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_change' ) );
		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_meta_change' ), 10, 3 );
		}
	}

	/**
	 * Flush when a term changes its status (published, unpublished, trashed…).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_status_change( $new_status, $old_status, $post ) {
		if ( $post instanceof \WP_Post && ACME_GLOSSARY_POST_TYPE === $post->post_type ) {
			self::flush();
		}
	}

	/**
	 * Flush when a term is deleted or (un)trashed.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post (deleted_post only).
	 */
	public static function on_post_change( $post_id, $post = null ) {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( ! $post || ACME_GLOSSARY_POST_TYPE === $post->post_type ) {
			self::flush();
		}
	}

	/**
	 * Flush when the short definition of a term changes.
	 *
	 * @param int|int[] $meta_ids  Meta ID(s).
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 */
	public static function on_meta_change( $meta_ids, $object_id, $meta_key ) {
		if ( ACME_GLOSSARY_SHORT_META === $meta_key && ACME_GLOSSARY_POST_TYPE === get_post_type( $object_id ) ) {
			self::flush();
		}
	}

	/**
	 * Forget the cached map.
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * The lookup map.
	 *
	 * @return array{slugs: array<string,int>, titles: array<string,int>, terms: array<int, array{id:int, slug:string, title:string, definition:string, url:string}>}
	 */
	public static function map() {
		$map = get_transient( self::TRANSIENT );
		if ( is_array( $map ) && isset( $map['terms'] ) ) {
			return $map;
		}

		$map   = array(
			'slugs'  => array(),
			'titles' => array(),
			'terms'  => array(),
		);
		$posts = get_posts(
			array(
				'post_type'        => ACME_GLOSSARY_POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $post ) {
			$title                               = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
			$map['slugs'][ $post->post_name ]    = $post->ID;
			$map['titles'][ self::lower( $title ) ] = $post->ID;
			$map['terms'][ $post->ID ]           = array(
				'id'         => $post->ID,
				'slug'       => $post->post_name,
				'title'      => $title,
				'definition' => acme_glossary_get_definition( $post ),
				'url'        => get_permalink( $post ),
			);
		}

		set_transient( self::TRANSIENT, $map, DAY_IN_SECONDS );
		return $map;
	}

	/**
	 * Cached data of one term.
	 *
	 * @param int $id Term ID.
	 * @return array|null
	 */
	public static function term( $id ) {
		$map = self::map();
		return isset( $map['terms'][ $id ] ) ? $map['terms'][ $id ] : null;
	}

	/**
	 * Lowercase a title for lookups.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function lower( $title ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title ) : strtolower( $title );
	}
}
