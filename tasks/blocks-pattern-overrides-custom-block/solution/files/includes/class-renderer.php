<?php
/**
 * Server rendering of the CTA block.
 *
 * CTAs are rendered from their attributes rather than from the saved markup:
 * WordPress resolves pattern overrides into the attributes before the block
 * renders, so this is what makes per-page overrides, tracking of the link that
 * is actually shown, and sanitization of untrusted override values work for
 * every CTA on the site, whatever version saved it.
 *
 * @package Acme\CTA
 */

namespace Acme\CTA;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the CTA markup.
 */
class Renderer {

	/**
	 * Attributes that pages using a synced pattern may override.
	 *
	 * Everything else (variant, heading level, new tab, campaign) stays
	 * controlled by the pattern.
	 */
	const OVERRIDABLE_ATTRIBUTES = array( 'heading', 'buttonText', 'buttonUrl' );

	/**
	 * Protocols a CTA link may use (relative URLs are always fine).
	 */
	const ALLOWED_PROTOCOLS = array( 'http', 'https', 'mailto', 'tel' );

	/**
	 * Tracking component.
	 *
	 * @var Tracking
	 */
	private $tracking;

	/**
	 * Constructor.
	 *
	 * @param Tracking $tracking Tracking component.
	 */
	public function __construct( Tracking $tracking ) {
		$this->tracking = $tracking;
	}

	/**
	 * Inline formatting allowed in headings and button texts.
	 *
	 * @return array
	 */
	public static function allowed_inline_html() {
		return array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
		);
	}

	/**
	 * Sanitize rich text coming from attributes or overrides.
	 *
	 * @param string $text Raw rich text.
	 * @return string
	 */
	public static function sanitize_rich_text( $text ) {
		// Drop script/style contents entirely, then everything but bold/italic.
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		return trim( wp_kses( $text, self::allowed_inline_html() ) );
	}

	/**
	 * Sanitize a button URL. Returns '' for anything that must not be linked.
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function sanitize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( $scheme && ! in_array( strtolower( $scheme ), self::ALLOWED_PROTOCOLS, true ) ) {
			return '';
		}
		// A colon before the first slash that isn't a known scheme (e.g. "javascript :x").
		if ( ! $scheme && preg_match( '#^[^/?\#]*:#', $url ) ) {
			return '';
		}
		return esc_url_raw( $url, self::ALLOWED_PROTOCOLS );
	}

	/**
	 * Render callback.
	 *
	 * @param array          $attributes Block attributes (overrides already applied by WordPress).
	 * @param string         $content    Saved block markup.
	 * @param \WP_Block|null $block      Block instance.
	 * @return string
	 */
	public function render( $attributes, $content = '', $block = null ) {
		$raw = ( $block instanceof \WP_Block && isset( $block->parsed_block['attrs'] ) ) ? (array) $block->parsed_block['attrs'] : array();
		$cta = acme_cta_normalize_attributes( $this->with_legacy_attributes( (array) $attributes, $raw ) );

		$heading     = self::sanitize_rich_text( $cta['heading'] );
		$button_text = self::sanitize_rich_text( $cta['buttonText'] );
		$url         = self::sanitize_url( $cta['buttonUrl'] );

		$wrapper_extra = array( 'class' => 'is-variant-' . $cta['variant'] );
		$anchor        = $this->anchor_from_saved_markup( (string) $content );
		if ( '' !== $anchor ) {
			$wrapper_extra['id'] = $anchor;
		}

		$html = sprintf( '<div %s>', get_block_wrapper_attributes( $wrapper_extra ) );

		if ( '' !== $heading ) {
			$tag   = 'h' . $cta['headingLevel'];
			$html .= sprintf( '<%1$s class="wp-block-acme-cta__heading">%2$s</%1$s>', $tag, $heading );
		}

		$link = new \WP_HTML_Tag_Processor( '<a class="wp-block-acme-cta__button wp-element-button">' );
		$link->next_tag();
		if ( '' !== $url ) {
			$link->set_attribute( 'href', $this->tracking->is_enabled() ? $this->tracking->tracked_url( $url, $cta['campaign'] ) : $url );
			if ( $this->tracking->is_enabled() ) {
				$link->set_attribute( 'data-acme-cta', $cta['campaign'] ? $cta['campaign'] : 'cta' );
			}
		}
		if ( $cta['opensInNewTab'] ) {
			$link->set_attribute( 'target', '_blank' );
			$link->set_attribute( 'rel', 'noopener noreferrer' );
		}
		$html .= $link->get_updated_html() . $button_text . '</a>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * CTAs saved by 1.x store `title`, `label`, `url` and `color` in the block
	 * comment. The 2.x attribute defaults would hide them, so map them over
	 * whenever the stored block has no 2.x value.
	 *
	 * @param array $attributes Attributes (with 2.x defaults applied).
	 * @param array $raw        Attributes as stored in the block comment.
	 * @return array
	 */
	private function with_legacy_attributes( array $attributes, array $raw ) {
		$legacy = array(
			'title' => 'heading',
			'label' => 'buttonText',
			'url'   => 'buttonUrl',
		);
		$is_v1  = false;
		foreach ( $legacy as $old => $new ) {
			if ( isset( $raw[ $old ] ) && ! isset( $raw[ $new ] ) ) {
				$attributes[ $new ] = (string) $raw[ $old ];
				$is_v1              = true;
			}
		}
		if ( $is_v1 && ! isset( $raw['variant'] ) ) {
			$attributes['variant'] = acme_cta_variant_from_legacy_color( isset( $raw['color'] ) ? (string) $raw['color'] : 'blue' );
		}
		return $attributes;
	}

	/**
	 * The HTML anchor lives in the saved markup (not in the block comment).
	 *
	 * @param string $content Saved markup.
	 * @return string
	 */
	private function anchor_from_saved_markup( $content ) {
		if ( '' === $content ) {
			return '';
		}
		$processor = new \WP_HTML_Tag_Processor( $content );
		if ( ! $processor->next_tag() ) {
			return '';
		}
		$id = $processor->get_attribute( 'id' );
		return is_string( $id ) ? $id : '';
	}
}
