<?php
/**
 * Front-end rendering of the TOC block.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the table of contents from the current headings of the post.
 */
class Renderer {

	/**
	 * Titles that were the untouched default of their time. TOCs saved with
	 * one of these show the (translated) default title of the current version.
	 */
	const LEGACY_DEFAULT_TITLES = array( 'Table of contents', 'Contents' );

	/**
	 * Render callback.
	 *
	 * @param array          $attributes Block attributes.
	 * @param string         $content    Saved markup (only present in TOCs saved by 1.x).
	 * @param \WP_Block|null $block      Block instance.
	 * @return string
	 */
	public static function render( $attributes, $content = '', $block = null ) {
		$post_id = 0;
		if ( $block instanceof \WP_Block && ! empty( $block->context['postId'] ) ) {
			$post_id = (int) $block->context['postId'];
		}
		$post = get_post( $post_id ? $post_id : null );
		if ( ! $post ) {
			return '';
		}

		$min_level = clamp_level( $attributes['minLevel'] ?? 2, 2 );
		$max_level = clamp_level( $attributes['maxLevel'] ?? 3, 3 );
		if ( $min_level > $max_level ) {
			list( $min_level, $max_level ) = array( $max_level, $min_level );
		}
		$excluded = array_merge(
			excluded_blocks(),
			array_map( 'strval', (array) ( $attributes['excludedBlocks'] ?? array() ) )
		);

		$items = array();
		foreach ( Headings::for_post( $post ) as $heading ) {
			if ( '' === $heading['text'] || '' === $heading['anchor'] ) {
				continue;
			}
			if ( $heading['level'] < $min_level || $heading['level'] > $max_level ) {
				continue;
			}
			if ( array_intersect( $heading['ancestors'], $excluded ) ) {
				continue;
			}
			$items[] = $heading;
		}

		if ( ! $items ) {
			return '';
		}

		$title = self::title( $attributes, (string) $content );
		$label = '' !== $title ? wp_strip_all_tags( $title ) : __( 'Table of contents', 'acme-toc' );

		$current_page = self::current_page( $post );
		$list         = '';
		foreach ( $items as $item ) {
			$href  = (int) $item['page'] === $current_page ? '' : Headings::page_url( $item['page'] );
			$href .= '#' . $item['anchor'];
			$list .= sprintf(
				'<li class="acme-toc__item acme-toc__item--h%1$d"><a href="%2$s">%3$s</a></li>',
				(int) $item['level'],
				esc_url( $href ),
				esc_html( $item['text'] )
			);
		}

		$wrapper = get_block_wrapper_attributes( array( 'aria-label' => $label ) );

		return sprintf(
			'<nav %1$s>%2$s<ol class="acme-toc__list">%3$s</ol></nav>',
			$wrapper,
			'' !== $title ? '<p class="acme-toc__title">' . wp_kses( $title, self::title_tags() ) . '</p>' : '',
			$list
		);
	}

	/**
	 * The title to show ('' for none).
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Saved markup of a 1.x TOC.
	 * @return string
	 */
	public static function title( array $attributes, $content ) {
		if ( array_key_exists( 'title', $attributes ) && is_string( $attributes['title'] ) ) {
			$title = trim( $attributes['title'] );
			return self::is_default_title( $title ) ? __( 'Table of contents', 'acme-toc' ) : $title;
		}

		// TOCs saved by 1.x keep their title in the saved markup.
		if ( '' !== trim( $content ) ) {
			$tags = new \WP_HTML_Tag_Processor( $content );
			if ( ! $tags->next_tag( array( 'class_name' => 'acme-toc__title' ) ) ) {
				return '';
			}
			$tag = strtolower( $tags->get_tag() );
			if ( preg_match( '#<' . $tag . '\b[^>]*\bacme-toc__title\b[^>]*>(.*?)</' . $tag . '>#s', $content, $m ) ) {
				$title = trim( $m[1] );
				return self::is_default_title( $title ) ? __( 'Table of contents', 'acme-toc' ) : $title;
			}
			return '';
		}

		return __( 'Table of contents', 'acme-toc' );
	}

	/**
	 * Is this one of the (untranslated) default titles?
	 *
	 * @param string $title Title.
	 * @return bool
	 */
	private static function is_default_title( $title ) {
		return in_array( $title, self::LEGACY_DEFAULT_TITLES, true );
	}

	/**
	 * Inline tags allowed in a custom title.
	 *
	 * @return array
	 */
	private static function title_tags() {
		return array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'code'   => array(),
			'span'   => array( 'class' => true ),
		);
	}

	/**
	 * Page of the post that is being viewed.
	 *
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	private static function current_page( \WP_Post $post ) {
		global $page, $id;
		if ( ! empty( $page ) && (int) $id === (int) $post->ID ) {
			return max( 1, (int) $page );
		}
		return 1;
	}
}
