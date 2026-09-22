<?php
/**
 * Front-end markup for callouts (block – every saved format – and shortcode).
 *
 * @package Acme\Callouts
 */

namespace Acme\Callouts;

defined( 'ABSPATH' ) || exit;

/**
 * Produces the 2.0 markup:
 *
 *   <aside class="wp-block-acme-callout is-type-{type}" role="note" aria-label="{Type label}">
 *     <p class="wp-block-acme-callout__title">{title}</p>
 *     <div class="wp-block-acme-callout__body">{body}</div>
 *   </aside>
 */
class Renderer {

	/**
	 * Inline formatting allowed in titles.
	 *
	 * @var array
	 */
	const TITLE_TAGS = array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
	);

	/**
	 * Normalize a type slug to a registered type (falls back to "info").
	 *
	 * @param mixed $type Raw type.
	 * @return string
	 */
	public static function sanitize_type( $type ) {
		$types = acme_callouts_get_types();
		$type  = is_string( $type ) ? sanitize_key( $type ) : '';
		if ( isset( $types[ $type ] ) ) {
			return $type;
		}
		return isset( $types['info'] ) ? 'info' : (string) array_key_first( $types );
	}

	/**
	 * Render callout markup.
	 *
	 * @param string $type       Type slug (unsanitized).
	 * @param string $title_html Title HTML (inline formatting only).
	 * @param string $body_html  Body HTML (already safe).
	 * @param array  $wrapper    Wrapper attribute string (from get_block_wrapper_attributes) or ''.
	 * @param string $anchor     Optional HTML id.
	 * @return string
	 */
	public static function markup( $type, $title_html, $body_html, $wrapper = '', $anchor = '' ) {
		$type  = self::sanitize_type( $type );
		$types = acme_callouts_get_types();
		$label = $types[ $type ] ?? '';

		if ( '' === $wrapper ) {
			$wrapper = 'class="wp-block-acme-callout is-type-' . esc_attr( $type ) . '"';
		}
		$attrs = $wrapper . ' role="note" aria-label="' . esc_attr( $label ) . '"';
		if ( '' !== $anchor ) {
			$attrs = 'id="' . esc_attr( $anchor ) . '" ' . $attrs;
		}

		$title_html = trim( wp_kses( (string) $title_html, self::TITLE_TAGS ) );
		$html       = '<aside ' . $attrs . '>';
		if ( '' !== $title_html ) {
			$html .= '<p class="wp-block-acme-callout__title">' . $title_html . '</p>';
		}
		$html .= '<div class="wp-block-acme-callout__body">' . $body_html . '</div>';
		$html .= '</aside>';
		return $html;
	}

	/**
	 * Block render callback.
	 *
	 * @param array     $attributes Attributes.
	 * @param string    $content    Inner content.
	 * @param \WP_Block $block      Block.
	 * @return string
	 */
	public static function render_block( $attributes, $content, $block ) {
		$type   = self::sanitize_type( $attributes['type'] ?? 'info' );
		$title  = isset( $attributes['title'] ) && is_string( $attributes['title'] ) ? $attributes['title'] : '';
		$anchor = isset( $attributes['anchor'] ) && is_string( $attributes['anchor'] ) ? $attributes['anchor'] : '';
		$body   = (string) $content;

		// 1.x content: the saved HTML holds the title and text; there are no inner blocks.
		$is_legacy = ( ! $block instanceof \WP_Block || 0 === count( $block->inner_blocks ) ) && self::looks_legacy( $body );
		if ( $is_legacy ) {
			$legacy = self::parse_legacy( $body );
			$title  = $legacy['title'];
			$anchor = '' !== $legacy['anchor'] ? $legacy['anchor'] : $anchor;
			$body   = '<p>' . wp_kses_post( $legacy['content'] ) . '</p>';
		}

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'is-type-' . $type ) );
		// Any id coming from supports is replaced by our anchor handling.
		$wrapper = preg_replace( '/\s*\bid="[^"]*"/', '', $wrapper );

		return self::markup( $type, $title, $body, $wrapper, $anchor );
	}

	/**
	 * Does saved block HTML use one of the 1.x formats?
	 *
	 * @param string $html Saved HTML.
	 * @return bool
	 */
	public static function looks_legacy( $html ) {
		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag() ) {
			return false;
		}
		return 'DIV' === $processor->get_tag()
			&& ( $processor->has_class( 'acme-callout' ) || $processor->has_class( 'callout' ) );
	}

	/**
	 * Extract title, content and anchor from 1.x saved HTML.
	 *
	 * @param string $html Saved HTML.
	 * @return array{title: string, content: string, anchor: string}
	 */
	public static function parse_legacy( $html ) {
		$result = array(
			'title'   => '',
			'content' => '',
			'anchor'  => '',
		);

		$doc = new \DOMDocument();
		$old = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="acme-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $old );

		$root = $doc->getElementById( 'acme-root' );
		$box  = null;
		foreach ( $root ? $root->childNodes : array() as $node ) {
			if ( XML_ELEMENT_NODE === $node->nodeType ) {
				$box = $node;
				break;
			}
		}
		if ( ! $box ) {
			return $result;
		}

		$result['anchor'] = (string) $box->getAttribute( 'id' );
		$classes          = preg_split( '/\s+/', (string) $box->getAttribute( 'class' ) );

		foreach ( $box->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}
			$child_classes = preg_split( '/\s+/', (string) $child->getAttribute( 'class' ) );
			if ( in_array( 'acme-callout', $classes, true ) ) {
				// 1.3+: <p class="acme-callout__title"> + <div class="acme-callout__content">.
				if ( in_array( 'acme-callout__title', $child_classes, true ) ) {
					$result['title'] = self::inner_html( $child );
				} elseif ( in_array( 'acme-callout__content', $child_classes, true ) ) {
					$result['content'] = self::inner_html( $child );
				}
			} elseif ( 'strong' === $child->nodeName && '' === $result['title'] ) {
				// 1.0: <strong>title</strong><p>content</p>.
				$result['title'] = self::inner_html( $child );
			} elseif ( 'p' === $child->nodeName && '' === $result['content'] ) {
				$result['content'] = self::inner_html( $child );
			}
		}

		return $result;
	}

	/**
	 * Inner HTML of a DOM node.
	 *
	 * @param \DOMNode $node Node.
	 * @return string
	 */
	private static function inner_html( \DOMNode $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return $html;
	}

	/**
	 * Wrap shortcode body text in a paragraph unless it already has block-level markup.
	 *
	 * @param string $html Body HTML.
	 * @return string
	 */
	public static function wrap_text( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return '';
		}
		if ( preg_match( '/<(p|div|ul|ol|h[1-6]|blockquote|table|figure|pre)[\s>]/i', $html ) ) {
			return $html;
		}
		return '<p>' . $html . '</p>';
	}
}
