<?php
/**
 * Heading index of a post: every heading block, its page and its anchor.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Builds (and caches per request) the list of headings of a post.
 *
 * Anchors are assigned once for the whole post, across all of its pages, so
 * that the links in the table of contents and the ids printed on the headings
 * always agree, whichever page is being viewed.
 */
class Headings {

	/**
	 * Per-request cache, keyed by post ID + content hash.
	 *
	 * @var array<string, array>
	 */
	private static $cache = array();

	/**
	 * Headings of a post, in document order.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<int, array{page:int, index:int, level:int, text:string, anchor:string, custom:bool, ancestors:string[]}>
	 */
	public static function for_post( \WP_Post $post ) {
		$key = $post->ID . ':' . md5( (string) $post->post_content );
		if ( ! isset( self::$cache[ $key ] ) ) {
			self::$cache[ $key ] = self::build( (string) $post->post_content );
		}
		return self::$cache[ $key ];
	}

	/**
	 * Forget cached indexes (e.g. after the content changed within a request).
	 */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Split post content into pages exactly like WordPress does for <!--nextpage-->.
	 *
	 * @param string $content Raw post content.
	 * @return string[] Page contents (1-based page N is index N-1).
	 */
	public static function split_pages( $content ) {
		if ( false === strpos( $content, '<!--nextpage-->' ) ) {
			return array( $content );
		}
		$content = str_replace( "\n<!--nextpage-->\n", '<!--nextpage-->', $content );
		$content = str_replace( "\n<!--nextpage-->", '<!--nextpage-->', $content );
		$content = str_replace( "<!--nextpage-->\n", '<!--nextpage-->', $content );
		$content = str_replace( '<!-- wp:nextpage -->', '', $content );
		$content = str_replace( '<!-- /wp:nextpage -->', '', $content );
		if ( 0 === strpos( $content, '<!--nextpage-->' ) ) {
			$content = substr( $content, 15 );
		}
		return explode( '<!--nextpage-->', $content );
	}

	/**
	 * Build the heading index for raw post content.
	 *
	 * @param string $content Raw post content.
	 * @return array
	 */
	public static function build( $content ) {
		$headings = array();
		foreach ( self::split_pages( $content ) as $i => $page_content ) {
			self::walk( parse_blocks( $page_content ), $i + 1, array(), $headings );
		}

		// Existing anchors are never changed and can't be taken by generated ones.
		$used = array();
		foreach ( $headings as $heading ) {
			if ( '' !== $heading['anchor'] ) {
				$used[ $heading['anchor'] ] = true;
			}
		}
		foreach ( $headings as $i => $heading ) {
			if ( '' !== $heading['anchor'] || '' === $heading['text'] ) {
				continue;
			}
			$base   = self::slugify( $heading['text'] );
			$anchor = $base;
			$n      = 2;
			while ( isset( $used[ $anchor ] ) ) {
				$anchor = $base . '-' . $n;
				++$n;
			}
			$used[ $anchor ]           = true;
			$headings[ $i ]['anchor'] = $anchor;
		}

		foreach ( $headings as $i => $heading ) {
			$headings[ $i ]['index'] = $i;
		}
		return $headings;
	}

	/**
	 * Collect heading blocks recursively.
	 *
	 * @param array    $blocks    Parsed blocks.
	 * @param int      $page      Page number.
	 * @param string[] $ancestors Names of the ancestor blocks.
	 * @param array    $headings  Collected headings (by reference).
	 */
	private static function walk( array $blocks, $page, array $ancestors, array &$headings ) {
		foreach ( $blocks as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$headings[] = self::describe( $block, $page, $ancestors );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_ancestors = $block['blockName'] ? array_merge( $ancestors, array( $block['blockName'] ) ) : $ancestors;
				self::walk( $block['innerBlocks'], $page, $inner_ancestors, $headings );
			}
		}
	}

	/**
	 * Level, text and existing anchor of a heading block.
	 *
	 * @param array    $block     Parsed heading block.
	 * @param int      $page      Page number.
	 * @param string[] $ancestors Ancestor block names.
	 * @return array
	 */
	private static function describe( array $block, $page, array $ancestors ) {
		$html   = (string) $block['innerHTML'];
		$level  = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 0;
		$anchor = isset( $block['attrs']['anchor'] ) && is_string( $block['attrs']['anchor'] ) ? $block['attrs']['anchor'] : '';

		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			if ( preg_match( '/^H([1-6])$/', $tags->get_tag(), $m ) ) {
				if ( ! $level ) {
					$level = (int) $m[1];
				}
				$id = $tags->get_attribute( 'id' );
				if ( is_string( $id ) && '' !== trim( $id ) ) {
					$anchor = trim( $id );
				}
				break;
			}
		}

		return array(
			'page'      => $page,
			'index'     => 0,
			'level'     => $level ? $level : 2,
			'text'      => self::text( $html ),
			'anchor'    => $anchor,
			'custom'    => '' !== $anchor,
			'ancestors' => $ancestors,
		);
	}

	/**
	 * Plain text of heading HTML.
	 *
	 * @param string $html Heading HTML.
	 * @return string
	 */
	public static function text( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Anchor slug for a heading text (same scheme as the editor, see src/toc/utils.js).
	 *
	 * @param string $text Heading text.
	 * @return string
	 */
	public static function slugify( $text ) {
		$text = (string) $text;
		if ( class_exists( '\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_KD );
			if ( false !== $normalized ) {
				$text = preg_replace( '/[\x{0300}-\x{036f}]/u', '', $normalized );
			}
		} else {
			$text = remove_accents( $text );
		}
		$slug = strtolower( $text );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
		$slug = trim( $slug, '-' );
		return '' === $slug ? 'heading' : $slug;
	}

	/**
	 * Headings on a given page.
	 *
	 * @param array $headings Heading index.
	 * @param int   $page     Page number.
	 * @return array
	 */
	public static function on_page( array $headings, $page ) {
		return array_values(
			array_filter(
				$headings,
				static function ( $heading ) use ( $page ) {
					return (int) $heading['page'] === (int) $page;
				}
			)
		);
	}

	/**
	 * URL of page N of the current post.
	 *
	 * @param int $page Page number.
	 * @return string
	 */
	public static function page_url( $page ) {
		$link = _wp_link_page( (int) $page );
		$tags = new \WP_HTML_Tag_Processor( $link );
		if ( $tags->next_tag( 'a' ) ) {
			$href = $tags->get_attribute( 'href' );
			if ( is_string( $href ) ) {
				return $href;
			}
		}
		return (string) get_permalink();
	}
}
