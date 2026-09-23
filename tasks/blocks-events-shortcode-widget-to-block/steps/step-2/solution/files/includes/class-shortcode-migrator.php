<?php
/**
 * Converts [acme_events] shortcodes in block content to acme/upcoming-events blocks.
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites Shortcode blocks that contain nothing but [acme_events] shortcodes into
 * Upcoming Events blocks with equivalent attributes. Everything else is left alone
 * (inline shortcodes, classic content, escaped [[acme_events]]); the shortcode keeps
 * rendering those.
 */
class Shortcode_Migrator {

	/**
	 * Migrate one post content.
	 *
	 * @param string $content Post content.
	 * @return array{0:string,1:int} New content and number of converted shortcodes.
	 */
	public function migrate_content( $content ) {
		if ( false === strpos( $content, '[' . Shortcode::TAG ) || ! has_blocks( $content ) ) {
			return array( $content, 0 );
		}
		$count  = 0;
		$blocks = $this->migrate_blocks( parse_blocks( $content ), $count );
		if ( 0 === $count ) {
			return array( $content, 0 );
		}
		return array( serialize_blocks( $blocks ), $count );
	}

	/**
	 * Walk a list of parsed blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param int   $count  Converted shortcodes (by reference).
	 * @return array
	 */
	private function migrate_blocks( array $blocks, &$count ) {
		$out = array();
		foreach ( $blocks as $block ) {
			$replacement = $this->replacement_for( $block );
			if ( null !== $replacement ) {
				$count += count( $replacement );
				foreach ( $replacement as $i => $new_block ) {
					if ( $i > 0 ) {
						$out[] = $this->separator();
					}
					$out[] = $new_block;
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block = $this->migrate_inner_blocks( $block, $count );
			}
			$out[] = $block;
		}
		return $out;
	}

	/**
	 * Walk the inner blocks of a block, keeping innerContent in sync.
	 *
	 * @param array $block Parsed block.
	 * @param int   $count Converted shortcodes (by reference).
	 * @return array
	 */
	private function migrate_inner_blocks( array $block, &$count ) {
		$inner         = array();
		$inner_content = array();
		$index         = 0;
		foreach ( $block['innerContent'] as $chunk ) {
			if ( is_string( $chunk ) ) {
				$inner_content[] = $chunk;
				continue;
			}
			$child       = $block['innerBlocks'][ $index++ ];
			$replacement = $this->replacement_for( $child );
			if ( null === $replacement ) {
				if ( ! empty( $child['innerBlocks'] ) ) {
					$child = $this->migrate_inner_blocks( $child, $count );
				}
				$inner[]         = $child;
				$inner_content[] = null;
				continue;
			}
			$count += count( $replacement );
			foreach ( $replacement as $i => $new_block ) {
				if ( $i > 0 ) {
					$inner_content[] = "\n\n";
				}
				$inner[]         = $new_block;
				$inner_content[] = null;
			}
		}
		$block['innerBlocks']  = $inner;
		$block['innerContent'] = $inner_content;
		return $block;
	}

	/**
	 * Upcoming Events blocks replacing a Shortcode block, or null if it can't be converted.
	 *
	 * @param array $block Parsed block.
	 * @return array[]|null
	 */
	private function replacement_for( array $block ) {
		if ( 'core/shortcode' !== ( $block['blockName'] ?? null ) ) {
			return null;
		}
		$text = (string) ( $block['innerHTML'] ?? '' );
		if ( ! preg_match_all( '/' . get_shortcode_regex( array( Shortcode::TAG ) ) . '/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$blocks = array();
		$offset = 0;
		foreach ( $matches as $m ) {
			// Anything besides whitespace around the shortcodes, or an escaped [[shortcode]]: leave it.
			if ( '' !== trim( substr( $text, $offset, $m[0][1] - $offset ) ) ) {
				return null;
			}
			if ( '[' === $m[1][0] && ']' === $m[6][0] ) {
				return null;
			}
			if ( '' !== $m[5][0] ) {
				return null; // Enclosing form [acme_events]…[/acme_events] is not a listing we know.
			}
			$atts     = shortcode_parse_atts( $m[3][0] );
			$options  = Listing::from_shortcode_atts( is_array( $atts ) ? $atts : array() );
			$blocks[] = array(
				'blockName'    => 'acme/upcoming-events',
				'attrs'        => Listing::to_block_attributes( $options ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
			$offset   = $m[0][1] + strlen( $m[0][0] );
		}
		if ( '' !== trim( substr( $text, $offset ) ) ) {
			return null;
		}
		return $blocks;
	}

	/**
	 * Whitespace between two top-level blocks.
	 *
	 * @return array
	 */
	private function separator() {
		return array(
			'blockName'    => null,
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => "\n\n",
			'innerContent' => array( "\n\n" ),
		);
	}
}
