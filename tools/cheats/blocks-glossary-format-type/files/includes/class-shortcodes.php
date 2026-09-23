<?php
/**
 * Shortcodes: [glossary] (inline term with tooltip) and [glossary_index].
 *
 * [glossary term="cdn"]edge caching[/glossary]   – term by slug or title
 * [glossary id="42"]time to first byte[/glossary] – term by ID
 * [glossary]Cache[/glossary]                       – the text itself is the term
 *
 * @package Acme\Glossary
 */

namespace Acme\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Glossary shortcodes.
 */
class Shortcodes {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the shortcodes.
	 */
	public function register() {
		add_shortcode( 'glossary', array( $this, 'term' ) );
		add_shortcode( 'glossary_index', array( $this, 'index' ) );
	}

	/**
	 * Resolve the term a [glossary] shortcode refers to.
	 *
	 * @param array  $atts    Shortcode attributes (id, term).
	 * @param string $content Enclosed text.
	 * @return \WP_Post|null
	 */
	public static function resolve( $atts, $content ) {
		if ( ! empty( $atts['id'] ) ) {
			return acme_glossary_find_term( (int) $atts['id'] );
		}
		$ref = ! empty( $atts['term'] ) ? $atts['term'] : $content;
		return acme_glossary_find_term( (string) $ref );
	}

	/**
	 * [glossary] – the enclosed text with the term's definition as a tooltip (same markup as terms marked in the editor).
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Enclosed text.
	 * @return string
	 */
	public function term( $atts, $content = null ) {
		$atts    = shortcode_atts(
			array(
				'id'   => 0,
				'term' => '',
			),
			$atts,
			'glossary'
		);
		$content = do_shortcode( (string) $content );
		$term    = self::resolve( $atts, wp_strip_all_tags( $content ) );
		$term    = $term ? Renderer::published_term( $term->ID ) : null;
		if ( ! $term ) {
			return $content;
		}

		return Renderer::render_term( $term, $content );
	}

	/**
	 * [glossary_index] – all terms, A–Z.
	 *
	 * @param array|string $atts Attributes (letters="yes|no").
	 * @return string
	 */
	public function index( $atts = array() ) {
		$atts = shortcode_atts( array( 'letters' => 'yes' ), $atts, 'glossary_index' );
		return self::render_index( 'no' !== $atts['letters'] );
	}

	/**
	 * Render the A–Z index (also used by the Glossary index block).
	 *
	 * @param bool $letters Show letter headings.
	 * @return string
	 */
	public static function render_index( $letters = true ) {
		$terms = Term_Cache::map()['terms'];
		if ( ! $terms ) {
			return '<p class="acme-glossary-index acme-glossary-index--empty">' . esc_html__( 'The glossary is empty.', 'acme-glossary' ) . '</p>';
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		$html    = '<div class="acme-glossary-index">';
		$current = null;
		$open    = false;
		foreach ( $terms as $term ) {
			$letter = strtoupper( remove_accents( mb_substr( $term['title'], 0, 1 ) ) );
			if ( $letters && $letter !== $current ) {
				if ( $open ) {
					$html .= '</dl>';
				}
				$html   .= '<h3 class="acme-glossary-index__letter">' . esc_html( $letter ) . '</h3><dl>';
				$open    = true;
				$current = $letter;
			} elseif ( ! $open ) {
				$html .= '<dl>';
				$open  = true;
			}
			$html .= sprintf(
				'<dt id="glossary-%1$s"><a href="%2$s">%3$s</a></dt><dd>%4$s</dd>',
				esc_attr( $term['slug'] ),
				esc_url( $term['url'] ),
				esc_html( $term['title'] ),
				esc_html( $term['definition'] )
			);
		}
		if ( $open ) {
			$html .= '</dl>';
		}
		return $html . '</div>';
	}
}
