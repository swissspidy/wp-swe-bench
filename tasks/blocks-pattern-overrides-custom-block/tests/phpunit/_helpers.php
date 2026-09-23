<?php
/**
 * Helpers for the CTA tests: render content and inspect CTA markup.
 */

namespace WPSB\CTA;

/** Render a seeded post's content through the_content, as the front end would. */
function render_slug( string $slug, string $post_type = 'post' ): string {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	return render_post( $post );
}

function render_post( \WP_Post $post ): string {
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', $post->post_content );
	wp_reset_postdata();
	return (string) $html;
}

function pattern_id( string $slug ): int {
	$post = get_page_by_path( $slug, OBJECT, 'wp_block' );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded pattern '$slug' not found" );
	}
	return (int) $post->ID;
}

/** Markup of a synced pattern instance with optional overrides. */
function instance( int $ref, ?array $content = null ): string {
	$attrs = array( 'ref' => $ref );
	if ( null !== $content ) {
		$attrs['content'] = $content;
	}
	return serialize_block(
		array(
			'blockName'    => 'core/block',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerContent' => array(),
		)
	);
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function has_class_xpath( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
}

function inner_html( \DOMNode $node ): string {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}
	return $html;
}

/**
 * All rendered CTAs in document order.
 *
 * @return array<int, array{classes:string[], attrs:array<string,string>, heading_tag:?string, heading:?string, heading_html:?string, button:?string, button_html:?string, link:array<string,string>|null}>
 */
function ctas( string $html, bool $legacy_fallback = false ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[' . has_class_xpath( 'wp-block-acme-cta' ) . ']' ) as $node ) {
		if ( $legacy_fallback && ! $node->getAttribute( 'class' ) ) {
			continue;
		}
		$attrs = array();
		foreach ( $node->attributes as $a ) {
			$attrs[ $a->name ] = $a->value;
		}
		$heading = $xpath->query( './/*[' . has_class_xpath( 'wp-block-acme-cta__heading' ) . ']', $node )->item( 0 );
		$button  = $xpath->query( './/a[' . has_class_xpath( 'wp-block-acme-cta__button' ) . ']', $node )->item( 0 );
		if ( $legacy_fallback ) {
			// 1.x markup (only used for checks that don't care about the markup version).
			$heading = $heading ? $heading : $xpath->query( './/h2|.//h3|.//h4', $node )->item( 0 );
			$button  = $button ? $button : $xpath->query( './/a', $node )->item( 0 );
		}
		$link    = null;
		if ( $button ) {
			$link = array();
			foreach ( $button->attributes as $a ) {
				$link[ $a->name ] = $a->value;
			}
		}
		$out[] = array(
			'classes'      => preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ),
			'attrs'        => $attrs,
			'heading_tag'  => $heading ? strtolower( $heading->nodeName ) : null,
			'heading'      => $heading ? squish( $heading->textContent ) : null,
			'heading_html' => $heading ? inner_html( $heading ) : null,
			'button'       => $button ? squish( $button->textContent ) : null,
			'button_html'  => $button ? inner_html( $button ) : null,
			'link'         => $link,
		);
	}
	return $out;
}

/** Split a URL into [base, query args]. */
function split_url( string $url ): array {
	$parts = explode( '?', $url, 2 );
	$args  = array();
	if ( isset( $parts[1] ) ) {
		parse_str( $parts[1], $args );
	}
	return array( $parts[0], $args );
}

function squish( string $s ): string {
	return trim( preg_replace( '/\s+/', ' ', $s ) );
}
