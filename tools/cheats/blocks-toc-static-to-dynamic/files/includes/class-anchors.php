<?php
/**
 * Prints the computed anchors on headings that don't have one.
 *
 * @package Acme\Toc
 */

namespace Acme\Toc;

defined( 'ABSPATH' ) || exit;

/**
 * Adds ids to heading blocks in posts that contain a table of contents.
 */
class Anchors {

	/**
	 * Hook into content rendering (before blocks are rendered).
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'add_anchors' ), 8 );
	}

	/**
	 * Inject anchors into the heading blocks of the current page of the post.
	 *
	 * @param string $content Content of the current page (block markup).
	 * @return string
	 */
	public function add_anchors( $content ) {
		$post = get_post();
		if ( ! $post || ! is_string( $content ) || false === strpos( $content, '<!-- wp:heading' ) ) {
			return $content;
		}
		if ( ! has_block( 'acme/toc', $post ) ) {
			return $content;
		}

		$index  = Headings::for_post( $post );
		$blocks = parse_blocks( $content );

		$local = array();
		$this->collect( $blocks, $local );
		if ( ! $local ) {
			return $content;
		}

		$page     = $this->find_page( $index, $local );
		$expected = null === $page ? array() : Headings::on_page( $index, $page );
		if ( count( $expected ) !== count( $local ) ) {
			return $content;
		}

		$i       = 0;
		$changed = false;
		$blocks  = $this->apply( $blocks, $expected, $i, $changed );
		return $changed ? serialize_blocks( $blocks ) : $content;
	}

	/**
	 * Texts of the heading blocks in document order.
	 *
	 * @param array    $blocks Parsed blocks.
	 * @param string[] $out    Collected texts.
	 */
	private function collect( array $blocks, array &$out ) {
		foreach ( $blocks as $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$out[] = Headings::text( $block['innerHTML'] );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->collect( $block['innerBlocks'], $out );
			}
		}
	}

	/**
	 * Which page of the post the content belongs to.
	 *
	 * @param array    $index Heading index of the post.
	 * @param string[] $texts Heading texts found in the content.
	 * @return int|null
	 */
	private function find_page( array $index, array $texts ) {
		global $page;
		$candidates = array();
		if ( ! empty( $page ) ) {
			$candidates[] = (int) $page;
		}
		foreach ( $index as $heading ) {
			$candidates[] = (int) $heading['page'];
		}
		foreach ( array_unique( $candidates ) as $candidate ) {
			$on_page = wp_list_pluck( Headings::on_page( $index, $candidate ), 'text' );
			if ( $on_page === $texts ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * Set ids on heading blocks that don't have one.
	 *
	 * @param array $blocks   Parsed blocks.
	 * @param array $expected Headings of this page, in order.
	 * @param int   $i        Running heading counter.
	 * @param bool  $changed  Whether anything changed.
	 * @return array
	 */
	private function apply( array $blocks, array $expected, &$i, &$changed ) {
		foreach ( $blocks as $k => $block ) {
			if ( 'core/heading' === $block['blockName'] ) {
				$heading = $expected[ $i ] ?? null;
				++$i;
				if ( $heading && ! $heading['custom'] && '' !== $heading['anchor'] ) {
					$html = $this->with_id( $block['innerHTML'], $heading['anchor'] );
					if ( $html !== $block['innerHTML'] ) {
						foreach ( $block['innerContent'] as $c => $chunk ) {
							if ( $chunk === $block['innerHTML'] ) {
								$block['innerContent'][ $c ] = $html;
							}
						}
						$block['innerHTML'] = $html;
						$blocks[ $k ]       = $block;
						$changed            = true;
					}
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $k ]['innerBlocks'] = $this->apply( $block['innerBlocks'], $expected, $i, $changed );
			}
		}
		return $blocks;
	}

	/**
	 * Add an id to the first h1–h6 tag of some HTML unless it has one.
	 *
	 * @param string $html   Heading HTML.
	 * @param string $anchor Id.
	 * @return string
	 */
	private function with_id( $html, $anchor ) {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			if ( preg_match( '/^H[1-6]$/', $tags->get_tag() ) ) {
				if ( null === $tags->get_attribute( 'id' ) || '' === trim( (string) $tags->get_attribute( 'id' ) ) ) {
					$tags->set_attribute( 'id', $anchor );
				}
				return $tags->get_updated_html();
			}
		}
		return $html;
	}
}
