<?php
/**
 * Lookup map of all published glossary terms.
 *
 * Building the map needs one query for all terms, so it is cached in a
 * transient. It is rebuilt when a term is saved.
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
