<?php
/**
 * Front-end rendering of glossary terms marked in post content.
 *
 * Stored markup (written by the editor's "Glossary term" format):
 *
 *     <span class="acme-glossary-term" data-term-id="42">caching</span>
 *
 * Rendered markup:
 *
 *     <span class="acme-glossary-term" data-term-id="42" tabindex="0" aria-describedby="acme-glossary-def-1">caching<span id="acme-glossary-def-1" class="acme-glossary-term__definition" role="tooltip">…</span></span>
 *
 * The definition is looked up when the page is rendered. Marks pointing to a
 * term that is missing or not published are rendered as their plain text.
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Renders glossary marks.
 */
class Renderer {

	/**
	 * Counter for unique tooltip IDs within the request.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		// After blocks (9), wpautop (10) and shortcodes (11).
		add_filter( 'the_content', array( __CLASS__, 'render_content' ), 20 );
	}

	/**
	 * A published glossary term by ID, or null.
	 *
	 * @param int $id Term ID.
	 * @return \WP_Post|null
	 */
	public static function published_term( $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$cached = Term_Cache::term( $id );
		return $cached ? get_post( $cached['id'] ) : null;
	}

	/**
	 * Markup of one term: the inner HTML plus an accessible tooltip with the definition.
	 *
	 * @param \WP_Post $term       Term.
	 * @param string   $inner_html Marked text (HTML, already safe).
	 * @return string
	 */
	public static function render_term( \WP_Post $term, $inner_html ) {
		$id         = 'acme-glossary-def-' . ( ++self::$counter );
		// Reuse the cached lookup map (one query per page).
		$cached     = Term_Cache::term( $term->ID );
		$definition = $cached ? $cached['definition'] : '';

		Assets::enqueue_front();

		return sprintf(
			'<span class="acme-glossary-term" data-term-id="%1$d" tabindex="0" aria-describedby="%2$s">%3$s<span id="%2$s" class="acme-glossary-term__definition" role="tooltip">%4$s</span></span>',
			$term->ID,
			esc_attr( $id ),
			$inner_html,
			esc_html( wp_strip_all_tags( $definition ) )
		);
	}

	/**
	 * Render all stored glossary marks in a piece of HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function render_content( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, 'acme-glossary-term' ) ) {
			return $html;
		}

		$marks = self::find_marks( $html );
		if ( ! $marks ) {
			return $html;
		}

		// Load all referenced terms (and their meta) at once.
		$ids = array_values( array_unique( array_filter( array_map( static fn( $m ) => $m['term_id'], $marks ) ) ) );
		if ( $ids ) {
			_prime_post_caches( $ids, false, true );
		}

		$out    = '';
		$offset = 0;
		foreach ( $marks as $mark ) {
			$out .= substr( $html, $offset, $mark['start'] - $offset );
			$term = self::published_term( $mark['term_id'] );
			// Render nested marks inside (unusual, but keep them working).
			$inner = self::render_content( $mark['inner'] );
			$out  .= $term ? self::render_term( $term, $inner ) : $inner;
			$offset = $mark['end'];
		}
		return $out . substr( $html, $offset );
	}

	/**
	 * Find top-level stored marks: <span …class="…acme-glossary-term…" data-term-id="…">…</span>.
	 *
	 * Already rendered marks (with a tooltip) are skipped.
	 *
	 * @param string $html HTML.
	 * @return array[] Each: start, end (byte offsets of the whole element), inner (HTML), term_id.
	 */
	private static function find_marks( $html ) {
		$marks = array();
		$pos   = 0;
		while ( preg_match( '/<span\b([^>]*)>/i', $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			$start = $m[0][1];
			$attrs = $m[1][0];
			$after = $start + strlen( $m[0][0] );
			if ( ! preg_match( '/\bclass\s*=\s*(["\'])(?:(?!\1).)*\bacme-glossary-term\b(?!__)(?:(?!\1).)*\1/i', $attrs ) || preg_match( '/\baria-describedby\s*=/i', $attrs ) ) {
				$pos = $after;
				continue;
			}
			$close = self::matching_close( $html, $after );
			if ( null === $close ) {
				break;
			}
			$term_id = 0;
			if ( preg_match( '/\bdata-term-id\s*=\s*(["\'])\s*(\d+)\s*\1/i', $attrs, $id_match ) ) {
				$term_id = (int) $id_match[2];
			}
			$marks[] = array(
				'start'   => $start,
				'end'     => $close + strlen( '</span>' ),
				'inner'   => substr( $html, $after, $close - $after ),
				'term_id' => $term_id,
			);
			$pos     = $close + strlen( '</span>' );
		}
		return $marks;
	}

	/**
	 * Offset of the </span> closing the span opened just before $offset (null if none).
	 *
	 * @param string $html   HTML.
	 * @param int    $offset Offset after the opening tag.
	 * @return int|null
	 */
	private static function matching_close( $html, $offset ) {
		$depth = 1;
		while ( preg_match( '#<(/?)span\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			$depth += '/' === $m[1][0] ? -1 : 1;
			if ( 0 === $depth ) {
				return $m[0][1];
			}
			$offset = $m[0][1] + strlen( $m[0][0] );
		}
		return null;
	}
}
