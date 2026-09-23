<?php
/**
 * Helpers for the TOC tests: render content and inspect TOC / heading markup.
 */

namespace WPSB\Toc;

function post_by_slug( string $slug, string $post_type = 'post' ): \WP_Post {
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post ) {
		throw new \RuntimeException( "Seeded $post_type '$slug' not found" );
	}
	return $post;
}

/** Render a post's content through the_content, as the front end would (single page view). */
function render_post( \WP_Post $post ): string {
	global $wp_query;
	$GLOBALS['post'] = $post;
	$wp_query->post  = $post;
	setup_postdata( $post );
	$html = apply_filters( 'the_content', get_the_content( null, false, $post ) );
	wp_reset_postdata();
	return (string) $html;
}

function render_slug( string $slug, string $post_type = 'post' ): string {
	return render_post( post_by_slug( $slug, $post_type ) );
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

function squish( string $s ): string {
	return trim( preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * All rendered TOCs.
 *
 * @return array<int, array{tag:string, classes:string[], label:?string, title:?string, title_tag:?string, list_tag:?string, items:array<int, array{href:string, text:string, level:?int, classes:string[]}>, html:string}>
 */
function tocs( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[' . has_class_xpath( 'wp-block-acme-toc' ) . ']' ) as $node ) {
		$title = $xpath->query( './/*[' . has_class_xpath( 'acme-toc__title' ) . ']', $node )->item( 0 );
		$list  = $xpath->query( './/*[' . has_class_xpath( 'acme-toc__list' ) . ']', $node )->item( 0 );
		$items = array();
		foreach ( $xpath->query( './/li', $node ) as $li ) {
			$a     = $xpath->query( './/a', $li )->item( 0 );
			$level = null;
			if ( preg_match( '/\bacme-toc__item--h([1-6])\b/', $li->getAttribute( 'class' ), $m ) ) {
				$level = (int) $m[1];
			}
			$items[] = array(
				'href'    => $a ? $a->getAttribute( 'href' ) : '',
				'text'    => $a ? squish( $a->textContent ) : '',
				'level'   => $level,
				'classes' => preg_split( '/\s+/', trim( $li->getAttribute( 'class' ) ) ),
				'a_html'  => $a ? $a->ownerDocument->saveHTML( $a ) : '',
			);
		}
		$out[] = array(
			'tag'       => strtolower( $node->nodeName ),
			'classes'   => preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ),
			'label'     => $node->hasAttribute( 'aria-label' ) ? $node->getAttribute( 'aria-label' ) : null,
			'title'     => $title ? squish( $title->textContent ) : null,
			'title_tag' => $title ? strtolower( $title->nodeName ) : null,
			'list_tag'  => $list ? strtolower( $list->nodeName ) : null,
			'items'     => $items,
			'html'      => $node->ownerDocument->saveHTML( $node ),
		);
	}
	return $out;
}

/** Headings (h1-h6) outside of TOCs: [ ['tag'=>'h2','id'=>?, 'text'=>...], ... ] in document order. */
function headings( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $h ) {
		if ( $xpath->query( 'ancestor::*[' . has_class_xpath( 'wp-block-acme-toc' ) . ']', $h )->length ) {
			continue;
		}
		$out[] = array(
			'tag'  => strtolower( $h->nodeName ),
			'id'   => $h->hasAttribute( 'id' ) ? $h->getAttribute( 'id' ) : null,
			'text' => squish( $h->textContent ),
		);
	}
	return $out;
}

/** All id attribute values in the HTML. */
function ids( string $html ): array {
	$xpath = dom( $html );
	$out   = array();
	foreach ( $xpath->query( '//*[@id]' ) as $el ) {
		$out[] = $el->getAttribute( 'id' );
	}
	return $out;
}

/** Fragment of a URL (without '#'), or null. */
function fragment( string $href ): ?string {
	$pos = strpos( $href, '#' );
	return false === $pos ? null : rawurldecode( substr( $href, $pos + 1 ) );
}

/** Resolve a (possibly relative) href against a base URL; returns scheme://host/path?query#frag. */
function resolve( string $href, string $base ): string {
	if ( preg_match( '#^https?://#', $href ) ) {
		return $href;
	}
	$b = wp_parse_url( $base );
	$origin = $b['scheme'] . '://' . $b['host'] . ( isset( $b['port'] ) ? ':' . $b['port'] : '' );
	if ( '' === $href ) {
		return $base;
	}
	if ( '#' === $href[0] ) {
		return preg_replace( '/#.*$/', '', $base ) . $href;
	}
	if ( '/' === $href[0] ) {
		return $origin . $href;
	}
	$dir = preg_replace( '#/[^/]*$#', '/', $b['path'] ?? '/' );
	return $origin . $dir . $href;
}

/** Translate the default title in the acme-toc text domain (simulates a loaded translation). */
function translate_default_title( $translation, $text, $context_or_domain = '', $domain = null ) {
	$domain = null === $domain ? $context_or_domain : $domain;
	if ( 'acme-toc' === $domain && 'Table of contents' === $text ) {
		return 'Inhaltsverzeichnis';
	}
	return $translation;
}
